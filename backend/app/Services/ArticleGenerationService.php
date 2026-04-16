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
     * Trigger finalize-stage Indonesian → English translation.
     * Uses /article-translate skill on Sonnet with refs-translate.md.
     *
     * @param int $postId Published Post ID (not content_idea_id)
     * @param string $idempotencyKey UUID to dedupe retries
     * @param string $targetLocale Target locale code (default 'en')
     * @return array{success: bool, pid: int|null, error: string|null}
     */
    public function triggerTranslate(int $postId, string $idempotencyKey, string $targetLocale = 'en'): array
    {
        $prompt = "/article-translate --post-id {$postId} --api-url {$this->apiUrl} --api-token {$this->apiToken}";
        $prompt .= " --idempotency-key {$idempotencyKey}";
        $prompt .= " --target-locale {$targetLocale}";

        $model = config('services.article_generation.model_translate', 'sonnet');
        $refsFile = config('services.article_generation.refs_translate', '');

        return $this->executePrompt($prompt, $postId, 'translate', $model, $refsFile);
    }

    /**
     * Rewrite a Visual Direction paragraph so it matches the person in a face
     * reference image. Keeps scene, setting, lighting, mood intact; only
     * updates age / gender / hair / attire / appearance to match the reference.
     *
     * Synchronous — waits for Claude CLI to return the rewritten paragraph.
     * Typical latency: ~10-20s.
     *
     * @param string $originalVd       Original Visual Direction text
     * @param string $faceRefUrl       Public URL of the face reference image
     * @param array  $segmentContext   Optional segment metadata (label, concept, style)
     * @return array{success: bool, rewritten_vd: string|null, error: string|null}
     */
    public function rewriteVisualDirectionForFace(
        string $originalVd,
        string $faceRefUrl,
        array $segmentContext = []
    ): array {
        if (trim($originalVd) === '' || trim($faceRefUrl) === '') {
            return ['success' => false, 'rewritten_vd' => null, 'error' => 'Missing original VD or face reference URL'];
        }

        $prompt = $this->buildVdRewritePrompt($originalVd, $segmentContext);
        $model = config('services.article_generation.model_vd_rewrite', 'sonnet');

        try {
            $result = $this->executeSyncPrompt($prompt, 'vd-rewrite', $model, $faceRefUrl);
        } catch (\Exception $e) {
            Log::error('[ArticleGeneration] VD rewrite failed', [
                'error' => $e->getMessage(),
                'face_ref' => $faceRefUrl,
            ]);
            return ['success' => false, 'rewritten_vd' => null, 'error' => $e->getMessage()];
        }

        if (!$result['success']) {
            return ['success' => false, 'rewritten_vd' => null, 'error' => $result['error']];
        }

        $rewritten = $this->parseVdRewriteOutput($result['output']);
        if ($rewritten === null || $rewritten === '') {
            return ['success' => false, 'rewritten_vd' => null, 'error' => 'Empty rewrite output'];
        }

        Log::info('[ArticleGeneration] VD rewritten for face ref', [
            'face_ref' => $faceRefUrl,
            'original_len' => strlen($originalVd),
            'rewritten_len' => strlen($rewritten),
        ]);

        return ['success' => true, 'rewritten_vd' => $rewritten, 'error' => null];
    }

    /**
     * Build the 1-shot prompt for Visual Direction rewrite.
     */
    private function buildVdRewritePrompt(string $originalVd, array $segmentContext): string
    {
        $contextLine = '';
        if (!empty($segmentContext['label']) || !empty($segmentContext['concept'])) {
            $label = $segmentContext['label'] ?? '';
            $concept = $segmentContext['concept'] ?? '';
            $contextLine = "Segment: {$label} — {$concept}\n";
        }

        return <<<PROMPT
You are rewriting a Visual Direction so it matches the person shown in the attached reference image.

Instructions:
- Look at the attached reference image carefully. Note the person's age, gender, ethnicity, hair, facial features, build, and attire.
- Keep the scene, setting, lighting, mood, camera angle, and composition from the original VD intact.
- Only update age, gender, hair, facial features, attire, and general appearance to match the reference person.
- Output ONLY the rewritten Visual Direction paragraph.
- No preamble, no explanation, no quotes, no markdown, no labels.

{$contextLine}Original Visual Direction:
{$originalVd}

Rewritten Visual Direction:
PROMPT;
    }

    /**
     * Parse Claude CLI output for the rewritten VD. Strips stray quotes,
     * markdown fences, leading labels, and extra whitespace.
     */
    private function parseVdRewriteOutput(string $raw): ?string
    {
        $text = trim($raw);
        if ($text === '') {
            return null;
        }

        $text = preg_replace('/^```[a-zA-Z]*\s*/', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);
        $text = preg_replace('/^(rewritten visual direction:|visual direction:|output:)\s*/i', '', $text);

        $text = trim($text);
        if (strlen($text) >= 2) {
            $first = $text[0];
            $last = substr($text, -1);
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $text = substr($text, 1, -1);
            }
        }

        return trim($text);
    }

    /**
     * Synchronous prompt execution — waits for stdout and returns it.
     * Used by VD rewrite (not the background pipeline phases).
     *
     * @return array{success: bool, output: string, error: string|null}
     */
    private function executeSyncPrompt(string $claudePrompt, string $phase, string $model = '', string $imageUrl = ''): array
    {
        $modelFlag = $model ? "--model {$model}" : '';
        $imageFlag = $imageUrl ? "--image {$imageUrl}" : '';
        $extraFlags = trim("{$modelFlag} {$imageFlag} --effort medium");

        if ($this->driver === 'local') {
            $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
            if ($isWindows) {
                $tmpDir = sys_get_temp_dir();
                $sep = DIRECTORY_SEPARATOR;
                $promptFile = "{$tmpDir}{$sep}article-{$phase}-sync-" . uniqid() . '.txt';
                file_put_contents($promptFile, $claudePrompt);
                $cmd = "& \"{$this->claudePath}\" -p (Get-Content -Raw \"{$promptFile}\") {$extraFlags} --dangerously-skip-permissions";
                $result = Process::timeout(180)->run(['powershell', '-Command', $cmd]);
                @unlink($promptFile);
            } else {
                $result = Process::timeout(180)->run([
                    'bash', '-lc',
                    "{$this->claudePath} -p " . escapeshellarg($claudePrompt) . " {$extraFlags} --dangerously-skip-permissions",
                ]);
            }
        } else {
            $promptFile = "/tmp/article-{$phase}-sync-" . uniqid() . '.txt';
            $base64Prompt = base64_encode($claudePrompt);

            $writeResult = Process::timeout(15)->run(
                $this->sshCommand("echo {$base64Prompt} | base64 -d > {$promptFile}")
            );
            if (!$writeResult->successful()) {
                return ['success' => false, 'output' => '', 'error' => 'Failed to write prompt file: ' . $writeResult->errorOutput()];
            }

            $remoteCmd = "bash -lc 'source ~/.profile 2>/dev/null; {$this->claudePath} -p \"\$(cat {$promptFile})\" {$extraFlags} --dangerously-skip-permissions; rm -f {$promptFile}'";
            $result = Process::timeout(180)->run($this->sshCommand(escapeshellarg($remoteCmd)));
        }

        if (!$result->successful()) {
            return [
                'success' => false,
                'output' => '',
                'error' => 'Sync execution failed: ' . ($result->errorOutput() ?: 'exit code ' . $result->exitCode()),
            ];
        }

        return ['success' => true, 'output' => $result->output(), 'error' => null];
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
