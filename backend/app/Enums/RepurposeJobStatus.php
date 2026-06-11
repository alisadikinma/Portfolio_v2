<?php

namespace App\Enums;

/**
 * FSM for the Telegram → IG repurpose → carousel pipeline.
 * See docs/plans/2026-06-10-telegram-ig-repurpose-carousel.md.
 *
 * Linear forward chain; every non-terminal state can drop to `failed`;
 * `failed` can re-enter any step entrypoint for per-step retry. `received`
 * is the awaiting-mode-button-tap state (capture starts only after D9 choice).
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
    case Finalizing = 'finalizing';
    case Drafted = 'drafted';
    case Failed = 'failed';

    public const TRANSITIONS = [
        'received'    => ['capturing', 'failed'],
        'capturing'   => ['captured', 'failed'],
        'captured'    => ['extracting', 'failed'],
        'extracting'  => ['extracted', 'failed'],
        'extracted'   => ['researching', 'failed'],
        'researching' => ['researched', 'failed'],
        'researched'  => ['rewriting', 'failed'],
        'rewriting'   => ['rewritten', 'failed'],
        'rewritten'   => ['finalizing', 'failed'],
        'finalizing'  => ['drafted', 'failed'],
        'drafted'     => [],
        // Per-step retry entrypoints after a failure. These are the guard states
        // each step job accepts (CaptureInstagramPost@capturing,
        // ExtractSlideContent@captured, ResearchRepurposeClaims@extracted,
        // RewriteRepurposeContent@researched, FinalizeRepurpose@rewritten) so the
        // admin retry can resume the exact failed step rather than restart.
        'failed'      => ['capturing', 'captured', 'extracted', 'researched', 'rewritten'],
    ];

    public function canTransitionTo(self $next): bool
    {
        return in_array($next->value, self::TRANSITIONS[$this->value] ?? [], true);
    }
}
