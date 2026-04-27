<?php

namespace App\Services;

use App\Enums\LinkedInPostStatus;
use App\Models\LinkedInPost;
use App\Models\PostTranslation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Bridges Portfolio_v2 backend to the `linkedin-post-writer` plugin.
 *
 * The plugin (v0.2.0+, https://github.com/alisadikinma/linkedin-post-writer)
 * emits a SINGLE JSON blob to stdout matching `OrchestratorOutputSchema`:
 *   { status: 'complete'|'failed', format: 'text'|'carousel',
 *     brief: {...}, post: {...}|null, carousel: {...}|null,
 *     validation: {...}|null, error?: {step, message}, generated_at: ISO8601 }
 *
 * Per plugin Addendum 3, the plugin NEVER calls the backend. This service
 * owns: input construction (blog URL + title + content), SSH invocation,
 * stdout parsing, persistence to `linkedin_posts`, and FSM advancement.
 *
 * Execution is SYNCHRONOUS from the backend's perspective — the plugin
 * runs ~30-90s on the VPS, we block and read stdout. Callers should
 * dispatch this from a queued `GenerateLinkedInPost` job so the web
 * request thread is never blocked.
 */
class LinkedInGenerationService
{
    public function __construct(private readonly PipelineGuard $guard)
    {
    }

    /**
     * Generate content for a LinkedInPost draft by invoking the plugin's
     * `/linkedin-gen` orchestrator. Advances FSM PendingGeneration →
     * Generating → Validating → (AwaitingPublish|ManualReview), or → Failed
     * on any error.
     *
     * @return array{success: bool, draft_id: int, status: string, depth_score?: int|null, error?: string|null}
     */
    public function generate(LinkedInPost $draft): array
    {
        // Already in progress — don't double-dispatch
        if (in_array($draft->status, ['generating', 'validating'], true)) {
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => $draft->status,
                'error' => "Draft already in-flight (status={$draft->status})",
            ];
        }

        // Step 1: Advance to Generating (from PendingGeneration / Failed / Cancelled)
        try {
            $this->guard->advance($draft, LinkedInPostStatus::Generating, 'plugin_dispatch_start');
        } catch (\App\Exceptions\InvalidStateTransitionException $e) {
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => $draft->status,
                'error' => $e->getMessage(),
            ];
        }

        // Step 2: Build blog payload from Post + primary PostTranslation
        $blog = $this->buildBlogPayload($draft);
        if ($blog === null) {
            $this->markFailed($draft, 'Cannot build blog payload — post or primary translation missing');
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => 'failed',
                'error' => 'Post or translation missing',
            ];
        }

        // Step 3: Invoke plugin
        $result = $this->invokePlugin($blog, $draft->id);
        if (!$result['success']) {
            $this->markFailed($draft, $result['error'] ?? 'Plugin invocation failed');
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => 'failed',
                'error' => $result['error'],
            ];
        }

        // Step 4: Parse JSON stdout
        $parsed = $this->parseOrchestratorOutput($result['stdout']);
        if ($parsed === null) {
            $this->markFailed($draft, 'Could not parse orchestrator JSON from stdout');
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => 'failed',
                'error' => 'Invalid JSON from plugin',
            ];
        }

        // Step 5: Handle plugin-reported failure
        if (($parsed['status'] ?? null) === 'failed') {
            $errStep = $parsed['error']['step'] ?? 'unknown';
            $errMsg = $parsed['error']['message'] ?? 'Plugin reported failed without message';
            $this->markFailed($draft, "Plugin failed at step={$errStep}: {$errMsg}");
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => 'failed',
                'error' => $errMsg,
            ];
        }

        // Step 6: Persist content + advance FSM
        return $this->persistAndRoute($draft, $parsed);
    }

    /**
     * Build the `{url, title, content}` blog payload from the draft's Post.
     * Prefers primary-language translation. Returns null if missing.
     */
    private function buildBlogPayload(LinkedInPost $draft): ?array
    {
        $draft->loadMissing('post.translations');
        $post = $draft->post;
        if ($post === null) {
            return null;
        }

        // Primary translation: 'id' (Indonesian) by project convention, fall back to first
        $translation = $post->translations->firstWhere('language', 'id')
            ?? $post->translations->firstWhere('language', 'en')
            ?? $post->translations->first();

        if ($translation === null) {
            return null;
        }

        $title = trim((string) $translation->title);
        $content = $this->stripHtmlToText((string) $translation->content);

        if ($title === '' || mb_strlen($content) < 100) {
            return null;
        }

        $appUrl = rtrim((string) config('app.url', 'https://alisadikinma.com'), '/');
        $url = $appUrl . '/blog/' . $post->slug;

        return compact('url', 'title', 'content');
    }

    /**
     * Convert HTML to plain text for plugin consumption. Plugin expects
     * markdown-like content >=100 chars. Basic strip — preserves paragraph
     * breaks.
     */
    private function stripHtmlToText(string $html): string
    {
        // Replace block-level close tags with double newline for paragraph breaks
        $text = preg_replace('/<\/(p|div|h[1-6]|li|blockquote|br)\s*>/i', "\n\n", $html);
        $text = preg_replace('/<br\s*\/?>/i', "\n", (string) $text);
        $text = strip_tags((string) $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", (string) $text);
        return trim((string) $text);
    }

    /**
     * SSH-invoke `claude -p "/linkedin-gen <blog-json>" --model ... --append-system-prompt-file ... x4`
     * on the VPS and capture stdout.
     *
     * @return array{success: bool, stdout: string, error: string|null}
     */
    private function invokePlugin(array $blog, int $draftId): array
    {
        $driver = (string) config('linkedin.generation.driver', 'ssh');
        $model = (string) config('linkedin.generation.model', 'sonnet');
        $timeout = (int) config('linkedin.generation.timeout_seconds', 300);

        $refsFlags = $this->buildRefsFlags();

        // Input JSON matching OrchestratorInputSchema: { blog: { url, title, content } }
        $inputJson = json_encode(['blog' => $blog], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($inputJson === false) {
            return ['success' => false, 'stdout' => '', 'error' => 'Failed to encode blog payload to JSON'];
        }

        // Build prompt: `/linkedin-gen` skill reads the blog from stdin (pipeline mode).
        // For Claude CLI `-p` mode, we embed the JSON inline in the prompt so the
        // skill sees it at invocation time.
        $prompt = "/linkedin-gen\n\nINPUT:\n" . $inputJson;

        if ($driver === 'local') {
            return $this->executeLocal($prompt, $model, $refsFlags, $timeout);
        }

        return $this->executeSSH($prompt, $model, $refsFlags, $timeout, $draftId);
    }

    private function buildRefsFlags(): string
    {
        $flags = [];
        foreach (['refs_playbook', 'refs_templates', 'refs_formats', 'refs_carousel'] as $key) {
            $path = (string) config("linkedin.generation.{$key}", '');
            if ($path !== '') {
                $flags[] = '--append-system-prompt-file ' . escapeshellarg($path);
            }
        }
        return implode(' ', $flags);
    }

    private function executeLocal(string $prompt, string $model, string $refsFlags, int $timeout): array
    {
        $claudePath = (string) config('linkedin.generation.claude_path', 'claude');
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $tmpDir = sys_get_temp_dir();
        $promptFile = $tmpDir . DIRECTORY_SEPARATOR . 'linkedin-gen-' . uniqid() . '.txt';
        file_put_contents($promptFile, $prompt);

        // Reverted --effort max: production-tested on draft #31, Sonnet hung
        // past the 880s SSH timeout cap with no output. Default effort
        // generates carousel JSON in 2-3 min reliably; the truncation issue
        // (caption/hashtags/link_comment dropped on draft #30) is being
        // solved structurally in plugin v0.4.6 by tightening per-layout
        // copy length invariants (so each slide is shorter, freeing tokens
        // for the post-body fields).
        try {
            if ($isWindows) {
                $cmd = "& \"{$claudePath}\" -p (Get-Content -Raw \"{$promptFile}\") --model {$model} {$refsFlags} --dangerously-skip-permissions";
                $result = Process::timeout($timeout)->run(['powershell', '-Command', $cmd]);
            } else {
                $result = Process::timeout($timeout)->run([
                    'bash', '-lc',
                    "{$claudePath} -p \"\$(cat " . escapeshellarg($promptFile) . ")\" --model {$model} {$refsFlags} --dangerously-skip-permissions",
                ]);
            }
        } finally {
            @unlink($promptFile);
        }

        if (!$result->successful()) {
            return [
                'success' => false,
                'stdout' => $result->output(),
                'error' => 'Local exec failed: ' . ($result->errorOutput() ?: 'exit ' . $result->exitCode()),
            ];
        }

        return ['success' => true, 'stdout' => $result->output(), 'error' => null];
    }

    private function executeSSH(string $prompt, string $model, string $refsFlags, int $timeout, int $draftId): array
    {
        $sshHost = (string) config('linkedin.generation.ssh_host');
        $sshUser = (string) config('linkedin.generation.ssh_user');
        $sshKey = (string) config('linkedin.generation.ssh_key');
        $claudePath = (string) config('linkedin.generation.claude_path', 'claude');

        $promptFile = "/tmp/linkedin-gen-{$draftId}-" . uniqid() . '.txt';
        $base64Prompt = base64_encode($prompt);

        $keyOpt = $sshKey ? "-i " . escapeshellarg($sshKey) : '';
        $sshPrefix = "ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 {$keyOpt} {$sshUser}@{$sshHost} ";

        // Step A: write prompt file to VPS
        $writeCmd = $sshPrefix . escapeshellarg("echo {$base64Prompt} | base64 -d > {$promptFile}");
        $writeResult = Process::timeout(15)->run($writeCmd);
        if (!$writeResult->successful()) {
            return ['success' => false, 'stdout' => '', 'error' => 'SSH prompt write failed: ' . $writeResult->errorOutput()];
        }

        // Step B: invoke claude CLI synchronously; tee stdout back over SSH.
        // The remote `timeout` wrapper self-kills the claude process if it
        // exceeds budget — without it, Symfony's local Process::timeout would
        // tear down the SSH client but leave bash + claude orphaned on the
        // remote (verified in production on draft #1, post #24). Reserve 20s
        // for SSH connect + cleanup so the local timeout never trips first.
        $remoteTimeout = max(30, $timeout - 20);
        // Reverted --effort max: see executeLocal() comment for rationale.
        $remoteCmd = "bash -lc 'source ~/.profile 2>/dev/null; timeout --kill-after=10s {$remoteTimeout} {$claudePath} -p \"\$(cat {$promptFile})\" --model {$model} {$refsFlags} --dangerously-skip-permissions; STATUS=\$?; rm -f {$promptFile} 2>/dev/null || true; exit \$STATUS'";
        $runCmd = $sshPrefix . escapeshellarg($remoteCmd);

        $result = Process::timeout($timeout)->run($runCmd);

        if (!$result->successful()) {
            return [
                'success' => false,
                'stdout' => $result->output(),
                'error' => 'SSH exec failed: ' . ($result->errorOutput() ?: 'exit ' . $result->exitCode()),
            ];
        }

        return ['success' => true, 'stdout' => $result->output(), 'error' => null];
    }

    /**
     * Parse plugin stdout for the first top-level JSON object matching the
     * orchestrator schema. Plugin may emit interactive narration after the
     * JSON — we anchor on the leading `{` and match balanced braces.
     *
     * Returns null on parse failure.
     */
    public function parseOrchestratorOutput(string $raw): ?array
    {
        $text = trim($raw);
        if ($text === '') {
            return null;
        }

        // Strip optional markdown fence
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```.*$/s', '', (string) $text);

        // Anchor on first `{`
        $start = strpos($text, '{');
        if ($start === false) {
            return null;
        }

        // Balanced brace scan to find the matching `}` even if interactive
        // narration follows.
        $depth = 0;
        $end = null;
        $inString = false;
        $escape = false;
        for ($i = $start; $i < strlen($text); $i++) {
            $c = $text[$i];
            if ($inString) {
                if ($escape) {
                    $escape = false;
                } elseif ($c === '\\') {
                    $escape = true;
                } elseif ($c === '"') {
                    $inString = false;
                }
                continue;
            }
            if ($c === '"') {
                $inString = true;
                continue;
            }
            if ($c === '{') {
                $depth++;
            } elseif ($c === '}') {
                $depth--;
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }

        if ($end === null) {
            return null;
        }

        $jsonBlob = substr($text, $start, $end - $start + 1);
        $decoded = json_decode($jsonBlob, true);
        if (!is_array($decoded)) {
            return null;
        }
        return $decoded;
    }

    /**
     * Persist orchestrator output to the draft row and advance FSM.
     * Text path: content + hashtags + link_comment
     * Carousel path: carousel_slides + content (cover copy as fallback)
     *
     * Routing:
     *   validation.passed=true → Validating → AwaitingPublish (+ schedule window)
     *   validation.passed=false → Validating → ManualReview
     *
     * @return array{success: bool, draft_id: int, status: string, depth_score?: int|null}
     */
    private function persistAndRoute(LinkedInPost $draft, array $parsed): array
    {
        $format = $parsed['format'] ?? $draft->format;
        $validation = $parsed['validation'] ?? [];
        $depthScore = isset($validation['depth_score']) ? (int) $validation['depth_score'] : null;
        $passed = (bool) ($validation['passed'] ?? false);

        $updates = [
            'format' => $format,
            'depth_score' => $depthScore,
            'validation_log' => $validation,
        ];

        if ($format === 'carousel' && is_array($parsed['carousel'] ?? null)) {
            $carousel = $parsed['carousel'];
            $updates['carousel_slides'] = $carousel['slides'] ?? [];

            // Plugin v0.4.3+ emits caption + hashtags + link_comment at the
            // carousel root (full LinkedIn post body — swipe teaser, hashtag
            // mix, link-in-comment bridge). Plugin v0.4.6+ splits per-slide
            // copy into copy_id (Indonesian, main headline) + copy_en
            // (English, subtitle); older plugins use single `copy` field.
            //
            // Fallback chain (from best to worst, each may be missing):
            //   caption     → carousel.caption ?? cover_slide.copy_en ??
            //                 cover_slide.copy ?? ''
            //   hashtags    → carousel.hashtags ?? brief.hashtags ?? []
            //   link_comment → carousel.link_comment ?? brief.pull_quote ?? ''
            $coverSlide = collect($carousel['slides'] ?? [])->firstWhere('is_cover', true)
                ?? ($carousel['slides'][0] ?? []);

            $updates['content'] = (string) (
                $carousel['caption']
                ?? $coverSlide['copy_en']
                ?? $coverSlide['copy']
                ?? ''
            );
            $updates['hashtags'] = is_array($carousel['hashtags'] ?? null)
                ? $carousel['hashtags']
                : ($parsed['brief']['hashtags'] ?? []);
            $updates['link_comment'] = (string) (
                $carousel['link_comment']
                ?? $parsed['brief']['pull_quote']
                ?? ''
            );
        } else {
            // Text path
            $post = $parsed['post'] ?? [];
            $updates['content'] = (string) ($post['post_text'] ?? '');
            $updates['hashtags'] = is_array($post['hashtags'] ?? null) ? $post['hashtags'] : [];
            $updates['link_comment'] = (string) ($post['link_comment'] ?? '');
        }

        $draft->update($updates);

        // Advance: Generating → Validating
        try {
            $this->guard->advance($draft, LinkedInPostStatus::Validating, 'plugin_validate_start', [
                'depth_score' => $depthScore,
            ]);
        } catch (\App\Exceptions\InvalidStateTransitionException $e) {
            Log::warning('[LinkedInGeneration] Could not transition to Validating', [
                'draft_id' => $draft->id,
                'current_status' => $draft->status,
                'error' => $e->getMessage(),
            ]);
        }

        // Route by validation result
        $nextState = $passed ? LinkedInPostStatus::AwaitingPublish : LinkedInPostStatus::ManualReview;
        $reason = $passed ? 'plugin_passed_gate' : 'plugin_failed_gate';

        try {
            $this->guard->advance($draft, $nextState, $reason, [
                'depth_score' => $depthScore,
                'passed' => $passed,
            ]);
        } catch (\App\Exceptions\InvalidStateTransitionException $e) {
            Log::error('[LinkedInGeneration] Routing transition failed', [
                'draft_id' => $draft->id,
                'target' => $nextState->value,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => $draft->status,
                'depth_score' => $depthScore,
                'error' => $e->getMessage(),
            ];
        }

        // Set schedule window when advancing to AwaitingPublish
        if ($nextState === LinkedInPostStatus::AwaitingPublish) {
            $windowMinutes = (int) \App\Models\Setting::get('linkedin_cancel_window_minutes', config('linkedin.cancel_window_minutes', 15));
            $draft->update([
                'scheduled_at' => now(),
                'cancel_window_ends_at' => now()->addMinutes($windowMinutes),
            ]);
        }

        // Dispatch carousel image rendering for either AwaitingPublish or
        // ManualReview — operators want to SEE the rendered slides during
        // manual review, not just the slide copy. Idempotent: the service
        // skips slides already done.
        if ($format === 'carousel') {
            try {
                \App\Jobs\GenerateLinkedInCarouselImages::dispatch($draft->id);
                Log::info('[LinkedInGeneration] dispatched carousel image job', [
                    'draft_id' => $draft->id,
                    'next_state' => $nextState->value,
                ]);
            } catch (\Throwable $e) {
                Log::error('[LinkedInGeneration] carousel image dispatch failed', [
                    'draft_id' => $draft->id,
                    'error' => $e->getMessage(),
                ]);
                // Do not fail the whole pipeline — admin can manually trigger
                // regenerate-all from the UI.
            }
        }

        return [
            'success' => true,
            'draft_id' => $draft->id,
            'status' => $draft->fresh()->status,
            'depth_score' => $depthScore,
        ];
    }

    private function markFailed(LinkedInPost $draft, string $reason): void
    {
        try {
            $this->guard->advance($draft, LinkedInPostStatus::Failed, 'generation_error', [
                'error' => $reason,
            ]);
            $draft->update(['last_error' => $reason]);
        } catch (\Throwable $e) {
            Log::error('[LinkedInGeneration] Could not mark failed', [
                'draft_id' => $draft->id,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
