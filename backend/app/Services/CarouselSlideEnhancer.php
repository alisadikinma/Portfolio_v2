<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Stamps consistent brand chrome onto every LinkedIn carousel slide image_prompt
 * before dispatch to GeminiGen / Nano Banana Pro.
 *
 * Mirror of CoverBrandingEnhancer for the LinkedIn carousel pipeline. Plugin
 * authors the visual hook + cinematography + bilingual headline copy; this
 * service injects:
 *
 *   1. Creator face URL into face_refs (cover, human_fingerprint, cta, body
 *      with humans) — backend resolves from Setting{group=about,key=profile_photo}
 *   2. Brand logo URL into file_urls — backend resolves from
 *      Setting{group=creator_brand,key=creator_brand_logo}
 *   3. Brand chrome instruction string appended to prompt_text — page number,
 *      brand icon center thirty percent opacity, @handle watermark center
 *      thirty percent opacity, SWIPE (GESER) > on non-CTA slides, social block
 *      on CTA slide
 *   4. Placeholder token replacement: {{CREATOR_FACE}}, {{BRAND_LOGO}},
 *      {{HANDLE}}, {{PORTFOLIO_URL}}, {{PAGE_INDICATOR}}, {{SWIPE_TEXT}}
 *
 * The plugin's image_prompt body should be cinematic prose only — this
 * enhancer is the SINGLE source of truth for brand chrome literals. To change
 * brand handle, opacity, swipe text, or social URLs, edit settings (or the
 * constants below) — never the plugin.
 */
class CarouselSlideEnhancer
{
    /**
     * Layout hints that require the creator's face on the rendered slide.
     * 'body' is conditional (depends on whether scene has human figures) —
     * the plugin signals via prompt body content, so we don't auto-inject
     * for body slides; if a body slide needs the creator, the plugin should
     * use the {{CREATOR_FACE}} placeholder explicitly.
     */
    private const FACE_REQUIRED_LAYOUTS = ['cover', 'human_fingerprint', 'cta'];

    /**
     * Prepare a single slide for GeminiGen dispatch.
     *
     * @param array $slide Plugin-authored slide row from carousel_slides[]:
     *                     { slide_number, layout_hint, copy, image_prompt,
     *                       is_cover, is_cta, direct_answer_block? }
     * @param int $slideIndex 0-based index into carousel_slides[]
     * @param int $totalSlides Total slide count for the carousel
     *
     * @return array {
     *   prompt_text: string,    // Final prompt with chrome appended + placeholders replaced
     *   face_refs: string[],    // URLs to push into GeminiGen face_refs param
     *   file_urls: string[],    // URLs to push into GeminiGen file_urls param (brand logo)
     *   layout_hint: string,
     * }
     */
    public function enhance(array $slide, int $slideIndex, int $totalSlides): array
    {
        $layoutHint = (string) ($slide['layout_hint'] ?? 'body');
        $isCta = (bool) ($slide['is_cta'] ?? false);
        $rawPrompt = (string) ($slide['image_prompt'] ?? '');

        $handle = $this->resolveHandle();
        $portfolioUrl = $this->resolvePortfolioUrl();
        $pageIndicator = ($slideIndex + 1) . '/' . $totalSlides;
        $swipeText = $isCta ? '' : 'SWIPE (GESER) >';
        $opacityWord = 'thirty percent opacity';

        // 1. Resolve reference URLs
        $creatorFaceUrl = $this->getCreatorFaceUrl();
        $brandLogoUrl = $this->getCreatorBrandLogoUrl();

        // 2. Replace placeholder tokens in plugin-authored prompt
        $promptText = $this->replacePlaceholders($rawPrompt, [
            '{{CREATOR_FACE}}' => 'the provided creator face reference image',
            '{{BRAND_LOGO}}' => 'the provided brand logo reference image',
            '{{HANDLE}}' => $handle,
            '{{PORTFOLIO_URL}}' => $portfolioUrl,
            '{{PAGE_INDICATOR}}' => $pageIndicator,
            '{{SWIPE_TEXT}}' => $swipeText,
        ]);

        // 3. Append brand chrome instruction (idempotent — skip when plugin
        //    already baked the literal markers in)
        $promptText = $this->appendBrandChrome(
            $promptText,
            $isCta,
            $handle,
            $portfolioUrl,
            $pageIndicator,
            $swipeText,
            $opacityWord
        );

        // 4. Build face_refs: creator face for required layouts
        $faceRefs = [];
        $needsFace = in_array($layoutHint, self::FACE_REQUIRED_LAYOUTS, true);
        if ($needsFace && $creatorFaceUrl !== null) {
            $faceRefs[] = $creatorFaceUrl;
        } elseif ($needsFace && $creatorFaceUrl === null) {
            Log::warning('[CarouselSlideEnhancer] face required but creator face URL unresolvable', [
                'layout_hint' => $layoutHint,
                'slide_index' => $slideIndex,
            ]);
        }

        // 5. Build file_urls: brand logo (always — chrome on every slide)
        $fileUrls = [];
        if ($brandLogoUrl !== null) {
            $fileUrls[] = $brandLogoUrl;
        }

        return [
            'prompt_text' => $promptText,
            'face_refs' => $faceRefs,
            'file_urls' => $fileUrls,
            'layout_hint' => $layoutHint,
        ];
    }

    private function replacePlaceholders(string $body, array $map): string
    {
        return strtr($body, $map);
    }

    /**
     * Append the brand chrome instruction paragraph after the plugin's prose.
     * Idempotent — if the prompt already contains the @handle literal, we
     * assume the plugin baked chrome in and leave it alone.
     */
    private function appendBrandChrome(
        string $body,
        bool $isCta,
        string $handle,
        string $portfolioUrl,
        string $pageIndicator,
        string $swipeText,
        string $opacityWord
    ): string {
        // Heuristic idempotency: skip when both the page indicator and handle
        // already appear in the prompt body. Plugin authors that follow §07
        // §13 will bake these in directly; we only append when missing.
        $hasPageIndicator = str_contains($body, $pageIndicator);
        $hasHandle = str_contains($body, $handle);
        if ($hasPageIndicator && $hasHandle) {
            return $body;
        }

        $chrome = "\n\nBrand chrome (rendered as in-image typography):\n";
        $chrome .= "Top-left corner of the canvas, render the page indicator \"{$pageIndicator}\" in small white text positioned roughly seventy-five pixels from the top edge and seventy-five pixels from the left edge.\n";
        $chrome .= "Centered horizontally and vertically as a small circular badge, render the brand icon from the provided brand logo reference image at {$opacityWord} — use the exact icon from the file, do not generate a new logo.\n";
        $chrome .= "Centered horizontally directly below the brand icon, render the watermark text \"{$handle}\" in white at {$opacityWord}, subtle background mark only, never competing with the headline.\n";

        if (! $isCta && $swipeText !== '') {
            $chrome .= "Bottom center of the composition, beneath the headline text with minimal gap and never crammed against the very bottom of the canvas, render the literal text \"{$swipeText}\" in small white typography.\n";
        }

        if ($isCta) {
            $chrome .= "Lower third of the canvas centered horizontally, render three small social media icons (Instagram logo, TikTok logo, LinkedIn logo) arranged in a single horizontal row with the literal text \"{$handle}\" in white beside the icons row. Below the icons row, render the literal text \"{$portfolioUrl}\" in white at slightly smaller size.\n";
        }

        return rtrim($body) . $chrome;
    }

    private function resolveHandle(): string
    {
        $value = Setting::where('group', 'linkedin')
            ->where('key', 'creator_handle')
            ->value('value');

        if (is_string($value) && trim($value) !== '') {
            return $this->normaliseHandle($value);
        }

        // Fallback: derive from creator_brand_slug, prefixed with @
        $slug = Setting::where('group', 'creator_brand')
            ->where('key', 'creator_brand_slug')
            ->value('value');

        if (is_string($slug) && trim($slug) !== '') {
            return $this->normaliseHandle($slug);
        }

        return '@alisadikinma';
    }

    private function normaliseHandle(string $handle): string
    {
        $trimmed = trim($handle);
        if ($trimmed === '') {
            return '@alisadikinma';
        }
        return str_starts_with($trimmed, '@') ? $trimmed : '@' . ltrim($trimmed, '@');
    }

    private function resolvePortfolioUrl(): string
    {
        $value = Setting::where('group', 'about')
            ->where('key', 'website_url')
            ->value('value');

        if (is_string($value) && trim($value) !== '') {
            return rtrim(trim($value), '/');
        }

        return rtrim((string) config('app.url', 'https://alisadikinma.com'), '/');
    }

    /**
     * Resolve the creator's profile photo URL from settings.
     * Mirrors CoverBrandingEnhancer::getCreatorFaceUrl — kept duplicated
     * rather than DI-injected because the two enhancers serve different
     * pipelines and we want them to fail/log independently.
     */
    public function getCreatorFaceUrl(): ?string
    {
        $value = Setting::where('group', 'about')
            ->where('key', 'profile_photo')
            ->value('value');

        if (empty($value) || ! is_string($value)) {
            Log::warning('[CarouselSlideEnhancer] profile_photo setting missing');
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        $relative = ltrim($value, '/');
        $relative = preg_replace('#^storage/#', '', $relative);

        if (file_exists(public_path($relative))) {
            return url('/' . $relative);
        }
        if (Storage::disk('public')->exists($relative)) {
            return url('/storage/' . $relative);
        }

        Log::warning('[CarouselSlideEnhancer] profile_photo file not found on disk', ['path' => $relative]);
        return null;
    }

    public function getCreatorBrandLogoUrl(): ?string
    {
        $value = Setting::where('group', 'creator_brand')
            ->where('key', 'creator_brand_logo')
            ->value('value');

        if (empty($value) || ! is_string($value)) {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        $relative = ltrim($value, '/');
        $relative = preg_replace('#^storage/#', '', $relative);

        if (file_exists(public_path($relative))) {
            return url('/' . $relative);
        }
        if (Storage::disk('public')->exists($relative)) {
            return url('/storage/' . $relative);
        }

        Log::warning('[CarouselSlideEnhancer] creator_brand_logo file not found on disk', ['path' => $relative]);
        return null;
    }
}
