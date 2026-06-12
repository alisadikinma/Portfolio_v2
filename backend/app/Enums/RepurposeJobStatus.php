<?php

namespace App\Enums;

/**
 * FSM for the Telegram → IG repurpose → carousel pipeline.
 * See docs/plans/2026-06-10-telegram-ig-repurpose-carousel.md.
 *
 * Linear forward chain; every non-terminal state can drop to `failed`;
 * `failed` can re-enter any step entrypoint for per-step retry. `received`
 * is the awaiting-mode-button-tap state (capture starts only after D9 choice).
 *
 * The video_rebrand mode (3rd mode) forks OFF `extracted` — it re-skins chrome,
 * it does NOT rewrite claims, so it skips researching/rewriting:
 *   extracted → generating_assets → assets_ready → compositing → composed → finalizing → drafted
 * See docs/plans/2026-06-12-ig-video-carousel-rebrand.md.
 */
enum RepurposeJobStatus: string
{
    case Received = 'received';
    case Capturing = 'capturing';
    case Captured = 'captured';
    case Extracting = 'extracting';
    case Extracted = 'extracted';
    case Researching = 'researching';
    case Researched = 'researched';
    case Rewriting = 'rewriting';
    case Rewritten = 'rewritten';
    // video_rebrand-only branch (Veo hook/CTA gen → ffmpeg composite)
    case GeneratingAssets = 'generating_assets';
    case AssetsReady = 'assets_ready';
    case Compositing = 'compositing';
    case Composed = 'composed';
    case Finalizing = 'finalizing';
    case Drafted = 'drafted';
    case Failed = 'failed';

    public const TRANSITIONS = [
        'received'    => ['capturing', 'failed'],
        'capturing'   => ['captured', 'failed'],
        'captured'    => ['extracting', 'failed'],
        'extracting'  => ['extracted', 'failed'],
        // blog/carousel → researching; video_rebrand → generating_assets
        'extracted'   => ['researching', 'generating_assets', 'failed'],
        'researching' => ['researched', 'failed'],
        'researched'  => ['rewriting', 'failed'],
        'rewriting'   => ['rewritten', 'failed'],
        'rewritten'   => ['finalizing', 'failed'],
        // video_rebrand branch states
        'generating_assets' => ['assets_ready', 'failed'],
        'assets_ready'      => ['compositing', 'failed'],
        'compositing'       => ['composed', 'failed'],
        'composed'          => ['finalizing', 'failed'],
        'finalizing'  => ['drafted', 'failed'],
        'drafted'     => [],
        // Per-step retry entrypoints after a failure. These are the guard states
        // each step job accepts (CaptureInstagramPost@capturing,
        // ExtractSlideContent@captured, ResearchRepurposeClaims@extracted,
        // RewriteRepurposeContent@researched, FinalizeRepurpose@rewritten) so the
        // admin retry can resume the exact failed step rather than restart.
        // video_rebrand guard states: GenerateRebrandAssets@extracted (already
        // listed), ComposeVideoCarousel@assets_ready, FinalizeRepurpose(video)@composed.
        'failed'      => ['capturing', 'captured', 'extracted', 'researched', 'rewritten', 'assets_ready', 'composed'],
    ];

    public function canTransitionTo(self $next): bool
    {
        return in_array($next->value, self::TRANSITIONS[$this->value] ?? [], true);
    }
}
