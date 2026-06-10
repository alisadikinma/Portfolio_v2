<?php

namespace App\Services;

use App\Models\RepurposeJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * IG repurpose Phase B — captures a source Instagram post's carousel slides +
 * caption via the deterministic Playwright script (scripts/playwright/ig-capture.cjs)
 * and persists them to a PRIVATE per-job dir (storage/app/repurpose/{id}/).
 *
 * Mirrors the exec/driver pattern of ArticleGenerationService (ssh | local).
 * No agent loop, no MCP — a plain `node ig-capture.cjs` invocation (D2).
 *
 * @see docs/plans/2026-06-10-telegram-ig-repurpose-carousel.md
 */
class InstagramCaptureService
{
    private string $driver;
    private string $sshHost;
    private string $sshUser;
    private string $sshKey;
    private string $nodePath;
    private string $scriptPath;
    private string $storageStatePath;
    private int $timeout;

    public function __construct()
    {
        $this->driver = (string) config('services.instagram_capture.driver', 'ssh');
        $this->sshHost = (string) config('services.instagram_capture.ssh_host', 'localhost');
        $this->sshUser = (string) config('services.instagram_capture.ssh_user', 'claudesn');
        $this->sshKey = (string) config('services.instagram_capture.ssh_key', '');
        $this->nodePath = (string) config('services.instagram_capture.node_path', 'node');
        $this->scriptPath = (string) config('services.instagram_capture.script_path', '');
        $this->storageStatePath = (string) config('services.instagram_capture.storage_state_path', '');
        $this->timeout = (int) config('services.instagram_capture.timeout', 120);
    }

    /**
     * Capture the post's slides + caption into storage/app/repurpose/{job}/.
     * On success sets $job->slides_path (relative to the storage/app disk).
     *
     * @return array{success: bool, count: int, slides: array<int,string>, caption: string, error: string|null}
     */
    public function capture(RepurposeJob $job): array
    {
        $url = (string) $job->source_url;

        // SSRF re-guard server-side — never exec the script against a non-IG host
        // even if the row was tampered with after Phase-A intake.
        if (!$this->isInstagramUrl($url)) {
            return $this->fail('invalid_url_host');
        }
        if ($this->scriptPath === '') {
            return $this->fail('script_path_not_configured');
        }

        $relDir = 'repurpose/' . $job->id;
        $outDir = storage_path('app/' . $relDir);

        try {
            $result = $this->runScript($url, $outDir);
        } catch (\Throwable $e) {
            Log::error('[InstagramCapture] exec threw', ['job' => $job->id, 'error' => $e->getMessage()]);
            return $this->fail('exec_error: ' . $e->getMessage());
        }

        $parsed = $this->parseOutput($result['stdout'] ?? '');
        if ($parsed === null) {
            Log::error('[InstagramCapture] unparseable script output', [
                'job' => $job->id,
                'exit' => $result['exit'] ?? null,
                'stderr_head' => mb_substr((string) ($result['stderr'] ?? ''), 0, 500),
                'stdout_head' => mb_substr((string) ($result['stdout'] ?? ''), 0, 500),
            ]);
            return $this->fail('capture_unparseable');
        }

        if (!($parsed['ok'] ?? false) || (int) ($parsed['count'] ?? 0) < 1) {
            return $this->fail((string) ($parsed['error'] ?? 'no_slides'), (array) ($parsed['slides'] ?? []), (string) ($parsed['caption'] ?? ''));
        }

        $job->update(['slides_path' => $relDir]);

        Log::info('[InstagramCapture] captured', ['job' => $job->id, 'count' => $parsed['count']]);

        return [
            'success' => true,
            'count' => (int) $parsed['count'],
            'slides' => array_values((array) ($parsed['slides'] ?? [])),
            'caption' => (string) ($parsed['caption'] ?? ''),
            'error' => null,
        ];
    }

    /**
     * Run the Node Playwright script (ssh | local). Returns raw streams so the
     * caller parses the single JSON line. Visibility `protected` so tests can
     * subclass to inject canned output without spawning a browser.
     *
     * @return array{stdout: string, stderr: string, exit: int}
     */
    protected function runScript(string $url, string $outDir): array
    {
        $args = [
            '--url', $url,
            '--out', $outDir,
            '--timeout', (string) $this->timeout,
        ];
        if ($this->storageStatePath !== '') {
            $args[] = '--storage-state';
            $args[] = $this->storageStatePath;
        }

        // Process timeout > script timeout so the script reports its own error
        // before we hard-kill it.
        $procTimeout = $this->timeout + 30;

        if ($this->driver === 'local') {
            $cmd = array_merge([$this->nodePath, $this->scriptPath], $args);
            $result = Process::timeout($procTimeout)->run($cmd);
        } else {
            // Shell-escape every token, then run the whole thing over SSH.
            $remoteParts = array_map('escapeshellarg', array_merge([$this->nodePath, $this->scriptPath], $args));
            $remoteCmd = 'bash -lc ' . escapeshellarg('source ~/.profile 2>/dev/null; ' . implode(' ', $remoteParts));
            $result = Process::timeout($procTimeout)->run($this->sshCommand($remoteCmd));
        }

        return [
            'stdout' => $result->output(),
            'stderr' => $result->errorOutput(),
            'exit' => $result->exitCode() ?? -1,
        ];
    }

    /**
     * Parse the script's last JSON line. Tolerates leading log noise (only the
     * JSON object line is consumed).
     *
     * @return array<string,mixed>|null
     */
    private function parseOutput(string $stdout): ?array
    {
        $stdout = trim($stdout);
        if ($stdout === '') {
            return null;
        }
        $lines = preg_split('/\r?\n/', $stdout) ?: [];
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim($lines[$i]);
            if ($line === '' || $line[0] !== '{') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded) && array_key_exists('ok', $decoded)) {
                return $decoded;
            }
        }
        return null;
    }

    private function isInstagramUrl(string $url): bool
    {
        return (bool) preg_match(
            '~^https?://(?:www\.)?instagram\.com/(?:p|reel|reels|tv)/[A-Za-z0-9_-]+/?~i',
            $url
        );
    }

    private function sshCommand(string $remoteCommand): string
    {
        $keyOption = $this->sshKey !== '' ? '-i ' . escapeshellarg($this->sshKey) : '';
        return trim("ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 {$keyOption} {$this->sshUser}@{$this->sshHost} {$remoteCommand}");
    }

    /**
     * @param array<int,string> $slides
     * @return array{success: false, count: int, slides: array<int,string>, caption: string, error: string}
     */
    private function fail(string $error, array $slides = [], string $caption = ''): array
    {
        return [
            'success' => false,
            'count' => 0,
            'slides' => array_values($slides),
            'caption' => $caption,
            'error' => $error,
        ];
    }
}
