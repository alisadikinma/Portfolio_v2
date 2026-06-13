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
 *
 * The blog mode (June 13, 2026) ALSO forks OFF `extracted` — it no longer runs
 * the low-quality internal research+rewrite. Instead finalize seeds a `draft`
 * ContentIdea from the extracted IG material and hands off to the proper Content
 * Engine article pipeline (5-gate scoring, full refs). So blog skips
 * researching/rewriting too:
 *   extracted → finalizing → drafted
 * Only carousel mode still uses researching → rewriting (the rewrite is just a
 * seed body that /carousel-gen re-authors — see FinalizeRepurpose).
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
        // carousel → researching; video_rebrand → generating_assets;
        // blog → finalizing (skips research+rewrite, hands off to Content Engine)
        'extracted'   => ['researching', 'generating_assets', 'finalizing', 'failed'],
        'researching' => ['researched', 'failed'],
        'researched'  => ['rewriting', 'failed'],
        'rewriting'   => ['rewritten', 'failed'],
        'rewritten'   => ['finalizing', 'failed'],
        // video_rebrand branch states. `extracted` is the recovery edge:
        // PollRebrandAssets::recover() bounces a stuck job back so
        // GenerateRebrandAssets' Extracted guard re-runs the failed bookends.
        'generating_assets' => ['assets_ready', 'extracted', 'failed'],
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
