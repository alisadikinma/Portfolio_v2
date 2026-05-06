<?php

namespace App\Console\Commands;

use App\Models\PostingTimeRule;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * One-shot researcher for AI-driven best posting time rules.
 *
 * Per docs/plans/2026-05-06-linkedin-calendar-and-ai-time-rules.md §5.2.
 *
 * Flow:
 *   1. Validate platform (must be one of the enum values)
 *   2. Staleness gate — abort if rules younger than `staleness_days` unless --force
 *   3. Build research prompt (asks Sonnet to WebSearch 2025-2026 industry data)
 *   4. SSH/local dispatch to `claude -p` with --mcp-config empty + --strict-mcp-config
 *      (mirrors LinkedInGenerationService dispatch pattern — same SSH leak protection)
 *   5. Parse JSON via balanced-brace scanner (tolerates Sonnet preamble + ```json fences)
 *   6. Validate envelope shape (sources[] + rules[])
 *   7. Upsert via PostingTimeRule::updateOrCreate (UNIQUE constraint handles dupes)
 *   8. Print summary table
 *
 * Mocking surface: subclass + override `runResearchProcess()` to inject stdout in tests.
 */
class ResearchPostingTimeRules extends Command
{
    protected $signature = 'posting-rules:research
                            {--platform=linkedin : One of linkedin|instagram|tiktok|youtube_shorts}
                            {--audience=b2b_tech : Audience segment label}
                            {--dry-run : Parse + validate without writing DB}
                            {--force : Bypass the staleness gate}';

    protected $description = 'Research best posting times via Sonnet WebSearch and upsert into posting_time_rules.';

    private const VALID_PLATFORMS = ['linkedin', 'instagram', 'tiktok', 'youtube_shorts'];

    public function handle(): int
    {
        $platform = (string) $this->option('platform');
        $audience = (string) $this->option('audience');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (!in_array($platform, self::VALID_PLATFORMS, true)) {
            $this->error("Invalid --platform '{$platform}'. Must be one of: " . implode(', ', self::VALID_PLATFORMS));
            return self::FAILURE;
        }

        // Staleness gate
        if (!$force && !$dryRun) {
            $stalenessDays = (int) config('posting-rules.staleness_days', 90);
            $newest = PostingTimeRule::query()
                ->forPlatform($platform)
                ->forAudience($audience)
                ->orderByDesc('last_researched_at')
                ->first();
            if ($newest !== null) {
                $ageDays = (int) Carbon::parse($newest->last_researched_at)->diffInDays(Carbon::now(), true);
                if ($ageDays < $stalenessDays) {
                    $remaining = $stalenessDays - $ageDays;
                    $this->warn("Rules fresh — last researched {$ageDays} days ago. Refresh in {$remaining} more days. Use --force to override.");
                    return self::SUCCESS;
                }
            }
        }

        $prompt = $this->buildResearchPrompt($platform, $audience);
        $this->info("Dispatching research for platform={$platform} audience={$audience}...");

        try {
            $stdout = $this->runResearchProcess($prompt);
        } catch (\Throwable $e) {
            Log::error('posting-rules:research dispatch failed', [
                'platform' => $platform,
                'audience' => $audience,
                'error' => $e->getMessage(),
            ]);
            $this->error('Research dispatch failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $parsed = $this->parseResearchJson($stdout);
        if ($parsed === null) {
            $this->error('Could not parse research JSON from stdout. First 500 chars of stdout:');
            $this->line(substr($stdout, 0, 500));
            return self::FAILURE;
        }

        $sources = $parsed['sources'] ?? [];
        $rules = $parsed['rules'] ?? [];
        if (!is_array($sources) || count($sources) === 0) {
            $this->error('Research envelope missing or empty `sources[]`. At least one citation is required.');
            return self::FAILURE;
        }
        if (!is_array($rules) || count($rules) === 0) {
            $this->error('Research envelope missing or empty `rules[]`. Need at least one (day_of_week, hour, score) row.');
            return self::FAILURE;
        }

        // Build a single source line we attach to every row (top citation).
        $primarySource = is_array($sources[0]) ? $sources[0] : [];
        $sourceUrl = isset($primarySource['url']) ? (string) $primarySource['url'] : null;
        $sourceTitle = isset($primarySource['title']) ? (string) $primarySource['title'] : null;
        $combinedNotes = $this->stringifySources($sources);

        $upserted = 0;
        $skipped = 0;
        $now = Carbon::now();

        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                $skipped++;
                continue;
            }
            $dow = isset($rule['day_of_week']) ? (int) $rule['day_of_week'] : -1;
            $hour = isset($rule['hour']) ? (int) $rule['hour'] : -1;
            $score = isset($rule['score']) ? (int) $rule['score'] : -1;
            $note = isset($rule['note']) ? (string) $rule['note'] : null;

            if ($dow < 0 || $dow > 6 || $hour < 0 || $hour > 23 || $score < 0 || $score > 100) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $upserted++;
                continue;
            }

            PostingTimeRule::updateOrCreate(
                [
                    'platform' => $platform,
                    'day_of_week' => $dow,
                    'hour' => $hour,
                    'audience' => $audience,
                    'timezone' => 'Asia/Jakarta',
                ],
                [
                    'score' => $score,
                    'source_url' => $sourceUrl,
                    'source_title' => $sourceTitle,
                    'notes' => $note ?? $combinedNotes,
                    'last_researched_at' => $now,
                ]
            );
            $upserted++;
        }

        if ($dryRun) {
            $this->warn("DRY-RUN: would upsert {$upserted} rules, skip {$skipped} malformed rows. No DB write.");
        } else {
            $this->info("Upserted {$upserted} rules. Skipped {$skipped} malformed rows.");
        }

        $this->renderSummary($parsed, $platform, $audience);

        return self::SUCCESS;
    }

    /**
     * Build the research prompt sent to Sonnet. Self-contained — Sonnet's
     * built-in WebSearch tool surfaces 2025-2026 sources without any RAG file.
     */
    protected function buildResearchPrompt(string $platform, string $audience): string
    {
        $platformLabel = match ($platform) {
            'linkedin' => 'LinkedIn',
            'instagram' => 'Instagram',
            'tiktok' => 'TikTok',
            'youtube_shorts' => 'YouTube Shorts',
            default => $platform,
        };

        return <<<PROMPT
Research current 2026 best posting times for {$platformLabel} for {$audience} audience in Asia/Jakarta (Indonesia, UTC+7) timezone.

Use your WebSearch tool to find at least 3 recent (2025-2026) industry sources — Hootsuite, Buffer, Sprout Social, Hubspot, Later, SocialPilot, etc. Adjust any US/EU posting times into Asia/Jakarta local time (e.g., LinkedIn US "Tue 9am EST" becomes "Tue 9pm WIB").

Return ONLY valid JSON (no prose, no fences) matching this schema EXACTLY:

{
  "sources": [
    {"url": "https://...", "title": "...", "n_size": "300k posts (optional)"}
  ],
  "rules": [
    {"day_of_week": 0, "hour": 0, "score": 25, "note": "Sunday midnight — dead air"},
    {"day_of_week": 2, "hour": 9, "score": 95, "note": "B2B sweet spot"}
  ]
}

Rules:
- day_of_week: 0=Sunday, 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday (Carbon convention).
- hour: 0..23 in WIB.
- score: 0..100 where 100=optimal, 50=neutral, 0=actively avoid.
- Cover ALL 168 (day,hour) combinations. Use lower scores (10-30) for graveyard hours like Sat/Sun midnight-6am.
- The "note" field is short (under 80 chars) operator hint.
- Cite at minimum 3 sources in the sources[] array.
- Return strictly valid JSON. No trailing prose. No markdown fences.

Begin research now.
PROMPT;
    }

    /**
     * Run the research process via SSH or local exec. Mirror of
     * LinkedInGenerationService::executeSSH/executeLocal — same MCP empty
     * config + dangerously-skip-permissions + per-host timeout reservation.
     *
     * Override in tests to inject canned stdout.
     */
    protected function runResearchProcess(string $prompt): string
    {
        $driver = (string) config('posting-rules.driver', 'ssh');
        $model = (string) config('posting-rules.model', 'sonnet');
        $timeout = (int) config('posting-rules.timeout_seconds', 180);
        $claudePath = (string) config('posting-rules.claude_path', 'claude');

        $mcpFlags = $this->buildMcpFlags();

        if ($driver === 'local') {
            return $this->runLocal($prompt, $claudePath, $model, $mcpFlags, $timeout);
        }

        return $this->runSsh($prompt, $claudePath, $model, $mcpFlags, $timeout);
    }

    private function runLocal(string $prompt, string $claudePath, string $model, string $mcpFlags, int $timeout): string
    {
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $tmpDir = sys_get_temp_dir();
        $promptFile = $tmpDir . DIRECTORY_SEPARATOR . 'posting-rules-' . uniqid() . '.txt';
        file_put_contents($promptFile, $prompt);

        try {
            if ($isWindows) {
                $cmd = "& \"{$claudePath}\" -p (Get-Content -Raw \"{$promptFile}\") --model {$model} {$mcpFlags} --dangerously-skip-permissions";
                $result = Process::timeout($timeout)->run(['powershell', '-Command', $cmd]);
            } else {
                $result = Process::timeout($timeout)->run([
                    'bash', '-lc',
                    "{$claudePath} -p \"\$(cat " . escapeshellarg($promptFile) . ")\" --model {$model} {$mcpFlags} --dangerously-skip-permissions",
                ]);
            }
        } finally {
            @unlink($promptFile);
        }

        if (!$result->successful()) {
            throw new RuntimeException('Local exec failed: ' . ($result->errorOutput() ?: 'exit ' . $result->exitCode()));
        }

        return $result->output();
    }

    private function runSsh(string $prompt, string $claudePath, string $model, string $mcpFlags, int $timeout): string
    {
        $sshHost = (string) config('posting-rules.ssh_host');
        $sshUser = (string) config('posting-rules.ssh_user');
        $sshKey = (string) config('posting-rules.ssh_key');

        $promptFile = '/tmp/posting-rules-' . uniqid() . '.txt';
        $base64 = base64_encode($prompt);

        $keyOpt = $sshKey !== '' ? '-i ' . escapeshellarg($sshKey) : '';
        $sshPrefix = "ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 {$keyOpt} {$sshUser}@{$sshHost} ";

        $writeCmd = $sshPrefix . escapeshellarg("echo {$base64} | base64 -d > {$promptFile}");
        $writeResult = Process::timeout(15)->run($writeCmd);
        if (!$writeResult->successful()) {
            throw new RuntimeException('SSH prompt write failed: ' . $writeResult->errorOutput());
        }

        $remoteTimeout = max(30, $timeout - 20);
        $remoteCmd = "bash -lc 'source ~/.profile 2>/dev/null; timeout --kill-after=10s {$remoteTimeout} {$claudePath} -p \"\$(cat {$promptFile})\" --model {$model} {$mcpFlags} --dangerously-skip-permissions; STATUS=\$?; rm -f {$promptFile} 2>/dev/null || true; exit \$STATUS'";
        $runCmd = $sshPrefix . escapeshellarg($remoteCmd);

        $result = Process::timeout($timeout)->run($runCmd);
        if (!$result->successful()) {
            throw new RuntimeException('SSH exec failed: ' . ($result->errorOutput() ?: 'exit ' . $result->exitCode()));
        }
        return $result->output();
    }

    private function buildMcpFlags(): string
    {
        $emptyConfig = (string) config('posting-rules.empty_mcp_config', '');
        if ($emptyConfig === '') {
            return '';
        }
        return '--mcp-config ' . escapeshellarg($emptyConfig) . ' --strict-mcp-config';
    }

    /**
     * Balanced-brace JSON scanner — tolerates Sonnet preamble narration and
     * trailing markdown fences. Lifted from LinkedInGenerationService
     * (renamed; same algorithm). Returns null on parse failure.
     */
    public function parseResearchJson(string $raw): ?array
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
        $jsonStr = substr($text, $start, $end - $start + 1);
        try {
            $decoded = json_decode($jsonStr, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        return is_array($decoded) ? $decoded : null;
    }

    private function stringifySources(array $sources): string
    {
        $parts = [];
        foreach ($sources as $s) {
            if (!is_array($s)) {
                continue;
            }
            $title = (string) ($s['title'] ?? 'Untitled');
            $url = (string) ($s['url'] ?? '');
            $parts[] = $url !== '' ? "{$title} <{$url}>" : $title;
        }
        return implode(' | ', $parts);
    }

    private function renderSummary(array $parsed, string $platform, string $audience): void
    {
        $rules = $parsed['rules'] ?? [];
        usort($rules, fn ($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        $top = array_slice($rules, 0, 10);
        $bottom = array_slice($rules, -5);
        $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        $rows = [];
        foreach ($top as $r) {
            $dow = $r['day_of_week'] ?? 0;
            $rows[] = [
                $dayNames[$dow] ?? '?',
                sprintf('%02d:00', $r['hour'] ?? 0),
                $r['score'] ?? 0,
                $r['note'] ?? '',
            ];
        }
        $this->info("Top 10 slots — {$platform} / {$audience}:");
        $this->table(['Day', 'Hour WIB', 'Score', 'Note'], $rows);

        $rows = [];
        foreach ($bottom as $r) {
            $dow = $r['day_of_week'] ?? 0;
            $rows[] = [
                $dayNames[$dow] ?? '?',
                sprintf('%02d:00', $r['hour'] ?? 0),
                $r['score'] ?? 0,
                $r['note'] ?? '',
            ];
        }
        $this->warn('Avoid:');
        $this->table(['Day', 'Hour WIB', 'Score', 'Note'], $rows);

        $sources = $parsed['sources'] ?? [];
        if (count($sources) > 0) {
            $this->info('Sources cited:');
            foreach ($sources as $s) {
                if (!is_array($s)) {
                    continue;
                }
                $this->line('  - ' . ($s['title'] ?? 'Untitled') . ' <' . ($s['url'] ?? '') . '>');
            }
        }
    }
}
