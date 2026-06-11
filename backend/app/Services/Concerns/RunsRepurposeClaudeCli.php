<?php

namespace App\Services\Concerns;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Shared synchronous Claude-CLI exec for the IG repurpose AI services
 * (vision extract / research / rewrite). Mirrors
 * ArticleGenerationService::executeSyncPrompt — ssh|local toggle, empty-mcp
 * flags (anti MCP-leak), base64 prompt transport over SSH.
 *
 * Config lives under `services.repurpose.*`. Reuses the same VPS auth/key as
 * article generation by default.
 */
trait RunsRepurposeClaudeCli
{
    /**
     * @return array{success: bool, output: string, error: string|null}
     */
    protected function runRepurposeSync(string $prompt, string $phase, string $model = 'sonnet', string $refsFile = ''): array
    {
        $driver = (string) config('services.repurpose.driver', 'ssh');
        $claudePath = (string) config('services.repurpose.claude_path', 'claude');
        $timeout = (int) config('services.repurpose.timeout', 300);

        $modelFlag = $model !== '' ? "--model {$model}" : '';
        $refsFlag = $refsFile !== '' ? '--append-system-prompt-file ' . escapeshellarg($refsFile) : '';
        $mcpFlags = $this->repurposeMcpFlags();
        $extraFlags = trim("{$modelFlag} {$refsFlag} {$mcpFlags}");

        if ($driver === 'local') {
            $result = Process::timeout($timeout)->run([
                'bash', '-lc',
                "{$claudePath} -p " . escapeshellarg($prompt) . " {$extraFlags} --dangerously-skip-permissions",
            ]);
        } else {
            $promptFile = "/tmp/repurpose-{$phase}-" . uniqid() . '.txt';
            $base64Prompt = base64_encode($prompt);

            $write = Process::timeout(15)->run(
                $this->repurposeSshCommand("echo {$base64Prompt} | base64 -d > {$promptFile}")
            );
            if (!$write->successful()) {
                return ['success' => false, 'output' => '', 'error' => 'Failed to write prompt file: ' . $write->errorOutput()];
            }

            $remoteCmd = "bash -lc 'source ~/.profile 2>/dev/null; {$claudePath} -p \"\$(cat {$promptFile})\" {$extraFlags} --dangerously-skip-permissions; rm -f {$promptFile} 2>/dev/null || true'";
            $result = Process::timeout($timeout)->run($this->repurposeSshCommand(escapeshellarg($remoteCmd)));
        }

        if (!$result->successful()) {
            return [
                'success' => false,
                'output' => '',
                'error' => 'exec failed: ' . ($result->errorOutput() ?: 'exit ' . $result->exitCode()),
            ];
        }

        return ['success' => true, 'output' => $result->output(), 'error' => null];
    }

    /**
     * Run the CLI, parse the JSON object, validate required keys. On a parse/keys
     * failure WHERE EXEC SUCCEEDED, fire ONE repair attempt with a strict
     * re-prompt. Exec failures (timeout/ssh) are returned immediately — a re-run
     * would just fail the same way (the bigger CLI budget is what rescues those).
     *
     * @param string[] $requiredKeys keys that must be present & non-empty in parsed
     * @return array{success: bool, parsed: array<string,mixed>|null, output: string, error: string|null, repaired: bool}
     */
    protected function runRepurposeParsed(
        string $prompt,
        string $phase,
        array $requiredKeys,
        string $model = 'sonnet',
        string $refsFile = ''
    ): array {
        $result = $this->runRepurposeSync($prompt, $phase, $model, $refsFile);

        if (!$result['success']) {
            // Exec failure (timeout / ssh) — no repair.
            return ['success' => false, 'parsed' => null, 'output' => '', 'error' => $result['error'], 'repaired' => false];
        }

        $parsed = $this->parseJsonObject($result['output']);
        if ($this->hasRequiredKeys($parsed, $requiredKeys)) {
            return ['success' => true, 'parsed' => $parsed, 'output' => $result['output'], 'error' => null, 'repaired' => false];
        }

        // Exec OK but output unparseable / missing keys → ONE repair attempt.
        Log::warning('[Repurpose] repair retry', ['phase' => $phase, 'required' => $requiredKeys]);
        $repair = $this->runRepurposeSync($this->buildRepairPrompt($prompt, $requiredKeys), $phase . '-repair', $model, $refsFile);

        if ($repair['success']) {
            $parsed = $this->parseJsonObject($repair['output']);
            if ($this->hasRequiredKeys($parsed, $requiredKeys)) {
                return ['success' => true, 'parsed' => $parsed, 'output' => $repair['output'], 'error' => null, 'repaired' => true];
            }
        }

        return [
            'success' => false,
            'parsed' => null,
            'output' => $repair['output'] !== '' ? $repair['output'] : $result['output'],
            'error' => 'unparseable_after_repair',
            'repaired' => true,
        ];
    }

    /** @param string[] $requiredKeys */
    private function buildRepairPrompt(string $prompt, array $requiredKeys): string
    {
        $keys = implode(', ', $requiredKeys);

        return $prompt . "\n\n---\nYOUR PREVIOUS OUTPUT WAS INVALID OR INCOMPLETE JSON. Return ONLY one complete, valid JSON object now. It MUST contain these non-empty keys: {$keys}. Escape every double-quote inside string values as \\\". Output compact JSON — no markdown fences, no preamble, no trailing prose. Do NOT truncate; close every bracket.";
    }

    /**
     * @param array<string,mixed>|null $parsed
     * @param string[] $keys
     */
    private function hasRequiredKeys(?array $parsed, array $keys): bool
    {
        if ($parsed === null) {
            return false;
        }
        foreach ($keys as $key) {
            if (empty($parsed[$key])) {
                return false;
            }
        }

        return true;
    }

    private function repurposeMcpFlags(): string
    {
        $empty = (string) config('services.repurpose.empty_mcp_config', '/home/claudesn/empty-mcp.json');
        if ($empty === '') {
            return '';
        }
        return '--mcp-config ' . escapeshellarg($empty) . ' --strict-mcp-config';
    }

    private function repurposeSshCommand(string $remoteCommand): string
    {
        $host = (string) config('services.repurpose.ssh_host', 'localhost');
        $user = (string) config('services.repurpose.ssh_user', 'claudesn');
        $key = (string) config('services.repurpose.ssh_key', '');
        $keyOption = $key !== '' ? '-i ' . escapeshellarg($key) : '';
        return trim("ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 {$keyOption} {$user}@{$host} {$remoteCommand}");
    }

    /**
     * Extract the first balanced JSON object from CLI output. Tolerates leading
     * preamble narration + trailing markdown fences (lifted from the post-May-2
     * parseOrchestratorOutput hardening).
     *
     * @return array<string,mixed>|null
     */
    protected function parseJsonObject(string $raw): ?array
    {
        $text = trim($raw);
        if ($text === '') {
            return null;
        }
        $start = strpos($text, '{');
        if ($start === false) {
            return null;
        }
        $depth = 0;
        $inStr = false;
        $esc = false;
        for ($i = $start, $len = strlen($text); $i < $len; $i++) {
            $ch = $text[$i];
            if ($inStr) {
                if ($esc) {
                    $esc = false;
                } elseif ($ch === '\\') {
                    $esc = true;
                } elseif ($ch === '"') {
                    $inStr = false;
                }
                continue;
            }
            if ($ch === '"') {
                $inStr = true;
            } elseif ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    $candidate = substr($text, $start, $i - $start + 1);
                    $decoded = json_decode($candidate, true);
                    return is_array($decoded) ? $decoded : null;
                }
            }
        }
        return null;
    }
}
