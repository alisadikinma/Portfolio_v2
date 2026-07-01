<?php

namespace App\Services;

use App\Exceptions\GeminiGenClientException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * Single transport to the indusiagen-api-client CLI (the SSOT for the
 * GeminiGen/snapgen wire protocol). The backend shells the client's `image` /
 * `video` subcommand to SUBMIT a generation; the Python client owns endpoint
 * routing, per-model field/ref mapping, and the local-vs-URL ref bug fix.
 *
 * Scope note (execution deviation 2026-07-01): the client's `check <uuid>`
 * subcommand is an unimplemented stub in the deployed version, and a history
 * poll is a model-agnostic `GET /history/{uuid}` with no drift risk, so
 * poll-check stays in the backend crons (already on config('geminigen.base_url')).
 * This bridge is SUBMIT-only.
 *
 * Security:
 *  - `local` driver runs an argv ARRAY through Illuminate Process — no shell,
 *    so prompt/ref text can never be interpreted.
 *  - `ssh` driver escapeshellarg()s every token into the remote command string.
 *  - The API key is NEVER passed on argv (visible in `ps`); the client resolves
 *    it from its own .env / config on the VPS.
 */
class GeminiGenClientBridge
{
    private const IMAGE_MODULE = 'scripts.geminigen_image';
    private const VIDEO_MODULE = 'scripts.geminigen_video';

    /** Models that reject https URL refs — their refs must be local files. */
    private const LOCAL_REF_ONLY_MODELS = ['gpt-image-2'];

    /** The client's strict `--aspect` Choice enum (argparse exit 2 on anything else). */
    private const VALID_ASPECTS = ['1:1', '16:9', '9:16', '4:3', '3:4', '21:9', '3:2', '2:3'];

    /**
     * Non-enum ratios the old HTTP path silently accepted (the raw API mapped
     * 4:5 → 16:9). The CLI is strict, so remap to the nearest valid ratio that
     * preserves orientation — blog inline images author 4:5, closest portrait
     * is 3:4. Unlisted ratios fall through to "omit the flag" (client default).
     */
    private const ASPECT_REMAP = ['4:5' => '3:4', '5:4' => '4:3'];

    /** The client's strict `--style` Choice enum (argparse exit 2 on anything else). */
    private const VALID_STYLES = [
        'None', '3D Render', 'Acrylic', 'Anime General', 'Creative', 'Dynamic',
        'Fashion', 'Game Concept', 'Graphic Design 3D', 'Illustration',
        'Photorealistic', 'Portrait', 'Portrait Cinematic', 'Portrait Fashion',
        'Ray Traced', 'Stock Photo', 'Watercolor',
    ];

    /**
     * Non-enum styles the old HTTP path accepted loosely. Blog segments author
     * "Cinematic" (photoreal cinematic scene) → nearest enum is Photorealistic.
     * Matched case-insensitively; unlisted non-enum styles drop the flag.
     */
    private const STYLE_REMAP = ['cinematic' => 'Photorealistic'];

    /**
     * Submit a generation and return the job uuid (does NOT wait for render).
     *
     * @param  string  $endpoint  e.g. 'generate_image', 'imagen/gpt-image-2', 'video-gen/veo', 'video-gen/grok'
     * @param  array<string,mixed>  $fields  prompt/aspect_ratio/style/resolution/mode/duration/mode_image
     * @param  list<string>  $refs  reference image URLs or local paths
     *
     * @throws GeminiGenClientException on CLI failure / no uuid
     */
    public function submit(string $endpoint, array $fields, array $refs, string $model): string
    {
        $tempFiles = [];

        try {
            [$module, $args] = $this->buildArgs($endpoint, $fields, $refs, $model, $tempFiles);
            $result = $this->exec($module, $args);
        } finally {
            foreach ($tempFiles as $tmp) {
                @unlink($tmp);
            }
        }

        if (! $result['ok']) {
            throw new GeminiGenClientException(
                "indusia submit failed (exit {$result['exit']}): ".
                trim($result['stderr'] ?: $result['stdout'])
            );
        }

        $uuid = $this->parseUuid($result['stdout']);
        if ($uuid === null) {
            throw new GeminiGenClientException(
                'indusia submit: no uuid in output: '.mb_substr(trim($result['stdout']), 0, 300)
            );
        }

        return $uuid;
    }

    /**
     * Build [module, argv-tail]. `local` prepends [python, -m, module]; `ssh`
     * rebuilds the tail into an escaped remote string. Returned tail never
     * includes the interpreter so both drivers share it.
     *
     * @param  list<string>  $tempFiles  filled with temp files to clean up
     * @return array{0:string,1:list<string>}
     */
    private function buildArgs(string $endpoint, array $fields, array $refs, string $model, array &$tempFiles): array
    {
        $isVideo = str_starts_with($endpoint, 'video-gen/');
        $module = $isVideo ? self::VIDEO_MODULE : self::IMAGE_MODULE;
        // Image CLI = single `image` subcommand. Video CLI = per-family
        // subcommand (veo|grok|seedance|kling) = the path tail of the endpoint.
        $subcommand = $isVideo ? substr($endpoint, strlen('video-gen/')) : 'image';

        $args = [$subcommand, (string) ($fields['prompt'] ?? ''), '--model', $model];

        $aspect = $this->normalizeAspect((string) ($fields['aspect_ratio'] ?? ''));
        if ($aspect !== null) {
            $args[] = '--aspect';
            $args[] = $aspect;
        }

        if ($isVideo) {
            // geminigen_video.py: --resolution, --duration, and --mode (which the
            // CLI maps to the mode_image wire field: frame/ingredient for veo,
            // custom for grok).
            foreach (['resolution' => '--resolution', 'duration' => '--duration', 'mode_image' => '--mode'] as $key => $flag) {
                if (! empty($fields[$key])) {
                    $args[] = $flag;
                    $args[] = (string) $fields[$key];
                }
            }
        } else {
            $style = $this->normalizeStyle((string) ($fields['style'] ?? ''));
            if ($style !== null) {
                $args[] = '--style';
                $args[] = $style;
            }
            // gpt-image-2 uses quality `mode` instead of style; harmless on others.
            if ($model === 'gpt-image-2' && ! empty($fields['mode'])) {
                $args[] = '--mode';
                $args[] = (string) $fields['mode'];
            }
            if (! empty($fields['resolution'])) {
                $args[] = '--resolution';
                $args[] = (string) $fields['resolution'];
            }
        }

        $localOnly = in_array($model, self::LOCAL_REF_ONLY_MODELS, true);
        foreach ($refs as $ref) {
            $ref = (string) $ref;
            if ($ref === '') {
                continue;
            }
            // gpt-image-2 rejects URL refs — materialize to a local temp file.
            if ($localOnly && str_starts_with($ref, 'http')) {
                $local = $this->materializeRef($ref);
                if ($local === null) {
                    continue; // fail-soft: drop an unfetchable ref rather than break submit
                }
                $tempFiles[] = $local;
                $ref = $local;
            }
            $args[] = '--ref';
            $args[] = $ref;
        }

        $args[] = '--no-wait';

        return [$module, $args];
    }

    /**
     * Run the client for the given module + arg tail via the configured driver.
     *
     * @param  list<string>  $args
     * @return array{ok:bool,exit:int,stdout:string,stderr:string}
     */
    private function exec(string $module, array $args): array
    {
        $python = (string) config('geminigen.client_path');
        $repo = (string) config('geminigen.client_repo');
        $timeout = (int) config('geminigen.client_timeout', 60);

        if (config('geminigen.client_driver') === 'ssh') {
            $host = config('geminigen.client_ssh_host');
            $user = config('geminigen.client_ssh_user');
            $key = (string) config('geminigen.client_ssh_key');

            $remoteTokens = array_merge(
                ['cd', $repo, '&&', $python, '-m', $module],
                $args
            );
            // '&&' must stay a shell operator, everything else is escaped data.
            $remote = implode(' ', array_map(
                fn ($t) => $t === '&&' ? '&&' : escapeshellarg((string) $t),
                $remoteTokens
            ));

            $command = ['ssh', '-i', $key, '-o', 'BatchMode=yes', "{$user}@{$host}", $remote];
            $result = Process::timeout($timeout)->run($command);
        } else {
            // local: argv array, NO shell — prompt/ref text is never interpreted.
            $command = array_merge([$python, '-m', $module], $args);
            $result = Process::path($repo)->timeout($timeout)->run($command);
        }

        return [
            'ok' => $result->successful(),
            'exit' => $result->exitCode() ?? 1,
            'stdout' => $result->output(),
            'stderr' => $result->errorOutput(),
        ];
    }

    /**
     * Coerce an aspect ratio to the client's strict enum. Valid → passthrough;
     * known non-enum → remapped; unknown → null (omit the flag, use the client
     * default) so a stray ratio never argparse-exit-2's the whole submit.
     */
    private function normalizeAspect(string $aspect): ?string
    {
        if ($aspect === '') {
            return null;
        }
        if (in_array($aspect, self::VALID_ASPECTS, true)) {
            return $aspect;
        }

        return self::ASPECT_REMAP[$aspect] ?? null;
    }

    /**
     * Coerce a style to the client's strict enum (case-insensitive). Valid →
     * canonical casing; known non-enum → remapped; unknown → null (omit the
     * flag) so a stray style never argparse-exit-2's the submit.
     */
    private function normalizeStyle(string $style): ?string
    {
        if ($style === '') {
            return null;
        }

        $lower = strtolower($style);
        foreach (self::VALID_STYLES as $valid) {
            if (strtolower($valid) === $lower) {
                return $valid;
            }
        }

        return self::STYLE_REMAP[$lower] ?? null;
    }

    /** Parse `submitted: uuid=<uuid> status=<n>` from CLI stdout. */
    private function parseUuid(string $stdout): ?string
    {
        if (preg_match('/uuid=([A-Za-z0-9][A-Za-z0-9_\-]*)/', $stdout, $m)) {
            return $m[1];
        }

        return null;
    }

    /** Download a URL ref to a temp file (gpt-image-2 local-ref requirement). */
    private function materializeRef(string $url): ?string
    {
        try {
            $resp = Http::timeout(20)->get($url);
            if (! $resp->successful()) {
                return null;
            }
            $ext = pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'png';
            $tmp = tempnam(sys_get_temp_dir(), 'ggref_').'.'.$ext;
            file_put_contents($tmp, $resp->body());

            return $tmp;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
