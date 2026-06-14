<?php

namespace App\Services;

use App\Models\RepurposeVideoSlide;

/**
 * video_rebrand bookend brand overlay — applies the role-specific transparent
 * card onto a finalized 4:5 bookend clip (the plain `slide_{i}.mp4` written by
 * finalizeVeoClip / composeSlide):
 *
 *   - HOOK → the cover headline (from the original IG hook, via
 *     RepurposeJob::videoHookTitle) + the auto-resolved topic brand logo.
 *   - CTA  → the single-command "Follow @… for more AI Tools" ask card.
 *
 * Shared by PollRebrandAssets (first finalize) AND ReskinBookendSlide (credit-free
 * re-skin of an already-rendered clip). Returns the new public URL, or null when
 * there's nothing to overlay / a render miss — the caller keeps the plain clip.
 */
class VideoBookendOverlayApplier
{
    public function __construct(
        private readonly VideoChromeRenderer $chrome,
        private readonly VideoRebrandComposer $composer,
    ) {
    }

    /**
     * Apply the brand overlay for this bookend's role onto its finalized clip.
     * Hook/CTA only — a tool slide returns null (it carries its own chrome).
     */
    public function apply(RepurposeVideoSlide $slide): ?string
    {
        return match ($slide->role) {
            RepurposeVideoSlide::ROLE_CTA => $this->applyCta($slide),
            RepurposeVideoSlide::ROLE_HOOK => $this->applyHookTitle($slide),
            default => null,
        };
    }

    /** Render + composite the CTA ask overlay (#3) onto the finalized CTA clip. */
    private function applyCta(RepurposeVideoSlide $slide): ?string
    {
        $overlayPng = $this->chrome->renderCtaOverlay($slide);
        if ($overlayPng === null) {
            return null;
        }

        return $this->composer->overlayCta($slide, $overlayPng);
    }

    /**
     * Render + composite the hook TITLE overlay (cover headline from the original
     * IG hook + auto-resolved topic brand logo) onto the finalized hook clip.
     */
    private function applyHookTitle(RepurposeVideoSlide $slide): ?string
    {
        $job = $slide->repurposeJob;
        $title = (string) (optional($job)->videoHookTitle() ?? '');
        if (trim($title) === '') {
            return null;
        }

        $logo = $job ? ($job->extracted['hook_brand_logo'] ?? null) : null;

        $overlayPng = $this->chrome->renderHookTitle($slide, $title, is_string($logo) ? $logo : null);
        if ($overlayPng === null) {
            return null;
        }

        return $this->composer->overlayClip($slide, $overlayPng, 'title');
    }
}
