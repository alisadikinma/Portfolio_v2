<?php

namespace App\Services;

use App\Models\RepurposeVideoSlide;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * video_rebrand Phase D — render a tool slide's brand chrome (header + footer
 * PNGs) by exec'ing scripts/repurpose/video-chrome.cjs (Playwright HTML→PNG).
 * Resolves the brand logo / handle / site from the creator_brand + linkedin
 * settings groups (same sources as CarouselSlideEnhancer).
 *
 * @see docs/plans/2026-06-12-ig-video-carousel-rebrand.md
 */
class VideoChromeRenderer
{
    private string $driver;
    private string $sshHost;
    private string $sshUser;
    private string $sshKey;
    private string $nodePath;
    private string $scriptPath;
    private int $timeout;

    public function __construct()
    {
        $this->driver = (string) config('services.instagram_capture.driver', 'ssh');
        $this->sshHost = (string) config('services.instagram_capture.ssh_host', 'localhost');
        $this->sshUser = (string) config('services.instagram_capture.ssh_user', 'claudesn');
        $this->sshKey = (string) config('services.instagram_capture.ssh_key', '');
        $this->nodePath = (string) config('services.instagram_capture.node_path', 'node');
        $this->scriptPath = (string) config('services.instagram_capture.chrome_script_path', '');
        $this->timeout = 120;
    }

    /**
     * Render header.png + footer.png for one tool slide. $active = cumulative
     * lit steps (this slide's 1-based tool position), $total = total tool count.
     * Returns absolute PNG paths or null on failure.
     *
     * @return array{header: string, footer: string}|null
     */
    public function renderSlide(RepurposeVideoSlide $slide, int $active, int $total): ?array
    {
        if ($this->scriptPath === '') {
            Log::error('[VideoChromeRenderer] chrome_script_path not configured');
            return null;
        }

        $jobId = (int) $slide->repurpose_job_id;
        $dir = storage_path("app/repurpose/{$jobId}/chrome");
        @mkdir($dir, 0775, true);
        $headerOut = "{$dir}/slide_{$slide->slide_index}_header.png";
        $footerOut = "{$dir}/slide_{$slide->slide_index}_footer.png";

        $args = [
            '--title', (string) ($slide->header_title ?? ''),
            '--desc', (string) ($slide->header_desc ?? ''),
            '--subtitle', (string) ($slide->header_desc_en ?? ''),
            '--active', (string) $active,
            '--total', (string) $total,
            '--number', (string) $active,
            '--logo', $this->logoUrl(),
            '--handle', $this->handle(),
            '--site', $this->site(),
            '--header-out', $headerOut,
            '--footer-out', $footerOut,
        ];

        try {
            $ok = $this->runChrome($args);
        } catch (\Throwable $e) {
            Log::error('[VideoChromeRenderer] exec threw', ['slide' => $slide->id, 'error' => $e->getMessage()]);
            return null;
        }

        if (!$ok || !is_file($headerOut) || !is_file($footerOut)) {
            Log::error('[VideoChromeRenderer] render failed', ['slide' => $slide->id]);
            return null;
        }

        return ['header' => $headerOut, 'footer' => $footerOut];
    }

    /** @param array<int,string> $args */
    protected function runChrome(array $args): bool
    {
        if ($this->driver === 'local') {
            return Process::timeout($this->timeout)->run(array_merge([$this->nodePath, $this->scriptPath], $args))->successful();
        }
        $remoteParts = array_map('escapeshellarg', array_merge([$this->nodePath, $this->scriptPath], $args));
        $remoteCmd = 'bash -lc ' . escapeshellarg('source ~/.profile 2>/dev/null; ' . implode(' ', $remoteParts));

        return Process::timeout($this->timeout)->run($this->sshCommand($remoteCmd))->successful();
    }

    /**
     * Render the CTA ask-overlay PNG (#3) — a transparent 1080×1350 card
     * (Follow/Save/Comment, no comment→DM promise) composited over the CTA Veo
     * clip by VideoRebrandComposer::overlayCta. Returns the absolute PNG path or
     * null on failure (caller keeps the plain CTA clip — graceful degrade).
     */
    public function renderCtaOverlay(RepurposeVideoSlide $slide): ?string
    {
        if ($this->scriptPath === '') {
            Log::error('[VideoChromeRenderer] chrome_script_path not configured');
            return null;
        }

        $jobId = (int) $slide->repurpose_job_id;
        $dir = storage_path("app/repurpose/{$jobId}/chrome");
        @mkdir($dir, 0775, true);
        $out = "{$dir}/cta_overlay.png";

        $args = [
            '--mode', 'cta',
            '--handle', $this->handle(),
            '--overlay-out', $out,
        ];

        try {
            $ok = $this->runChrome($args);
        } catch (\Throwable $e) {
            Log::error('[VideoChromeRenderer] CTA overlay exec threw', ['slide' => $slide->id, 'error' => $e->getMessage()]);
            return null;
        }

        if (!$ok || !is_file($out)) {
            Log::error('[VideoChromeRenderer] CTA overlay render failed', ['slide' => $slide->id]);
            return null;
        }

        return $out;
    }

    /**
     * Render the hook TITLE overlay PNG — a transparent 1080×1350 card with the
     * cover headline (sourced from the original IG hook slide) anchored bottom-third
     * over a scrim, composited over the hook clip by VideoRebrandComposer::overlayClip.
     * Returns the absolute PNG path, or null on empty title / render failure (caller
     * keeps the plain hook clip — graceful degrade).
     */
    public function renderHookTitle(RepurposeVideoSlide $slide, string $title, ?string $logoUrl = null): ?string
    {
        $title = trim($title);
        if ($title === '' || $this->scriptPath === '') {
            return null;
        }

        $jobId = (int) $slide->repurpose_job_id;
        $dir = storage_path("app/repurpose/{$jobId}/chrome");
        @mkdir($dir, 0775, true);
        $out = "{$dir}/hook_title.png";

        $args = [
            '--mode', 'hook',
            '--title', $title,
            '--overlay-out', $out,
        ];
        // The cjs inlines the logo as a data:URI from a LOCAL path (a remote URL is
        // blocked under setContent's opaque origin). Convert the entity-ref storage
        // URL → public/storage path. Omitted cleanly when unresolved.
        $logoPath = $this->localLogoPath($logoUrl);
        if ($logoPath !== '') {
            $args[] = '--logo';
            $args[] = $logoPath;
        }

        try {
            $ok = $this->runChrome($args);
        } catch (\Throwable $e) {
            Log::error('[VideoChromeRenderer] hook title exec threw', ['slide' => $slide->id, 'error' => $e->getMessage()]);
            return null;
        }

        if (!$ok || !is_file($out)) {
            Log::error('[VideoChromeRenderer] hook title render failed', ['slide' => $slide->id]);
            return null;
        }

        return $out;
    }

    /**
     * Convert an entity-ref storage URL (…/storage/entity-refs/x.png) to its local
     * public/storage filesystem path so the cjs can inline it as a data:URI.
     * Returns '' for an empty/foreign URL.
     */
    private function localLogoPath(?string $url): string
    {
        if ($url === null || $url === '') {
            return '';
        }
        $pos = strpos($url, '/storage/');
        if ($pos === false) {
            return '';
        }

        return public_path(substr($url, $pos + 1)); // drops leading slash → "storage/…"
    }

    protected function logoUrl(): string
    {
        $rel = (string) Setting::query()->where('group', 'creator_brand')->where('key', 'creator_brand_logo')->value('value');
        if ($rel === '') {
            return '';
        }
        // The cjs inlines this as a data:URI (it strips a file:// prefix and reads
        // bytes), so a plain absolute path is enough — no browser-readable URL.
        return public_path(ltrim($rel, '/'));
    }

    protected function handle(): string
    {
        // Shared resolver (also used by the caption) — placeholder-hardened.
        return \App\Support\CreatorHandle::resolve();
    }

    private function site(): string
    {
        $tagline = (string) Setting::query()->where('group', 'creator_brand')->where('key', 'creator_brand_tagline')->value('value');

        return $tagline !== '' ? $tagline : 'alisadikinma.com';
    }

    private function sshCommand(string $remoteCommand): string
    {
        $keyOption = $this->sshKey !== '' ? '-i ' . escapeshellarg($this->sshKey) : '';
        return trim("ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 {$keyOption} {$this->sshUser}@{$this->sshHost} {$remoteCommand}");
    }
}
