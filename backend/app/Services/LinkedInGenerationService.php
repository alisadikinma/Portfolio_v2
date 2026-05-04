<?php

namespace App\Services;

use App\Enums\LinkedInPostStatus;
use App\Exceptions\CarouselGenAdapterException;
use App\Models\LinkedInPost;
use App\Models\PostTranslation;
use App\Support\LinkedInProgressEmitter;
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
    public function __construct(
        private readonly PipelineGuard $guard,
        private readonly CarouselGenOutputAdapter $carouselAdapter = new CarouselGenOutputAdapter(),
    ) {
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
            LinkedInProgressEmitter::reset($draft, 'pipeline_start');
            LinkedInProgressEmitter::emit($draft, 'plugin_dispatch', 5, 'Dispatching /linkedin-gen plugin to VPS');
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

        $detectedFormat = $parsed['format'] ?? 'unknown';
        $isCarouselRoute = ($parsed['status'] ?? null) === 'route_to_carousel_gen';
        $parsedPct = $detectedFormat === 'carousel' || $isCarouselRoute ? 25 : 50;
        LinkedInProgressEmitter::emit(
            $draft,
            'orchestrator_parsed',
            $parsedPct,
            $isCarouselRoute
                ? 'Plugin output parsed — routing to /carousel-gen engine'
                : "Plugin output parsed — format={$detectedFormat}"
        );

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

        // Step 5.5: Plugin v0.5.0 — for carousel format, /linkedin-gen
        // emits status='route_to_carousel_gen' (brief only, all other slots
        // null). Dispatch /carousel-gen, run the adapter, and assemble the
        // final carousel into $parsed.carousel.slides. No feature flag —
        // /carousel-gen is the only carousel path post-v0.5.0.
        if ($detectedFormat === 'carousel' || $isCarouselRoute) {
            LinkedInProgressEmitter::emit($draft, 'carousel_gen_dispatch', 30, 'Dispatching /carousel-gen engine');
        }
        try {
            $parsed = $this->applyCarouselGenAdapter($parsed, $blog['url'], $draft->id);
        } catch (CarouselGenAdapterException $e) {
            $this->markFailed($draft, "carousel-gen adapter failed: {$e->getMessage()}");
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }

        if ($detectedFormat === 'carousel' || $isCarouselRoute) {
            $slideCount = count($parsed['carousel']['slides'] ?? []);
            LinkedInProgressEmitter::emit(
                $draft,
                'slides_assembled',
                55,
                "{$slideCount} slides assembled by /carousel-gen adapter"
            );
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
        // Plugin v0.5.0 retired refs_carousel — carousel design specs live in
        // /carousel-gen plugin, not /linkedin-gen.
        $flags = [];
        foreach (['refs_playbook', 'refs_templates', 'refs_formats'] as $key) {
            $path = (string) config("linkedin.generation.{$key}", '');
            if ($path !== '') {
                $flags[] = '--append-system-prompt-file ' . escapeshellarg($path);
            }
        }
        return implode(' ', $flags);
    }

    /**
     * Returns the --mcp-config flags that disable ALL MCP server boot for
     * pipeline runs. Without this, every claude CLI invocation spawns its
     * full MCP server stack (obsidian-mcp, firecrawl, playwright, etc.) —
     * obsidian-mcp in particular leaks its child node process when the
     * parent claude exits, accumulating ~60MB RSS each per leak. Production
     * incident on April 29, 2026 saw 140 leaked obsidian-mcp processes
     * consuming 8.7GB RSS over 4 days, hanging the carousel-gen runs.
     *
     * `--mcp-config /home/claudesn/empty-mcp.json` points at a JSON file
     * with `{"mcpServers": {}}` — zero servers configured.
     * `--strict-mcp-config` tells claude to use ONLY that config and ignore
     * the user-level `~/.claude.json` MCP entries entirely.
     *
     * Pipeline runs don't need MCP servers — all required context comes via
     * `--append-system-prompt-file` (compiled refs) and the prompt itself.
     */
    private function buildMcpFlags(): string
    {
        $emptyConfig = (string) config(
            'linkedin.generation.empty_mcp_config',
            '/home/claudesn/empty-mcp.json'
        );
        if ($emptyConfig === '') {
            return '';
        }
        return '--mcp-config ' . escapeshellarg($emptyConfig) . ' --strict-mcp-config';
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
        $mcpFlags = $this->buildMcpFlags();
        try {
            if ($isWindows) {
                $cmd = "& \"{$claudePath}\" -p (Get-Content -Raw \"{$promptFile}\") --model {$model} {$refsFlags} {$mcpFlags} --dangerously-skip-permissions";
                $result = Process::timeout($timeout)->run(['powershell', '-Command', $cmd]);
            } else {
                $result = Process::timeout($timeout)->run([
                    'bash', '-lc',
                    "{$claudePath} -p \"\$(cat " . escapeshellarg($promptFile) . ")\" --model {$model} {$refsFlags} {$mcpFlags} --dangerously-skip-permissions",
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
        $mcpFlags = $this->buildMcpFlags();
        // Reverted --effort max: see executeLocal() comment for rationale.
        $remoteCmd = "bash -lc 'source ~/.profile 2>/dev/null; timeout --kill-after=10s {$remoteTimeout} {$claudePath} -p \"\$(cat {$promptFile})\" --model {$model} {$refsFlags} {$mcpFlags} --dangerously-skip-permissions; STATUS=\$?; rm -f {$promptFile} 2>/dev/null || true; exit \$STATUS'";
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
     * orchestrator schema. Plugin may emit a Sonnet-narrated preamble
     * ("Verified facts… let me assemble the JSON…") and wrap the JSON in a
     * ```json fenced block — the balanced-brace scanner anchors on the first
     * `{` and ignores both prefixes/suffixes naturally.
     *
     * Earlier versions stripped fences with `preg_replace('/\s*```.*$/s', '')`,
     * but with `s`-flag the LEFTMOST `\s*```` match landed on the OPENING
     * ```json fence and the greedy `.*$` consumed the entire JSON to EOF —
     * leaving only preamble. Real production failure on draft #43 (Apr 29)
     * surfaced the regression. Dropping fence-strip entirely is safe because
     * `strpos($text, '{')` skips Sonnet preamble (preamble bullets contain
     * markdown brackets `[]` but never `{`) and the balanced-brace scanner
     * stops at the matched `}` regardless of trailing fence/narration.
     *
     * Returns null on parse failure.
     */
    public function parseOrchestratorOutput(string $raw): ?array
    {
        $text = (string) $raw;
        if (trim($text) === '') {
            return null;
        }

        // Anchor on first `{` — skips Sonnet preamble narration and any
        // ```json fence opener (fence chars are backtick + 'json' + newline,
        // none of which include `{`).
        $start = strpos($text, '{');
        if ($start === false) {
            return null;
        }

        // Balanced brace scan to find the matching `}` even if a closing
        // ```fence or interactive narration follows.
        $depth = 0;
        $end = null;
        $inString = false;
        $escape = false;
        $len = strlen($text);
        for ($i = $start; $i < $len; $i++) {
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
            // Clear stale last_error from any prior failed attempt — the FSM has
            // now reached Validating with valid parsed output, so any previously
            // persisted parse-error / SSH-timeout / safety-rewrite message is no
            // longer the truth about the draft. Without this clear, the admin UI
            // surfaces a misleading "Could not parse orchestrator JSON" banner
            // on drafts that subsequently regenerated successfully.
            'last_error' => null,
        ];

        if ($format === 'carousel') {
            // Strict guard: by the time persistAndRoute runs for carousel
            // format, applyCarouselGenAdapter MUST have populated slides[]
            // from /carousel-gen. A missing or empty slot here means the
            // adapter was bypassed (caller bug) — refuse to persist garbage.
            $carousel = is_array($parsed['carousel'] ?? null) ? $parsed['carousel'] : [];
            $slides = is_array($carousel['slides'] ?? null) ? $carousel['slides'] : [];
            if (empty($slides)) {
                $this->markFailed(
                    $draft,
                    'Carousel persist guard: /carousel-gen produced no slides — refusing to publish empty carousel.'
                );
                return [
                    'success' => false,
                    'draft_id' => $draft->id,
                    'status' => 'failed',
                    'error' => 'Carousel persist guard: empty slides',
                ];
            }
            $updates['carousel_slides'] = $slides;

            // Backend is the sole source of truth for caption + hashtags +
            // link_comment on carousel format — /carousel-gen focuses on
            // slide visuals only (caption / hashtags / link CTA are LinkedIn-
            // publisher-side concerns, not part of the universal carousel
            // engine's contract).
            $updates['content'] = $this->buildCarouselCaption('', $carousel, $parsed['brief'] ?? [], $draft);
            $updates['hashtags'] = $this->resolveHashtags(
                null,
                $parsed['brief']['hashtags'] ?? null,
                $draft
            );
            $updates['link_comment'] = $this->resolveLinkComment('', $draft);
        } else {
            // Text path
            $post = $parsed['post'] ?? [];
            $updates['content'] = (string) ($post['post_text'] ?? '');
            $updates['hashtags'] = $this->resolveHashtags(
                $post['hashtags'] ?? null,
                $parsed['brief']['hashtags'] ?? null,
                $draft
            );
            $updates['link_comment'] = $this->resolveLinkComment(
                (string) ($post['link_comment'] ?? ''),
                $draft
            );
        }

        $draft->update($updates);

        if ($format === 'carousel') {
            LinkedInProgressEmitter::emit($draft, 'structural_check', 60, 'Carousel structural validation passed');
        } else {
            $depthLabel = $depthScore !== null ? "depth_score={$depthScore}/100" : 'depth_score=n/a';
            LinkedInProgressEmitter::emit(
                $draft,
                'gates_evaluated',
                75,
                "Validation complete · {$depthLabel} · " . ($passed ? 'passed' : 'manual review')
            );
        }

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

        // Carousel: FSM advanced, image rendering pending. Text: FSM advanced
        // is the terminal step before publish, so progress can hit 100.
        if ($format === 'carousel') {
            LinkedInProgressEmitter::emit(
                $draft,
                'fsm_advanced',
                65,
                "FSM → {$nextState->value} · slide rendering pending"
            );
        } else {
            LinkedInProgressEmitter::emit(
                $draft,
                'fsm_advanced',
                95,
                "FSM → {$nextState->value}"
            );
            LinkedInProgressEmitter::emit($draft, 'completed', 100, 'Text generation complete');
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

    /**
     * Carousel router — STRICT /carousel-gen enforcement (no legacy fallback).
     *
     * Behavior matrix:
     *   format='text'                                 → return $parsed unchanged
     *   format='carousel' + status='route_to_carousel_gen' → dispatch /carousel-gen,
     *                                                         build carousel slot
     *                                                         from adapter output
     *   format='carousel' + ANY OTHER status          → throw (rejects legacy
     *                                                   plugin envelopes, hand-
     *                                                   crafted JSON, etc.)
     *
     * Plugin v0.5.0+ short-circuits at the brief stage for carousel format —
     * /linkedin-gen emits status='route_to_carousel_gen' with carousel=null
     * and validation=null. The backend MUST build the carousel slot from the
     * /carousel-gen adapter output and only that path. Validation does not
     * apply to carousels (the /carousel-gen schema enforces structural quality).
     *
     * Pre-v0.5.0 envelopes with inline `status='complete' + format='carousel'`
     * slides are explicitly rejected here — operator-side regenerate flow
     * surfaces this as FSM Failed with a clear "legacy envelope rejected"
     * message rather than silently honoring stale plugin output.
     *
     * @throws CarouselGenAdapterException When /carousel-gen returns null,
     *                                      empty, status=failed, OR when the
     *                                      orchestrator emitted a
     *                                      non-route_to_carousel_gen status
     *                                      for carousel format. Caller
     *                                      catches and routes to FSM Failed.
     */
    public function applyCarouselGenAdapter(array $parsed, string $blogUrl, int $draftId): array
    {
        $format = $parsed['format'] ?? null;
        if ($format !== 'carousel') {
            return $parsed;
        }

        $status = $parsed['status'] ?? null;

        // Strict v0.5.0+ enforcement: only `route_to_carousel_gen` is a valid
        // carousel envelope. Legacy `complete` envelopes (with inline slides
        // emitted by the retired /linkedin-carousel skill) are rejected so
        // we never publish slides authored outside /carousel-gen.
        if ($status !== 'route_to_carousel_gen') {
            throw new CarouselGenAdapterException(
                "Carousel format requires status='route_to_carousel_gen' (plugin v0.5.0+); "
                . 'got status=' . var_export($status, true) . '. '
                . 'Legacy envelopes with inline slides are rejected — re-run plugin or use admin Regenerate.'
            );
        }

        $brief = is_array($parsed['brief'] ?? null) ? $parsed['brief'] : [];

        Log::info('[LinkedInGeneration] dispatching /carousel-gen engine', [
            'draft_id' => $draftId,
            'blog_url' => $blogUrl,
            'brief_hook' => $brief['hook_framework'] ?? null,
            'brief_pillar' => $brief['pillar'] ?? null,
        ]);

        $carouselGenJson = $this->dispatchCarouselGenEngine($brief, $blogUrl, $draftId);

        if ($carouselGenJson === null) {
            throw new CarouselGenAdapterException(
                'carousel-gen dispatch failed or returned null/empty stdout'
            );
        }

        // Adapter throws on status=failed and on any other unexpected status.
        // The slides[] returned here matches the linkedin_posts.carousel_slides
        // JSON shape (with image_status='pending' lifecycle fields initialized).
        $adaptedSlides = $this->carouselAdapter->adapt($carouselGenJson);

        // Build the carousel slot fresh from adapter output — orchestrator
        // sends carousel=null on route_to_carousel_gen, so there's nothing
        // to merge with. Slides + bilingual + narrative come solely from
        // /carousel-gen.
        $parsed['carousel'] = [
            'slides' => $adaptedSlides,
        ];
        if (isset($carouselGenJson['bilingual'])) {
            $parsed['carousel']['bilingual'] = (bool) $carouselGenJson['bilingual'];
        }
        if (isset($carouselGenJson['narrative'])) {
            $parsed['carousel']['narrative'] = (string) $carouselGenJson['narrative'];
        }

        // Promote route_to_carousel_gen → complete now that the carousel is
        // materialized. Downstream persistAndRoute treats status=complete as
        // the FSM advance trigger.
        $parsed['status'] = 'complete';

        Log::info('[LinkedInGeneration] /carousel-gen adapter applied', [
            'draft_id' => $draftId,
            'slide_count' => count($adaptedSlides),
            'bilingual' => $carouselGenJson['bilingual'] ?? null,
        ]);

        return $parsed;
    }

    /**
     * SSH-invoke `claude -p "/carousel-gen --pipeline --blog-source=<url> ..."` on the
     * VPS and parse stdout via the existing balanced-brace scanner. Returns
     * the decoded JSON envelope (matching CarouselGenOutputSchema), or null
     * if the SSH call failed or stdout could not be parsed.
     *
     * Public so it can be Mockery-mocked in unit tests without booting the
     * full SSH stack.
     */
    public function dispatchCarouselGenEngine(array $brief, string $blogUrl, int $draftId): ?array
    {
        $driver = (string) config('carousel-gen.driver', 'ssh');
        $model = (string) config('carousel-gen.model', 'sonnet');
        $timeout = (int) config('carousel-gen.timeout_seconds', 600);
        $refsPath = (string) config('carousel-gen.refs_pipeline', '');

        // Decide bilingual + target_slides from brief if available.
        // /linkedin-gen carousel briefs default to bilingual ID/EN (LinkedIn
        // pillar) so we mirror that.
        $bilingual = 'id,en';
        $narrative = '5act';
        $targetSlides = $this->inferTargetSlides($brief);

        $flags = [
            '--pipeline',
            '--blog-source=' . escapeshellarg($blogUrl),
            '--bilingual=' . $bilingual,
            '--narrative=' . $narrative,
            '--target-slides=' . $targetSlides,
        ];
        $flagString = implode(' ', $flags);

        $refsFlag = $refsPath !== ''
            ? '--append-system-prompt-file ' . escapeshellarg($refsPath)
            : '';

        $prompt = "/carousel-gen {$flagString}";

        if ($driver === 'local') {
            $result = $this->executeCarouselGenLocal($prompt, $model, $refsFlag, $timeout);
        } else {
            $result = $this->executeCarouselGenSSH($prompt, $model, $refsFlag, $timeout, $draftId);
        }

        if (!$result['success']) {
            Log::error('[LinkedInGeneration] /carousel-gen invocation failed', [
                'draft_id' => $draftId,
                'driver' => $driver,
                'error' => $result['error'],
            ]);
            return null;
        }

        $parsed = $this->parseOrchestratorOutput($result['stdout']);
        if ($parsed === null) {
            // Dump full stdout to disk for forensics — production debugging
            // requires the entire model output, but laravel.log entries are
            // capped at log-line length. Path: storage/app/carousel-gen-debug/
            $stdoutLength = strlen($result['stdout']);
            $dumpPath = storage_path('app/carousel-gen-debug/draft-' . $draftId . '-' . date('YmdHis') . '.txt');
            try {
                @mkdir(dirname($dumpPath), 0755, true);
                file_put_contents($dumpPath, $result['stdout']);
            } catch (\Throwable $e) {
                $dumpPath = '(dump write failed: ' . $e->getMessage() . ')';
            }
            Log::error('[LinkedInGeneration] /carousel-gen stdout could not be parsed', [
                'draft_id' => $draftId,
                'stdout_length' => $stdoutLength,
                'stdout_head_2k' => substr($result['stdout'], 0, 2000),
                'stdout_tail_2k' => $stdoutLength > 4000 ? substr($result['stdout'], -2000) : null,
                'dump_path' => $dumpPath,
            ]);
            return null;
        }

        return $parsed;
    }

    /**
     * Heuristic: pick target slide count from brief signal.
     *
     * May 4, 2026: Reduced from 9 → 7 default after sustained Sonnet output
     * truncation on 9-slide bilingual carousels (model emits per-slide JSON
     * chunks with continuation prose instead of a single envelope, breaks
     * the orchestrator parser). 7 slides cuts ~22% of output tokens while
     * preserving the 5-act narrative arc (cover + 4 body + human + cta).
     * See CLAUDE.md May 2 entry "Open issue: Sonnet output truncation".
     */
    private function inferTargetSlides(array $brief): int
    {
        $framework = $brief['hook_framework'] ?? null;
        return match ($framework) {
            'before_after' => 7,
            'AIDA' => 6,
            'contrarian' => 7,
            default => 7,
        };
    }

    private function executeCarouselGenLocal(string $prompt, string $model, string $refsFlag, int $timeout): array
    {
        $claudePath = (string) config('carousel-gen.claude_path', 'claude');
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $tmpDir = sys_get_temp_dir();
        $promptFile = $tmpDir . DIRECTORY_SEPARATOR . 'carousel-gen-' . uniqid() . '.txt';
        file_put_contents($promptFile, $prompt);

        $mcpFlags = $this->buildMcpFlags();
        try {
            if ($isWindows) {
                $cmd = "& \"{$claudePath}\" -p (Get-Content -Raw \"{$promptFile}\") --model {$model} {$refsFlag} {$mcpFlags} --dangerously-skip-permissions";
                $result = Process::timeout($timeout)->run(['powershell', '-Command', $cmd]);
            } else {
                $result = Process::timeout($timeout)->run([
                    'bash', '-lc',
                    "{$claudePath} -p \"\$(cat " . escapeshellarg($promptFile) . ")\" --model {$model} {$refsFlag} {$mcpFlags} --dangerously-skip-permissions",
                ]);
            }
        } finally {
            @unlink($promptFile);
        }

        if (!$result->successful()) {
            return [
                'success' => false,
                'stdout' => $result->output(),
                'error' => 'carousel-gen local exec failed: ' . ($result->errorOutput() ?: 'exit ' . $result->exitCode()),
            ];
        }

        return ['success' => true, 'stdout' => $result->output(), 'error' => null];
    }

    private function executeCarouselGenSSH(string $prompt, string $model, string $refsFlag, int $timeout, int $draftId): array
    {
        $sshHost = (string) config('carousel-gen.ssh_host');
        $sshUser = (string) config('carousel-gen.ssh_user');
        $sshKey = (string) config('carousel-gen.ssh_key');
        $claudePath = (string) config('carousel-gen.claude_path', 'claude');

        $promptFile = "/tmp/carousel-gen-{$draftId}-" . uniqid() . '.txt';
        $base64Prompt = base64_encode($prompt);

        $keyOpt = $sshKey ? "-i " . escapeshellarg($sshKey) : '';
        $sshPrefix = "ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 {$keyOpt} {$sshUser}@{$sshHost} ";

        $writeCmd = $sshPrefix . escapeshellarg("echo {$base64Prompt} | base64 -d > {$promptFile}");
        $writeResult = Process::timeout(15)->run($writeCmd);
        if (!$writeResult->successful()) {
            return ['success' => false, 'stdout' => '', 'error' => 'SSH prompt write failed: ' . $writeResult->errorOutput()];
        }

        // Reserve 20s for SSH connect + cleanup.
        $remoteTimeout = max(30, $timeout - 20);
        $mcpFlags = $this->buildMcpFlags();
        $remoteCmd = "bash -lc 'source ~/.profile 2>/dev/null; timeout --kill-after=10s {$remoteTimeout} {$claudePath} -p \"\$(cat {$promptFile})\" --model {$model} {$refsFlag} {$mcpFlags} --dangerously-skip-permissions; STATUS=\$?; rm -f {$promptFile} 2>/dev/null || true; exit \$STATUS'";
        $runCmd = $sshPrefix . escapeshellarg($remoteCmd);

        $result = Process::timeout($timeout)->run($runCmd);

        if (!$result->successful()) {
            return [
                'success' => false,
                'stdout' => $result->output(),
                'error' => 'SSH carousel-gen exec failed: ' . ($result->errorOutput() ?: 'exit ' . $result->exitCode()),
            ];
        }

        return ['success' => true, 'stdout' => $result->output(), 'error' => null];
    }

    private function markFailed(LinkedInPost $draft, string $reason): void
    {
        try {
            $this->guard->advance($draft, LinkedInPostStatus::Failed, 'generation_error', [
                'error' => $reason,
            ]);
            $draft->update(['last_error' => $reason]);
            LinkedInProgressEmitter::fail($draft, $reason);
        } catch (\Throwable $e) {
            Log::error('[LinkedInGeneration] Could not mark failed', [
                'draft_id' => $draft->id,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Canonical blog URL for the draft's source post:
     *   {APP_URL}/blog/{slug}
     * Empty string when the post or slug is missing (defensive — prevents
     * malformed URLs from leaking into LinkedIn comments).
     */
    private function blogUrl(LinkedInPost $draft): string
    {
        $appUrl = rtrim((string) config('app.url', ''), '/');
        $slug = (string) ($draft->post?->slug ?? '');
        return ($appUrl !== '' && $slug !== '') ? "{$appUrl}/blog/{$slug}" : '';
    }

    /**
     * Resolve link_comment to always include the canonical blog URL.
     *
     * If the plugin-emitted link_comment already contains an http(s) URL,
     * trust it as-is (operator-defined CTA). Otherwise replace with
     * "Full article: {blogUrl}" — every LinkedIn post MUST surface the
     * source link via first-comment automation (avoids 60% reach penalty
     * on body links + maintains traffic-back to alisadikinma.com).
     *
     * Operators can still override via PUT /admin/linkedin-drafts/{id}
     * after generation if they want a different CTA copy.
     */
    private function resolveLinkComment(string $pluginValue, LinkedInPost $draft): string
    {
        $cleaned = trim($pluginValue);
        if ($cleaned !== '' && preg_match('#https?://#i', $cleaned) === 1) {
            return $cleaned;
        }
        $url = $this->blogUrl($draft);
        return $url !== '' ? "Full article: {$url}" : $cleaned;
    }

    /**
     * Build the LinkedIn post body for a carousel draft.
     *
     * Targets the 2026 LinkedIn carousel sweet spot: 800-1500 chars with a
     * Hook → Setup → Insights → Question → Swipe → Link structure. Long-form
     * captions sustain dwell time (61s+ correlates with 15.6% engagement vs
     * 1.2% for sub-3s posts), and carousel posts hit 6.60% engagement when
     * paired with story-driven captions.
     *
     * Plugin v0.5.0+ /carousel-gen engine doesn't emit caption (visual focus
     * only). Backend composes a 7-block caption:
     *   1. Hook (cover_id) — within first 210 chars (preview window)
     *   2. Punchline subtitle (cover_en, if distinct)
     *   3. Setup paragraph (blog excerpt or first body slide, ≤280 chars)
     *   4. Pull-quote / data point (brief.pull_quote, if distinct)
     *   5. Insight bullets (3 sharpest body slides as → bullets)
     *   6. Engagement question + Swipe CTA
     *   7. "Full article: link in comments ↓"
     *
     * Plugin captions ≥800 chars are trusted as-is (legacy v0.4.x envelopes).
     */
    /**
     * Re-synthesize caption + hashtags from existing carousel slides without
     * touching slide images, FSM state, or draft ID. Used by the admin
     * "Regenerate caption" button when slides look good but the caption
     * needs a refresh (operator scenario: re-authored slides preserve
     * caption per design — but operator wants caption updated to match
     * new slide content).
     *
     * Forces backend synthesis by passing empty pluginCaption — the trust
     * threshold (≥800 chars passthrough) won't fire so buildCarouselCaption
     * runs the 7-block synthesizer (hook, subtitle, setup, pull-quote,
     * insight bullets, engagement question, link CTA).
     *
     * Hashtags re-resolved with null plugin/brief inputs — falls through to
     * blog meta_keywords synthesis path with brand handle enforcement.
     *
     * Carousel-only — text format's "caption" IS the post body, can't be
     * regenerated independently of the post.
     */
    public function regenerateCaption(LinkedInPost $draft): array
    {
        if ($draft->format !== 'carousel') {
            return [
                'success' => false,
                'error' => 'Caption regeneration is only available for carousel drafts. Text-format captions are the post body and require a full regenerate.',
            ];
        }

        $slides = is_array($draft->carousel_slides) ? $draft->carousel_slides : [];
        if (empty($slides)) {
            return [
                'success' => false,
                'error' => 'Draft has no carousel slides to source caption content from.',
            ];
        }

        $draft->loadMissing(['post.translations']);

        $carousel = ['slides' => $slides];
        $brief = is_array($draft->validation_log['brief'] ?? null) ? $draft->validation_log['brief'] : [];

        $newCaption = $this->buildCarouselCaption('', $carousel, $brief, $draft);
        $newHashtags = $this->resolveHashtags(null, $brief['hashtags'] ?? null, $draft);

        $draft->update([
            'content' => $newCaption,
            'hashtags' => $newHashtags,
        ]);

        Log::info('[LinkedInGeneration] Caption regenerated', [
            'draft_id' => $draft->id,
            'caption_length' => mb_strlen($newCaption),
            'hashtag_count' => count($newHashtags),
        ]);

        return [
            'success' => true,
            'draft_id' => $draft->id,
            'content' => $newCaption,
            'hashtags' => $newHashtags,
        ];
    }

    private function buildCarouselCaption(string $pluginCaption, array $carousel, array $brief, LinkedInPost $draft): string
    {
        $cleaned = trim($pluginCaption);
        if ($cleaned !== '' && mb_strlen($cleaned) >= 800) {
            return $cleaned;
        }

        $slides = is_array($carousel['slides'] ?? null) ? $carousel['slides'] : [];
        $coverSlide = collect($slides)->firstWhere('is_cover', true) ?? ($slides[0] ?? []);

        // /carousel-gen plugin v2.16+ emits cover.copy as multi-paragraph string:
        //   paragraph 1 = the sharp hook line ("AI chatbot kasih ritual.")
        //   paragraph 2 = pattern-interrupt subtitle ("Paku besi. Mazmur 91...")
        //   paragraph 3 = stat / proof point ("3 dari 5 chatbot...")
        // Old code took the entire trim() so all three bled into the hook
        // slot, then setup/insights duplicated paragraph 3. Splitting on
        // blank-line boundary lets each paragraph fill its intended caption
        // role.
        $coverParagraphs = $this->splitParagraphs(
            (string) ($coverSlide['copy_id'] ?? $coverSlide['copy'] ?? '')
        );
        $hook = $coverParagraphs[0] ?? '';

        // Subtitle: prefer plugin-authored copy_en (legacy v0.4.x bilingual
        // schema). When absent (universal /carousel-gen path), fall back to
        // the cover's second paragraph — it's authored as the pattern-
        // interrupt line and reads as a natural subtitle.
        $subtitle = trim((string) ($coverSlide['copy_en'] ?? ''));
        if ($subtitle === '' && isset($coverParagraphs[1])) {
            $subtitle = $coverParagraphs[1];
        }

        // The cover's third paragraph (stat/proof) is high-signal — surface
        // it as a stat line right under the subtitle so the hook block reads
        // hook → subtitle → stat. Skip when missing or duplicate of subtitle.
        $coverStat = $coverParagraphs[2] ?? '';
        if ($coverStat !== '' && mb_strtolower($coverStat) === mb_strtolower($subtitle)) {
            $coverStat = '';
        }

        $setup = $this->extractSetupParagraph($draft, $slides);
        $pullQuote = trim((string) ($brief['pull_quote'] ?? ''));
        // Skip insight slides that already appear in setup or hook block to
        // avoid the "Studi 2026 dalam 60 Detik" duplicate the operator hit.
        $skipFingerprints = array_filter([
            $this->captionFingerprint($setup),
            $this->captionFingerprint($hook),
            $this->captionFingerprint($coverStat),
        ]);
        $insights = $this->extractInsightsFromSlides($slides, 3, $skipFingerprints);
        $question = trim((string) ($brief['engagement_question'] ?? $brief['question'] ?? ''));
        if ($question === '') {
            $question = 'Apa yang sebenarnya terjadi di balik layar?';
        }

        $parts = [];
        if ($hook !== '') $parts[] = $hook;
        if ($subtitle !== '' && mb_strtolower($subtitle) !== mb_strtolower($hook)) {
            $parts[] = $subtitle;
        }
        if ($coverStat !== '' && mb_strtolower($coverStat) !== mb_strtolower($hook)) {
            $parts[] = $coverStat;
        }
        if ($setup !== '') $parts[] = $setup;
        if ($pullQuote !== '' && $pullQuote !== $setup && mb_stripos($setup, $pullQuote) === false) {
            $parts[] = $pullQuote;
        }
        if (!empty($insights)) {
            $bullets = implode("\n", array_map(fn($i) => "→ {$i}", $insights));
            $parts[] = "Yang nggak kelihatan dari permukaan:\n{$bullets}";
        }
        $parts[] = "{$question}\n\nSwipe → untuk breakdown lengkap.";
        $parts[] = 'Full article: link in comments ↓';

        $caption = implode("\n\n", $parts);

        // Cap at 1900 chars (LinkedIn engagement sweet spot per 2026 data).
        if (mb_strlen($caption) > 1900) {
            $caption = mb_substr($caption, 0, 1897) . '...';
        }

        return $caption;
    }

    /**
     * Split a /carousel-gen slide copy block on blank-line boundary into
     * trimmed paragraphs. Plugin v2.16+ emits each slide.copy as a stack of
     * paragraphs separated by `\n\n` — the first paragraph is typically the
     * slide's headline/title and subsequent paragraphs are body copy.
     */
    private function splitParagraphs(string $copy): array
    {
        $copy = trim($copy);
        if ($copy === '') return [];
        $paragraphs = preg_split('/\n\s*\n/u', $copy) ?: [];
        $out = [];
        foreach ($paragraphs as $p) {
            $p = trim($p);
            if ($p !== '') $out[] = $p;
        }
        return $out;
    }

    /**
     * Lower-cased + alpha-only signature of a caption fragment for de-dup.
     * Lets us skip insight bullets whose first paragraph already appears in
     * setup or the cover stat line (substring check is too loose, exact
     * match too strict — fingerprint compares on normalized content).
     */
    private function captionFingerprint(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/u', '', $text) ?? '';
        // Bound at first 60 chars — enough to identify the slide's
        // headline without rejecting longer bodies that merely START
        // with the same headline.
        return mb_substr($text, 0, 60);
    }

    /**
     * Pull a setup paragraph (60-280 chars) from blog excerpt → first
     * non-cover/non-CTA slide → empty. The setup sits between the hook
     * and insight bullets to give context for why this matters.
     *
     * For /carousel-gen direct_answer slides, prefer the dedicated
     * `direct_answer_block` field over `copy` — it's authored as
     * stand-alone prose suited for caption use, while `copy` carries
     * the on-slide infographic copy block.
     */
    private function extractSetupParagraph(LinkedInPost $draft, array $slides): string
    {
        $post = $draft->post;
        if ($post && $post->translations) {
            $primary = $post->translations->where('language', 'id')->first()
                ?? $post->translations->first();
            if ($primary) {
                $excerpt = trim((string) ($primary->excerpt ?? ''));
                if ($excerpt !== '' && mb_strlen($excerpt) >= 60) {
                    return mb_strlen($excerpt) > 280
                        ? mb_substr($excerpt, 0, 277) . '...'
                        : $excerpt;
                }
            }
        }

        // Fallback: first substantive non-cover/non-CTA slide
        foreach ($slides as $s) {
            if (!is_array($s)) continue;
            if (($s['is_cover'] ?? false) || ($s['is_cta'] ?? false)) continue;
            if (in_array(($s['layout_hint'] ?? ''), ['cover', 'cta'], true)) continue;

            // /carousel-gen direct_answer layout has a richer prose block
            // that reads naturally as a setup paragraph (vs the on-slide
            // `copy` which is HIGH RISK / LOW RISK column labels).
            $candidate = '';
            if (($s['layout_hint'] ?? '') === 'direct_answer') {
                $candidate = trim((string) ($s['direct_answer_block'] ?? ''));
            }
            if ($candidate === '') {
                // Fall back: first 1-2 paragraphs of slide.copy joined by space.
                $paragraphs = $this->splitParagraphs(
                    (string) ($s['copy_id'] ?? $s['copy'] ?? '')
                );
                $candidate = trim(implode(' ', array_slice($paragraphs, 0, 2)));
            }
            if ($candidate !== '' && mb_strlen($candidate) >= 60) {
                return mb_strlen($candidate) > 280
                    ? mb_substr($candidate, 0, 277) . '...'
                    : $candidate;
            }
        }
        return '';
    }

    /**
     * Extract up to N sharpest body slides as bullet-ready one-liners.
     *
     * /carousel-gen plugin v2.16+ emits each slide.copy as a multi-paragraph
     * block where paragraph 1 is the slide headline and subsequent
     * paragraphs are body. Take paragraph 1 as the insight (it's already
     * sharp and pre-authored as the slide title). Falls back to first
     * sentence regex only when there's no paragraph break (legacy slides
     * with single-paragraph copy).
     *
     * Skips slides whose first-paragraph fingerprint matches anything in
     * $skipFingerprints — used to dedupe against setup + cover stat lines
     * so the same headline doesn't appear twice in the same caption.
     */
    private function extractInsightsFromSlides(array $slides, int $max = 3, array $skipFingerprints = []): array
    {
        $insights = [];
        $seen = $skipFingerprints;
        foreach ($slides as $s) {
            if (count($insights) >= $max) break;
            if (!is_array($s)) continue;
            if (($s['is_cover'] ?? false) || ($s['is_cta'] ?? false)) continue;
            if (in_array(($s['layout_hint'] ?? ''), ['cover', 'cta'], true)) continue;

            $copy = (string) ($s['headline_id'] ?? $s['copy_id'] ?? $s['copy'] ?? $s['headline'] ?? '');
            if (trim($copy) === '') continue;

            $paragraphs = $this->splitParagraphs($copy);
            $line = $paragraphs[0] ?? '';

            // Defend against pathologically short first paragraphs (e.g. a
            // standalone "Q:" or single emoji) — fall through to second
            // paragraph when the headline alone is too thin to read.
            if (mb_strlen($line) < 8 && isset($paragraphs[1])) {
                $line = $paragraphs[1];
            }

            // Single-paragraph fallback: if still too long, slice on first
            // sentence terminator. Flatten newlines first so the regex
            // (whose `.` doesn't cross `\n` without `s` modifier) actually
            // matches across the line.
            if (mb_strlen($line) > 110) {
                $flat = preg_replace('/\s+/u', ' ', $line) ?? $line;
                if (preg_match('/^(.+?[.!?])(?:\s|$)/u', $flat, $m)) {
                    $line = $m[1];
                }
            }
            if (mb_strlen($line) > 110) {
                $line = mb_substr($line, 0, 107) . '...';
            }
            if ($line === '') continue;

            // Skip duplicates of setup / cover stat lines so the bullet
            // "→ Studi 2026 dalam 60 Detik" doesn't appear right after a
            // setup paragraph that already opened with the same headline.
            $fp = $this->captionFingerprint($line);
            if ($fp !== '' && in_array($fp, $seen, true)) continue;
            $seen[] = $fp;

            $insights[] = $line;
        }
        return $insights;
    }

    /**
     * Resolve hashtags. Plugin output > brief fallback > synthesized from
     * blog meta_keywords. Always returns 3-5 tags (LinkedIn validator rule)
     * — guarantees brand handles #alisadikinma + #aigeneralist appear on
     * every post (drops the last non-mandatory tag if at the 5-cap).
     */
    private function resolveHashtags(?array $pluginHashtags, ?array $briefHashtags, LinkedInPost $draft): array
    {
        $tags = is_array($pluginHashtags) && count($pluginHashtags) > 0
            ? $pluginHashtags
            : (is_array($briefHashtags) ? $briefHashtags : []);

        if (count($tags) === 0) {
            $tags = $this->synthesizeHashtagsFromBlog($draft);
        }

        // Normalize: ensure each starts with #, no spaces, dedupe (case-insensitive)
        $normalized = [];
        $seen = [];
        foreach ($tags as $raw) {
            if (!is_string($raw)) continue;
            $tag = trim($raw);
            if ($tag === '') continue;
            $tag = str_replace(' ', '', $tag);
            if ($tag[0] !== '#') $tag = '#' . $tag;
            $key = mb_strtolower($tag);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $normalized[] = $tag;
            if (count($normalized) >= 5) break;
        }

        // Mandatory brand handles — ALWAYS present. If at 5-cap, drop the
        // last non-mandatory tag to make room. Order: append at the tail so
        // they render last in the post (LinkedIn convention: branded handles
        // close the hashtag stack).
        $mandatory = ['#alisadikinma', '#aigeneralist'];
        $mandatoryKeys = array_map('mb_strtolower', $mandatory);
        foreach ($mandatory as $brand) {
            $key = mb_strtolower($brand);
            if (isset($seen[$key])) continue;
            if (count($normalized) >= 5) {
                // Walk backwards looking for the last non-mandatory tag to evict.
                for ($i = count($normalized) - 1; $i >= 0; $i--) {
                    if (!in_array(mb_strtolower($normalized[$i]), $mandatoryKeys, true)) {
                        unset($seen[mb_strtolower($normalized[$i])]);
                        array_splice($normalized, $i, 1);
                        break;
                    }
                }
            }
            $normalized[] = $brand;
            $seen[$key] = true;
        }

        // Pad with industry defaults if still below the 3-tag minimum
        $defaults = ['#AI', '#Engineering', '#TechIndonesia'];
        foreach ($defaults as $d) {
            if (count($normalized) >= 3) break;
            $key = mb_strtolower($d);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $normalized[] = $d;
            }
        }
        return $normalized;
    }

    /**
     * Build hashtags from the source blog post's meta_keywords + tags.
     * Caps at 5, strips non-alphanumerics, prepends #.
     */
    private function synthesizeHashtagsFromBlog(LinkedInPost $draft): array
    {
        $post = $draft->post;
        if (!$post) return [];

        // Prefer the primary translation's meta_keywords (comma-separated).
        $translations = $post->translations ?? collect();
        $primary = $translations->where('language', 'id')->first()
            ?? $translations->first();
        $keywords = (string) ($primary->meta_keywords ?? '');

        $candidates = [];
        if ($keywords !== '') {
            foreach (explode(',', $keywords) as $kw) {
                $candidates[] = trim($kw);
            }
        }

        // Also include post `tags` column if it exists (JSON or comma string).
        $tags = $post->tags ?? null;
        if (is_array($tags)) {
            $candidates = array_merge($candidates, $tags);
        } elseif (is_string($tags) && $tags !== '') {
            foreach (explode(',', $tags) as $t) {
                $candidates[] = trim($t);
            }
        }

        $hashtags = [];
        foreach ($candidates as $c) {
            if (!is_string($c) || trim($c) === '') continue;
            // Strip non-alphanumerics (keep letters/digits/spaces only).
            $clean = preg_replace('/[^a-zA-Z0-9\s]/u', '', $c) ?? '';
            $clean = trim($clean);
            if ($clean === '') continue;

            // Preserve all-caps acronyms (AI, GPT, IPO, NASA, SaaS-style mixed
            // case). Default ucwords(strtolower()) destroys these — "AI" was
            // ending up as "#Ai", "GPT" as "#Gpt", which looks amateurish on
            // LinkedIn. Rule: when the candidate is a SHORT (≤6 char) single
            // word with at least one uppercase letter, keep its original
            // casing; for everything else, fall back to TitleCase.
            $words = preg_split('/\s+/u', $clean) ?: [];
            $hasMixedCase = preg_match('/[A-Z]/u', $clean) === 1;
            if (count($words) === 1 && mb_strlen($clean) <= 6 && $hasMixedCase) {
                $tag = $clean;
            } else {
                $tag = str_replace(' ', '', ucwords(mb_strtolower($clean)));
            }
            if ($tag === '' || strlen($tag) < 2) continue;
            $hashtags[] = '#' . $tag;
            if (count($hashtags) >= 5) break;
        }
        return $hashtags;
    }
}
