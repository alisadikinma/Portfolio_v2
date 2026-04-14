<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ArticleGenerationService
{
    private string $driver;
    private string $sshHost;
    private string $sshUser;
    private string $sshKey;
    private string $claudePath;
    private string $apiUrl;
    private string $apiToken;

    public function __construct()
    {
        $this->driver = config('services.article_generation.driver', 'ssh');
        $this->sshHost = config('services.article_generation.ssh_host', '');
        $this->sshUser = config('services.article_generation.ssh_user', 'root');
        $this->sshKey = config('services.article_generation.ssh_key', '');
        $this->claudePath = config('services.article_generation.claude_path', 'claude');
        $this->apiUrl = config('services.article_generation.api_url', '');
        $this->apiToken = config('services.article_generation.api_token', '');
    }

    /**
     * Trigger full article generation (single-skill fallback).
     * Uses /article-gen skill — all steps in one session.
     *
     * @return array{success: bool, pid: int|null, error: string|null}
     */
    public function triggerGeneration(int $ideaId, array $config = []): array
    {
        $topic = $config['topic'] ?? '';
        $languages = implode(',', $config['languages'] ?? ['en']);
        $instructions = $config['instructions'] ?? '';

        $prompt = $this->buildArticleGenPrompt($ideaId, $topic, $languages, $instructions);

        return $this->executePrompt($prompt, $ideaId, 'gen');
    }

    /**
     * Trigger article prep (Steps 1-3: research, strategy, outline).
     * Uses /article-prep skill on Sonnet with refs-prep.md.
     *
     * @return array{success: bool, pid: int|null, error: string|null}
     */
    public function triggerPrep(int $ideaId, array $config = []): array
    {
        $topic = $config['topic'] ?? '';
        $languages = implode(',', $config['languages'] ?? ['en']);
        $instructions = $config['instructions'] ?? '';
        $keyword = $config['keyword'] ?? '';

        $prompt = "/article-prep --idea-id {$ideaId} --api-url {$this->apiUrl} --api-token {$this->apiToken}";
        $prompt .= ' --topic "' . addslashes($topic) . '"';
        $prompt .= " --languages {$languages}";
        if ($keyword) {
            $prompt .= ' --keyword "' . addslashes($keyword) . '"';
        }
        if ($instructions) {
            $prompt .= ' --instructions "' . addslashes($instructions) . '"';
        }

        $model = config('services.article_generation.model_prep', 'sonnet');
        $refsFile = config('services.article_generation.refs_prep', '');

        return $this->executePrompt($prompt, $ideaId, 'prep', $model, $refsFile);
    }

    /**
     * Trigger article writing (Step 4: write + polish + images).
     * Uses /article-write skill on Opus with refs-write.md.
     *
     * @return array{success: bool, pid: int|null, error: string|null}
     */
    public function triggerWrite(int $ideaId): array
    {
        $prompt = "/article-write --idea-id {$ideaId} --api-url {$this->apiUrl} --api-token {$this->apiToken}";

        $model = config('services.article_generation.model_write', 'opus');
        $refsFile = config('services.article_generation.refs_write', '');

        return $this->executePrompt($prompt, $ideaId, 'write', $model, $refsFile);
    }

    /**
     * Trigger article scoring (Step 5: five gates + combined 100-point).
     * Uses /article-score skill on Sonnet with refs-score.md.
     *
     * @return array{success: bool, pid: int|null, error: string|null}
     */
    public function triggerScore(int $ideaId): array
    {
        $prompt = "/article-score --idea-id {$ideaId} --api-url {$this->apiUrl} --api-token {$this->apiToken}";

        $model = config('services.article_generation.model_score', 'sonnet');
        $refsFile = config('services.article_generation.refs_score', '');

        return $this->executePrompt($prompt, $ideaId, 'score', $model, $refsFile);
    }

    /**
     * Trigger Gate 2 image prompt authoring.
     * Uses /article-images skill on Sonnet with refs-images.md.
     *
     * @param int $ideaId
     * @param string $idempotencyKey UUID to dedupe retries
     * @param int[] $onlySections Outline positions to regenerate; empty = all
     * @return array{success: bool, pid: int|null, error: string|null}
     */
    public function triggerImages(int $ideaId, string $idempotencyKey, array $onlySections = []): array
    {
        $prompt = "/article-images --idea-id {$ideaId} --api-url {$this->apiUrl} --api-token {$this->apiToken}";
        $prompt .= " --idempotency-key {$idempotencyKey}";
        if (!empty($onlySections)) {
            $prompt .= ' --only-sections ' . implode(',', $onlySections);
        }

        $model = config('services.article_generation.model_images', 'sonnet');
        $refsFile = config('services.article_generation.refs_images', '');

        return $this->executePrompt($prompt, $ideaId, 'images', $model, $refsFile);
    }

    /**
     * Check if a process is still running.
     */
    public function isProcessRunning(int $pid): bool
    {
        try {
            $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

            if ($this->driver === 'local') {
                if ($isWindows) {
                    $result = Process::run(['powershell', '-Command', "Get-Process -Id {$pid} -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Id"]);
                    return trim($result->output()) === (string) $pid;
                }
                $result = Process::run("kill -0 {$pid} 2>/dev/null; echo $?");
                return trim($result->output()) === '0';
            }
            $result = Process::run($this->sshCommand("'kill -0 {$pid} 2>/dev/null; echo \$?'"));
            return trim($result->output()) === '0';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Execute a prompt via SSH or local, with optional model and refs file.
     *
     * @return array{success: bool, pid: int|null, error: string|null}
     */
    private function executePrompt(string $prompt, int $ideaId, string $phase, string $model = '', string $refsFile = ''): array
    {
        try {
            if ($this->driver === 'local') {
                return $this->executeLocal($prompt, $ideaId, $phase, $model, $refsFile);
            }
            return $this->executeSSH($prompt, $ideaId, $phase, $model, $refsFile);
        } catch (\Exception $e) {
            Log::error('[ArticleGeneration] Trigger failed', [
                'idea_id' => $ideaId,
                'phase' => $phase,
                'driver' => $this->driver,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'pid' => null, 'error' => $e->getMessage()];
        }
    }

    private function buildArticleGenPrompt(int $ideaId, string $topic, string $languages, string $instructions): string
    {
        $prompt = "/article-gen --idea-id {$ideaId} --api-url {$this->apiUrl} --api-token {$this->apiToken}";
        $prompt .= ' --topic "' . addslashes($topic) . '"';
        $prompt .= " --languages {$languages}";
        if ($instructions) {
            $prompt .= ' --instructions "' . addslashes($instructions) . '"';
        }

        return $prompt;
    }

    private function executeLocal(string $claudePrompt, int $ideaId, string $phase = 'gen', string $model = '', string $refsFile = ''): array
    {
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $tmpDir = $isWindows ? sys_get_temp_dir() : '/tmp';
        $logFile = $tmpDir . DIRECTORY_SEPARATOR . "article-{$phase}-{$ideaId}.log";

        $modelFlag = $model ? "--model {$model}" : '';
        $refsFlag = $refsFile ? "--append-system-prompt-file {$refsFile}" : '';
        $extraFlags = trim("{$modelFlag} {$refsFlag} --effort medium");

        if ($isWindows) {
            $sep = DIRECTORY_SEPARATOR;
            $promptFile = "{$tmpDir}{$sep}article-{$phase}-{$ideaId}-prompt.txt";
            $runScript = "{$tmpDir}{$sep}article-{$phase}-{$ideaId}.ps1";
            $errFile = "{$logFile}.err";

            file_put_contents($promptFile, $claudePrompt);
            file_put_contents($runScript, implode("\r\n", [
                '$prompt = (Get-Content -Raw "' . $promptFile . '").Trim()',
                '& "' . $this->claudePath . '" -p "$prompt" ' . $extraFlags . ' --dangerously-skip-permissions > "' . $logFile . '" 2> "' . $errFile . '"',
            ]));

            $psLauncher = "\$p = Start-Process -FilePath 'powershell' -ArgumentList '-ExecutionPolicy Bypass -File \"" . $runScript . "\"' -WindowStyle Hidden -PassThru; Write-Output \$p.Id";
            $result = Process::timeout(30)->run(['powershell', '-Command', $psLauncher]);
            $pid = (int) trim($result->output());
        } else {
            $pidFile = "{$tmpDir}/article-{$phase}-{$ideaId}.pid";
            $command = "nohup {$this->claudePath} -p \"{$claudePrompt}\" {$extraFlags} --dangerously-skip-permissions > {$logFile} 2>&1 & echo \$! > {$pidFile}";
            Process::run($command);
            $pid = (int) trim(file_get_contents($pidFile));
        }

        Log::info("[ArticleGeneration] Local {$phase} process started", [
            'idea_id' => $ideaId,
            'phase' => $phase,
            'pid' => $pid,
            'model' => $model ?: 'default',
        ]);

        return ['success' => true, 'pid' => $pid > 0 ? $pid : null, 'error' => null];
    }

    private function executeSSH(string $claudePrompt, int $ideaId, string $phase = 'gen', string $model = '', string $refsFile = ''): array
    {
        $promptFile = "/tmp/article-{$phase}-{$ideaId}-prompt.txt";
        $logFile = "/tmp/article-{$phase}-{$ideaId}.log";
        $pidFile = "/tmp/article-{$phase}-{$ideaId}.pid";
        $runScript = "/tmp/article-{$phase}-{$ideaId}.sh";

        // Step 0: Clean old files to avoid permission conflicts between www-data and claudesn
        Process::timeout(10)->run(
            $this->sshCommand("rm -f {$promptFile} {$logFile} {$pidFile} {$runScript} 2>/dev/null; true")
        );

        // Step 1: Write prompt to remote file
        $base64Prompt = base64_encode($claudePrompt);
        $writeResult = Process::timeout(15)->run(
            $this->sshCommand("echo {$base64Prompt} | base64 -d > {$promptFile}")
        );

        if (!$writeResult->successful()) {
            throw new \RuntimeException('Failed to write prompt file: ' . $writeResult->errorOutput());
        }

        // Step 2: Build runner script with model + refs flags
        $modelFlag = $model ? "--model {$model}" : '';
        $refsFlag = $refsFile ? "--append-system-prompt-file {$refsFile}" : '';
        $extraFlags = trim("{$modelFlag} {$refsFlag} --effort medium");

        $scriptContent = base64_encode(implode("\n", [
            '#!/bin/bash',
            'source ~/.profile 2>/dev/null',
            "prompt=\$(cat {$promptFile})",
            "nohup {$this->claudePath} -p \"\$prompt\" {$extraFlags} --dangerously-skip-permissions > {$logFile} 2>&1 &",
            "echo \$! > {$pidFile}",
        ]));
        $scriptResult = Process::timeout(15)->run(
            $this->sshCommand("echo {$scriptContent} | base64 -d > {$runScript} && chmod +x {$runScript}")
        );

        if (!$scriptResult->successful()) {
            throw new \RuntimeException('Failed to write run script: ' . $scriptResult->errorOutput());
        }

        // Step 3: Execute the script
        $result = Process::timeout(30)->run($this->sshCommand("bash -l {$runScript}"));

        if (!$result->successful()) {
            throw new \RuntimeException('SSH execution failed: ' . $result->errorOutput());
        }

        $pid = $this->readPidFile($pidFile);

        Log::info("[ArticleGeneration] SSH {$phase} process started", [
            'idea_id' => $ideaId,
            'phase' => $phase,
            'pid' => $pid,
            'model' => $model ?: 'default',
            'refs_file' => $refsFile ?: 'none',
            'host' => $this->sshHost,
        ]);

        return ['success' => true, 'pid' => $pid, 'error' => null];
    }

    private function sshCommand(string $remoteCommand): string
    {
        $keyOption = $this->sshKey ? "-i {$this->sshKey}" : '';
        return "ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 {$keyOption} {$this->sshUser}@{$this->sshHost} {$remoteCommand}";
    }

    private function readPidFile(string $pidFile): ?int
    {
        try {
            $result = Process::run($this->sshCommand("cat {$pidFile} 2>/dev/null"));
            $pid = (int) trim($result->output());
            return $pid > 0 ? $pid : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
