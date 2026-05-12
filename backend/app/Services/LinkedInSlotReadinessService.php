<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LinkedInPost;

/**
 * Determines whether a LinkedIn draft (and its cross-post siblings) is ready
 * for atomic publish at slot fire time.
 *
 * Used by `social:publish-slot` orchestrator to decide between:
 *   - All ready → publish LinkedIn + dispatch siblings same tick
 *   - Not ready → atomic postpone to next slot (max 2 postpones, then ship
 *     LinkedIn solo + mark unready siblings as manual_review)
 *
 * Readiness rules:
 *   - format=text → trivially ready (FB+TH publish independently, never
 *     block LinkedIn)
 *   - format=carousel → ALL slides image_status=done AND non-empty image_url
 *     AND every present sibling (IG/TT/TH/FB) has non-empty caption AND
 *     status NOT IN ('failed', 'cancelled')
 *
 * Output shape: ['ready' => bool, 'blockers' => string[]] where blockers
 * are operator-actionable strings like 'slide_3_pending', 'instagram_caption_empty',
 * 'tiktok_status_failed'.
 */
class LinkedInSlotReadinessService
{
    private const SIBLING_BLOCKING_STATUSES = ['failed', 'cancelled'];

    private const SIBLING_RELATIONS = [
        'instagram' => 'instagramPost',
        'tiktok' => 'tiktokPost',
        'threads' => 'threadsPost',
        // facebook intentionally excluded — FB publishes independently
        // (no link_comment, less algorithmic clustering risk). Don't gate.
    ];

    /**
     * @return array{ready: bool, blockers: string[]}
     */
    public function isReady(LinkedInPost $draft): array
    {
        if ($draft->format === 'text') {
            return ['ready' => true, 'blockers' => []];
        }

        $blockers = [];

        // Carousel: validate slides
        $slides = $draft->carousel_slides ?? [];
        if (empty($slides)) {
            $blockers[] = 'no_slides';
            return ['ready' => false, 'blockers' => $blockers];
        }

        foreach ($slides as $i => $slide) {
            $status = $slide['image_status'] ?? 'pending';
            $url = $slide['image_url'] ?? null;
            if ($status !== 'done' || empty($url)) {
                $idx = $i + 1;
                $blockers[] = "slide_{$idx}_{$status}";
            }
        }

        // Validate cross-post siblings
        foreach (self::SIBLING_RELATIONS as $platformKey => $relation) {
            $sibling = $draft->$relation;
            if ($sibling === null) {
                continue;
            }

            $caption = trim((string) ($sibling->caption ?? ''));
            if ($caption === '') {
                $blockers[] = "{$platformKey}_caption_empty";
            }

            $status = (string) ($sibling->status ?? '');
            if (in_array($status, self::SIBLING_BLOCKING_STATUSES, true)) {
                $blockers[] = "{$platformKey}_status_{$status}";
            }
        }

        return [
            'ready' => empty($blockers),
            'blockers' => $blockers,
        ];
    }
}
