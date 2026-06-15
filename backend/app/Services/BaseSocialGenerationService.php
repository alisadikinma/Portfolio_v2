<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

/**
 * Shared infrastructure for cross-post caption generation services
 * (Instagram, TikTok, Facebook-carousel).
 *
 * Holds the SSH/local exec wrapper + balanced-brace JSON parser used by all
 * three concrete services. Mirrors the proven pattern from
 * `LinkedInGenerationService` — see CLAUDE.md May 2 "Phase D" entry for the
 * parser-fix backstory.
 *
 * Subclasses declare:
 *   - $skillName       (e.g. 'instagram-gen')
 *   - $refsConfigKey   (e.g. 'social-cross-post.generation.refs_instagram')
 *
 * SSH key + claude path + model + timeout all read from
 * `config('social-cross-post.generation.*')` — no per-platform overrides.
 *
 * The plugin lives at https://github.com/alisadikinma/social-short-form-writer
 * and emits ONE JSON envelope to stdout per invocation. Our parser tolerates:
 *   - leading Sonnet preamble narration
 *   - trailing markdown fences or narration
 *   - the JSON wrapped in ```json ...``` fences
 */
abstract class BaseSocialGenerationService
{
    /**
     * Skill name as registered by the plugin (without leading slash).
     * Subclass MUST set this, e.g. 'instagram-gen'.
     */
    protected string $skillName;

    /**
     * Config key for the compiled refs path (passed via --append-system-prompt-file).
     * Subclass MUST set this, e.g. 'social-cross-post.generation.refs_instagram'.
     */
    protected string $refsConfigKey;

    /**
     * SSH-invoke `claude -p "/<skill> <input>"` on the VPS and capture stdout.
     *
     * @return array{success: bool, stdout: string, error: string|null}
     */
    protected function invokePlugin(array $input, int $draftId): array
    {
        $driver = (string) config('social-cross-post.generation.driver', 'ssh');
        $model = (string) config('social-cross-post.generation.model', 'sonnet');
        $timeout = (int) config('social-cross-post.generation.timeout_seconds', 300);

        $refsFlag = $this->buildRefsFlag();

        $inputJson = json_encode($input, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($inputJson === false) {
            return [
                'success' => false,
                'stdout' => '',
                'error' => 'Failed to encode input payload to JSON',
            ];
        }

        $prompt = "/{$this->skillName}\n\nINPUT:\n" . $inputJson;

        if ($driver === 'local') {
            return $this->executeLocal($prompt, $model, $refsFlag, $timeout);
        }

        return $this->executeSSH($prompt, $model, $refsFlag, $timeout, $draftId);
    }

    /**
     * Build `--append-system-prompt-file <path>` flag from $refsConfigKey.
     */
    protected function buildRefsFlag(): string
    {
        $path = (string) config($this->refsConfigKey, '');
        if ($path === '') {
            return '';
        }
        return '--append-system-prompt-file ' . escapeshellarg($path);
    }

    /**
     * `--mcp-config /home/claudesn/empty-mcp.json --strict-mcp-config` —
     * mandatory for pipeline runs to prevent MCP server boot leak (see
     * CLAUDE.md April 29 entry: 140 leaked obsidian-mcp processes consumed
     * 8.7GB RSS over 4 days before this fix landed). Pipeline runs don't
     * need MCP servers — all context flows via --append-system-prompt-file
     * + the prompt itself.
     */
    protected function buildMcpFlags(): string
    {
        $emptyConfig = (string) config(
            'social-cross-post.generation.empty_mcp_config',
            '/home/claudesn/empty-mcp.json'
        );
        if ($emptyConfig === '') {
            return '';
        }
        return '--mcp-config ' . escapeshellarg($emptyConfig) . ' --strict-mcp-config';
    }

    /**
     * Local exec path — used for XAMPP-on-Windows dev or when SSH is not
     * available. Supports both Windows (PowerShell) and POSIX (bash).
     *
     * @return array{success: bool, stdout: string, error: string|null}
     */
    protected function executeLocal(string $prompt, string $model, string $refsFlag, int $timeout): array
    {
        $claudePath = (string) config('social-cross-post.generation.claude_path', 'claude');
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $tmpDir = sys_get_temp_dir();
        $promptFile = $tmpDir . DIRECTORY_SEPARATOR . 'social-gen-' . uniqid() . '.txt';
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
                'error' => 'Local exec failed: ' . ($result->errorOutput() ?: 'exit ' . $result->exitCode()),
            ];
        }

        return ['success' => true, 'stdout' => $result->output(), 'error' => null];
    }

    /**
     * SSH exec path — production. Writes prompt to a temp file on VPS,
     * invokes claude CLI synchronously, captures stdout.
     *
     * Same `timeout` wrapper + budget shaving pattern as
     * LinkedInGenerationService::executeSSH so a hung remote claude doesn't
     * leave bash + child orphaned (verified production fix on draft #1).
     *
     * @return array{success: bool, stdout: string, error: string|null}
     */
    protected function executeSSH(string $prompt, string $model, string $refsFlag, int $timeout, int $draftId): array
    {
        $sshHost = (string) config('social-cross-post.generation.ssh_host');
        $sshUser = (string) config('social-cross-post.generation.ssh_user');
        $sshKey = (string) config('social-cross-post.generation.ssh_key');
        $claudePath = (string) config('social-cross-post.generation.claude_path', 'claude');

        $promptFile = "/tmp/social-gen-{$this->skillName}-{$draftId}-" . uniqid() . '.txt';
        $base64Prompt = base64_encode($prompt);

        $keyOpt = $sshKey ? '-i ' . escapeshellarg($sshKey) : '';
        $sshPrefix = "ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 {$keyOpt} {$sshUser}@{$sshHost} ";

        // Step A: write prompt file to VPS
        $writeCmd = $sshPrefix . escapeshellarg("echo {$base64Prompt} | base64 -d > {$promptFile}");
        $writeResult = Process::timeout(15)->run($writeCmd);
        if (!$writeResult->successful()) {
            return ['success' => false, 'stdout' => '', 'error' => 'SSH prompt write failed: ' . $writeResult->errorOutput()];
        }

        // Step B: invoke claude CLI synchronously
        $remoteTimeout = max(30, $timeout - 20);
        $mcpFlags = $this->buildMcpFlags();
        $remoteCmd = "bash -lc 'source ~/.profile 2>/dev/null; timeout --kill-after=10s {$remoteTimeout} {$claudePath} -p \"\$(cat {$promptFile})\" --model {$model} {$refsFlag} {$mcpFlags} --dangerously-skip-permissions; STATUS=\$?; rm -f {$promptFile} 2>/dev/null || true; exit \$STATUS'";
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
     * Parse plugin stdout for the first top-level JSON object.
     *
     * Tolerates Sonnet preamble narration, ```json fenced code blocks, and
     * trailing prose. Anchors on the first `{` and balanced-brace scans
     * forward to the matching `}`. String-aware (skips braces inside
     * JSON strings, handles escaped quotes).
     *
     * Public for unit-test access — same precedent as
     * LinkedInGenerationService::parseOrchestratorOutput.
     *
     * @return array<string,mixed>|null Parsed envelope, or null when no
     *                                   valid JSON found.
     */
    public function parseOrchestratorOutput(string $raw): ?array
    {
        $text = (string) $raw;
        if (trim($text) === '') {
            return null;
        }

        $start = strpos($text, '{');
        if ($start === false) {
            return null;
        }

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
     * Publish-all cascade check (May 10, 2026). When the parent LinkedIn
     * draft has `auto_approve_cross_posts=true`, promote AwaitingReview →
     * Publishing and dispatch PublishViaPubler — skipping the operator
     * review gate.
     *
     * Idempotent + non-fatal — failure to advance/dispatch is logged but
     * doesn't fail the calling generate() call (the draft has already
     * reached AwaitingReview successfully).
     *
     * @param  \Illuminate\Database\Eloquent\Model  $draft             Cross-post draft (FB/IG/TT/Threads)
     * @param  \BackedEnum                           $publishingStatus  The {Status}::Publishing enum case
     * @param  string                                $modelClass        FQCN for PublishViaPubler dispatch
     * @param  \App\Services\PipelineGuard           $guard             Same guard the caller already injected
     */
    protected function maybeCascadeToPublisher(
        \Illuminate\Database\Eloquent\Model $draft,
        \BackedEnum $publishingStatus,
        string $modelClass,
        \App\Services\PipelineGuard $guard,
    ): void {
        try {
            if (!$draft->relationLoaded('linkedinPost')) {
                $draft->loadMissing('linkedinPost');
            }
            $linkedinPost = $draft->linkedinPost;
            if ($linkedinPost === null || !$linkedinPost->shouldAutoApproveCrossPosts()) {
                return;
            }

            // PublishViaPubler expects the SHORT platform key (instagram/tiktok/
            // threads/facebook) — its loadSibling() matches those, NOT the FQCN.
            // Passing $modelClass made the job throw InvalidArgumentException at
            // runtime (caught below as "non-fatal"), so the cascade never reached
            // Publer. Derive the short name: InstagramPost → instagram.
            $platform = strtolower(str_replace('Post', '', class_basename($modelClass)));

            // Per-platform gate: don't promote/dispatch a platform whose SELECTED
            // publisher (Zernio primary / Publer fallback) has no account
            // configured — leave it at AwaitingReview.
            if (!\App\Support\PublisherResolver::isPlatformEnabled($platform)) {
                \Illuminate\Support\Facades\Log::info('[BaseSocialGen] Cascade skipped — platform not configured for its selected publisher', [
                    'platform' => $platform,
                    'publisher' => \App\Support\PublisherResolver::for($platform),
                    'draft_id' => $draft->id,
                ]);
                return;
            }

            $guard->advance(
                $draft,
                $publishingStatus,
                'auto_approve_cascade',
                ['source' => 'linkedin_publish_all_flag']
            );

            \App\Support\PublisherResolver::dispatchPublish($platform, $draft->id);

            \Illuminate\Support\Facades\Log::info('[BaseSocialGen] Cascade promoted draft to Publishing', [
                'platform' => $platform,
                'draft_id' => $draft->id,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[BaseSocialGen] Cascade promotion failed (non-fatal)', [
                'platform' => class_basename($modelClass),
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
