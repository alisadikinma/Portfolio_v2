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
     * Trigger article generation for a content idea.
     *
     * @return array{success: bool, pid: int|null, error: string|null}
     */
    public function triggerGeneration(int $ideaId, array $config = []): array
    {
        $topic = $config['topic'] ?? '';
        $languages = implode(',', $config['languages'] ?? ['en']);
        $instructions = $config['instructions'] ?? '';

        $claudePrompt = $this->buildClaudePrompt($ideaId, $topic, $languages, $instructions);

        try {
            if ($this->driver === 'local') {
                return $this->executeLocal($claudePrompt, $ideaId);
            }
            return $this->executeSSH($claudePrompt, $ideaId);
        } catch (\Exception $e) {
            Log::error('[ArticleGeneration] Trigger failed', [
                'idea_id' => $ideaId,
                'driver' => $this->driver,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'pid' => null, 'error' => $e->getMessage()];
        }
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
            $result = Process::run($this->sshCommand("kill -0 {$pid} 2>/dev/null; echo \$?"));
            return trim($result->output()) === '0';
        } catch (\Exception $e) {
            return false;
        }
    }

    private function buildClaudePrompt(int $ideaId, string $topic, string $languages, string $instructions): string
    {
        $escapedTopic = addslashes($topic);
        $escapedInstructions = addslashes($instructions);

        $prompt = "/article-gen --idea-id {$ideaId} --api-url {$this->apiUrl} --api-token {$this->apiToken}";
        $prompt .= " --topic \"{$escapedTopic}\"";
        $prompt .= " --languages {$languages}";

        if ($escapedInstructions) {
            $prompt .= " --instructions \"{$escapedInstructions}\"";
        }

        return $prompt;
    }

    private function executeLocal(string $claudePrompt, int $ideaId): array
    {
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $tmpDir = $isWindows ? sys_get_temp_dir() : '/tmp';
        $logFile = $tmpDir . DIRECTORY_SEPARATOR . "article-gen-{$ideaId}.log";

        if ($isWindows) {
            // Windows: write prompt to file, then launch PowerShell script
            // that reads it — avoids all cmd.exe escaping issues
            $sep = DIRECTORY_SEPARATOR;
            $promptFile = "{$tmpDir}{$sep}article-gen-{$ideaId}-prompt.txt";
            $runScript = "{$tmpDir}{$sep}article-gen-{$ideaId}.ps1";
            $errFile = "{$logFile}.err";

            file_put_contents($promptFile, $claudePrompt);
            file_put_contents($runScript, implode("\r\n", [
                '$prompt = (Get-Content -Raw "' . $promptFile . '").Trim()',
                '& "' . $this->claudePath . '" -p "$prompt" --dangerously-skip-permissions > "' . $logFile . '" 2> "' . $errFile . '"',
            ]));

            $psLauncher = "\$p = Start-Process -FilePath 'powershell' -ArgumentList '-ExecutionPolicy Bypass -File \"" . $runScript . "\"' -WindowStyle Hidden -PassThru; Write-Output \$p.Id";
            $result = Process::timeout(30)->run(['powershell', '-Command', $psLauncher]);
            $pid = (int) trim($result->output());
        } else {
            // Unix: nohup background process
            $pidFile = "{$tmpDir}/article-gen-{$ideaId}.pid";
            $command = "nohup {$this->claudePath} -p \"{$claudePrompt}\" --dangerously-skip-permissions > {$logFile} 2>&1 & echo \$! > {$pidFile}";
            Process::run($command);
            $pid = $this->readPidFile($pidFile, 'local');
        }

        Log::info('[ArticleGeneration] Local process started', [
            'idea_id' => $ideaId,
            'pid' => $pid,
            'os' => $isWindows ? 'windows' : 'unix',
            'log_file' => $logFile,
        ]);

        return ['success' => true, 'pid' => $pid > 0 ? $pid : null, 'error' => null];
    }

    private function executeSSH(string $claudePrompt, int $ideaId): array
    {
        $logFile = "/tmp/article-gen-{$ideaId}.log";
        $pidFile = "/tmp/article-gen-{$ideaId}.pid";

        $remoteCommand = "nohup {$this->claudePath} -p \\\"{$claudePrompt}\\\" --dangerously-skip-permissions > {$logFile} 2>&1 & echo \\\$! > {$pidFile}";
        $sshCmd = $this->sshCommand($remoteCommand);

        $result = Process::timeout(30)->run($sshCmd);

        if (!$result->successful()) {
            throw new \RuntimeException('SSH execution failed: ' . $result->errorOutput());
        }

        $pid = $this->readPidFile($pidFile, 'ssh');

        Log::info('[ArticleGeneration] SSH process started', [
            'idea_id' => $ideaId,
            'pid' => $pid,
            'host' => $this->sshHost,
        ]);

        return ['success' => true, 'pid' => $pid, 'error' => null];
    }

    private function sshCommand(string $remoteCommand): string
    {
        $keyOption = $this->sshKey ? "-i {$this->sshKey}" : '';
        return "ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 {$keyOption} {$this->sshUser}@{$this->sshHost} \"{$remoteCommand}\"";
    }

    private function readPidFile(string $pidFile, string $driver): ?int
    {
        try {
            if ($driver === 'local') {
                $result = Process::run("cat {$pidFile} 2>/dev/null");
            } else {
                $result = Process::run($this->sshCommand("cat {$pidFile} 2>/dev/null"));
            }
            $pid = (int) trim($result->output());
            return $pid > 0 ? $pid : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
