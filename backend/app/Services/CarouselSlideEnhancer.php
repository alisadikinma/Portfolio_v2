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
     * Layout hints that ALWAYS require the creator's face on the rendered
     * slide regardless of prompt content. For other layouts (body,
     * direct_answer) we additionally inspect the prompt body — if it
     * references the creator (per plugin SKILL.md the protagonist is named
     * "creator" by convention), we auto-attach face_refs too.
     *
     * Why prompt sniffing matters: the plugin's slide_role taxonomy
     * (foreshadow / problem / loop-end / b-roll-with-humans) maps onto only
     * 5 Zod layout_hint enums (cover / body / human_fingerprint /
     * direct_answer / cta), so a foreshadow slide depicting the creator is
     * routinely emitted as layout_hint='body'. Without prompt sniffing those
     * slides drop face_refs at dispatch and GeminiGen renders a generic
     * person instead of the creator's likeness.
     *
     * SKILL.md line 160 mandates creator face on:
     *   Hook + CTA + Foreshadow + Loop-end + Thumbnail
     *   + any B-Roll with human figures.
     * Sniffing for 'creator' tokens covers all those roles in one rule.
     */
    private const FACE_REQUIRED_LAYOUTS = ['cover', 'human_fingerprint', 'cta'];

    /**
     * Regex (case-insensitive, word-boundary) that flags a prompt as
     * depicting the creator. Matches plugin convention: "creator", "the
     * creator's face", "creator hands", "creator's silhouette", etc. Also
     * catches the post-replacement marker "the provided creator face
     * reference image" left by the {{CREATOR_FACE}} placeholder substitution
     * (so prompts that explicitly opted in always get the face URL).
     */
    private const CREATOR_PROMPT_REGEX = '/\b(creator(?:\'s)?|the provided creator face reference image|creator face from the)\b/i';

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
        // Phrase the opacity as a non-numeric rendering cue. Earlier "thirty
        // percent opacity" wording leaked into renders as a literal "30%" label
        // beside the brand icon — the model treated the value as caption text.
        $opacityWord = 'a faint, barely-visible strength (subtle semi-transparent background watermark, never fully opaque)';

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

        // 3. Mandate creator face on cover / human_fingerprint / CTA when the
        //    plugin's prose hasn't already requested it. Plugin-authored prompts
        //    routinely describe abstract scenes (terminals, courtrooms, neural
        //    networks) without reserving canvas space for the creator's face,
        //    which leaves GeminiGen rendering generic icons or stock figures
        //    even when face_refs is supplied. Prepending an explicit "PRIMARY
        //    SUBJECT" instruction nudges the model to treat the face reference
        //    as the hero of the composition rather than a decorative input.
        $promptText = $this->prependCreatorFaceMandate(
            $promptText,
            $layoutHint,
            $creatorFaceUrl
        );

        // 4. Append brand chrome instruction (idempotent — skip when plugin
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

        // 5. Build face_refs: creator face for required layouts + ANY slide
        //    whose prompt references the creator. This is the rule the
        //    operator enforced after slide-2 foreshadow slides shipped
        //    without face_refs and rendered generic faces (May 6, 2026):
        //    "step manapun klo ada muka creator pastikan attach reference
        //    image".
        //
        //    Detection order:
        //      a) Layout is in FACE_REQUIRED_LAYOUTS (cover/human_fingerprint/cta)
        //      b) Prompt body matches CREATOR_PROMPT_REGEX (catches body +
        //         direct_answer slides that depict the creator, including
        //         foreshadow / loop-end / B-roll-with-humans)
        $faceRefs = [];
        $layoutMandates = in_array($layoutHint, self::FACE_REQUIRED_LAYOUTS, true);
        $promptMentionsCreator = $this->promptReferencesCreator($promptText);
        $needsFace = $layoutMandates || $promptMentionsCreator;

        if ($needsFace && $creatorFaceUrl !== null) {
            $faceRefs[] = $creatorFaceUrl;
            if (! $layoutMandates && $promptMentionsCreator) {
                Log::info('[CarouselSlideEnhancer] auto-attached creator face on non-mandated layout (prompt references creator)', [
                    'layout_hint' => $layoutHint,
                    'slide_index' => $slideIndex,
                ]);
            }
        } elseif ($needsFace && $creatorFaceUrl === null) {
            Log::warning('[CarouselSlideEnhancer] face required but creator face URL unresolvable', [
                'layout_hint' => $layoutHint,
                'slide_index' => $slideIndex,
                'reason' => $layoutMandates ? 'layout_mandates' : 'prompt_references_creator',
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
     * Detect whether the (post-placeholder-replacement) prompt body refers
     * to the creator as a subject in the scene. Used to attach face_refs on
     * `body` and `direct_answer` slides that the plugin authored as creator
     * scenes (foreshadow, loop-end, B-roll with humans) but didn't tag with
     * a face-mandating layout_hint.
     */
    private function promptReferencesCreator(string $body): bool
    {
        return (bool) preg_match(self::CREATOR_PROMPT_REGEX, $body);
    }

    /**
     * Prepend an explicit "PRIMARY SUBJECT" instruction that demands the
     * creator's face from the supplied face reference image be rendered as a
     * key visual element of the slide. Layout-specific positioning so the
     * mandate fits the slide's narrative role:
     *
     *   - cover: head-and-shoulders portrait in the right or center third,
     *     visually paired with the headline (the personal-brand hook)
     *   - human_fingerprint: creator IS the human figure in the described
     *     scene, never a generic stock person
     *   - cta: small circular portrait beside the social block, signing the
     *     carousel before the swipe-end
     *
     * Idempotent — skipped when the plugin already baked a {{CREATOR_FACE}}
     * placeholder (handled by replacePlaceholders → "the provided creator
     * face reference image"), or when face_url is unresolvable. Returns the
     * body unchanged for layouts that aren't FACE_REQUIRED.
     */
    private function prependCreatorFaceMandate(string $body, string $layoutHint, ?string $faceUrl): string
    {
        if ($faceUrl === null) {
            return $body;
        }

        $layoutMandates = in_array($layoutHint, self::FACE_REQUIRED_LAYOUTS, true);
        $promptMentionsCreator = $this->promptReferencesCreator($body);

        if (! $layoutMandates && ! $promptMentionsCreator) {
            return $body;
        }

        // Idempotency guard — plugin already baked in the placeholder
        // (replacePlaceholders converted {{CREATOR_FACE}} → the literal
        // marker) or already authored the mandate prose.
        if (str_contains($body, 'the provided creator face reference image')
            || str_contains($body, "creator's face")
            || str_contains($body, 'creator face from the')) {
            return $body;
        }

        $instruction = match ($layoutHint) {
            'cover' => "PRIMARY SUBJECT (mandatory): render the creator's face from the provided face reference image as a head-and-shoulders portrait, photographic and naturally lit, prominently positioned in the right third or center of the canvas as the personal-brand hook of this cover slide. Use the exact likeness from the face reference — do not generate a generic person, an avatar, or an icon. The face should occupy approximately twenty-five to thirty-five percent of the canvas height and visually anchor the headline. The headline text and any background scene composition described below must yield canvas space to accommodate the portrait.\n\n",
            'human_fingerprint' => "PRIMARY SUBJECT (mandatory): the human figure in the scene IS the creator. Render their face from the provided face reference image, photographic and naturally lit, with their exact likeness. Do not generate a generic person, a stock model, or an avatar. The creator's face must be clearly recognizable.\n\n",
            'cta' => "SECONDARY SUBJECT (mandatory): render a small circular portrait of the creator from the provided face reference image, approximately ninety to one hundred twenty pixels in diameter, positioned in the upper-center area of the canvas above the headline. Use the exact likeness from the face reference — do not generate a generic avatar.\n\n",
            // body + direct_answer reach this branch only when the prompt
            // body itself names the creator (foreshadow, loop-end, B-roll
            // with humans). Mandate the face-reference likeness so
            // GeminiGen renders the creator instead of a generic figure.
            'body', 'direct_answer' => "PRIMARY SUBJECT (mandatory): the creator depicted in this scene must be rendered using the creator's exact likeness from the provided face reference image — photographic, naturally lit, recognizable. Do not generate a generic person, a stock model, an avatar, or an icon. The creator is the protagonist of this slide; compose the scene so their face is clearly visible and unobstructed.\n\n",
            default => '',
        };

        return $instruction === '' ? $body : ($instruction . $body);
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
        $chrome .= "Bottom-left corner of the canvas, render the watermark text \"{$handle}\" in small white typography at {$opacityWord} — a single subtle attribution mark, never competing with the headline.\n";
        $chrome .= "The brand mark (bald-with-glasses icon) appears ONCE, in the top bar only. Do NOT render a second brand-icon watermark in the center of the canvas — no duplicated face/logo in the middle of the image.\n";
        $chrome .= "CRITICAL: the opacity/transparency is a RENDERING instruction only — NEVER draw the words \"opacity\", \"transparent\", \"thirty percent\", \"30%\", or any percentage or number label anywhere in the image. The watermark is ONLY the \"{$handle}\" text — no opacity caption, no percentage figure, nothing beside it.\n";

        if (! $isCta && $swipeText !== '') {
            $chrome .= "Bottom center of the composition, beneath the headline text with minimal gap and never crammed against the very bottom of the canvas, render the literal text \"{$swipeText}\" in small white typography.\n";
        }

        if ($isCta) {
            $chrome .= "Lower third of the canvas centered horizontally, render three small social media icons (Instagram logo, TikTok logo, LinkedIn logo) arranged in a single horizontal row with the literal text \"{$handle}\" in white beside the icons row. Below the icons row, render the literal text \"{$portfolioUrl}\" in white at slightly smaller size.\n";
        }

        // Bilingual font hierarchy (hard rule). Indonesian dominates visually,
        // English is a smaller supporting subtitle. This is the single source of
        // truth for sizing — applied to every slide so plugin authors don't
        // have to repeat the rule per prompt. Specs derived from the canonical
        // reference covers (alisadikinma WW3 series).
        $chrome .= "\nBilingual headline hierarchy (HARD RULE — apply on every slide that has both languages):\n";
        $chrome .= "The Indonesian headline must be visually dominant. Render it in large bold uppercase white sans-serif typography (target ninety to one hundred ten pixels tall on a 1080x1440 canvas), tight letter-spacing, condensed weight, two to three lines maximum. Within the Indonesian headline, render two to four key emphasis words (numbers, intensifiers, named subjects) in warm amber/gold #F5A623 to draw the eye; the remaining words stay pure white.\n";
        $chrome .= "The English subtitle must be clearly smaller and secondary. Render it on a single line directly below the Indonesian headline at approximately forty percent of the Indonesian headline's font size (target thirty-five to forty-two pixels tall), in clean regular-weight white sans-serif (NOT bold, NOT italic, NOT uppercase — sentence case only), never wider than the Indonesian headline above it. The English subtitle is white — never amber — and never visually rivals the Indonesian headline.\n";
        $chrome .= "Both copies must be short, punchy, and editorial — under twelve words for the Indonesian headline and under fourteen words for the English subtitle. Do not paraphrase or pad. Tight typographic rhythm. The Indonesian headline is roughly two-and-a-half times the visual size of the English subtitle.\n";

        return rtrim($body) . $chrome;
    }

    /**
     * Resolve the LinkedIn handle to bake into slide watermarks.
     *
     * Priority order:
     *   1. Explicit `linkedin.creator_handle` setting (operator override, not
     *      yet exposed in admin UI but supported)
     *   2. Derived from `creator_brand_tagline` — strip TLD (.com / .id /
     *      .me / etc.), prefix @. Tagline is the canonical brand identity
     *      configured in the About admin page (e.g. "alisadikinma.com" →
     *      "@alisadikinma")
     *   3. Hardcoded fallback `@alisadikinma`
     *
     * NOTE: `creator_brand_slug` is NOT used here — it's the filename slug
     * (often kebab-case like "creator-brand") which produces an incorrect
     * `@creator-brand` watermark. We deliberately fall through past it.
     */
    protected function resolveHandle(): string
    {
        $value = Setting::where('group', 'linkedin')
            ->where('key', 'creator_handle')
            ->value('value');

        if (is_string($value) && trim($value) !== '') {
            return $this->normaliseHandle($value);
        }

        $tagline = Setting::where('group', 'creator_brand')
            ->where('key', 'creator_brand_tagline')
            ->value('value');

        if (is_string($tagline) && trim($tagline) !== '') {
            return $this->normaliseHandle($this->stripDomainSuffix($tagline));
        }

        return '@alisadikinma';
    }

    /**
     * Strip a TLD from a domain string so it can be used as a social handle.
     *
     * Examples:
     *   alisadikinma.com  → alisadikinma
     *   alisadikinma.co.id → alisadikinma
     *   www.alisadikinma.com → alisadikinma
     *   @alisadikinma     → alisadikinma   (already a handle, just normalize)
     */
    private function stripDomainSuffix(string $tagline): string
    {
        $trimmed = trim($tagline);
        $trimmed = ltrim($trimmed, '@');
        $trimmed = preg_replace('#^https?://#i', '', $trimmed);
        $trimmed = preg_replace('/^www\./i', '', (string) $trimmed);
        // Strip everything from the first dot onward (covers .com / .co.id /
        // .me / .io etc.)
        $trimmed = preg_replace('/\..+$/', '', (string) $trimmed);
        return (string) $trimmed;
    }

    private function normaliseHandle(string $handle): string
    {
        $trimmed = trim($handle);
        if ($trimmed === '') {
            return '@alisadikinma';
        }
        return str_starts_with($trimmed, '@') ? $trimmed : '@' . ltrim($trimmed, '@');
    }

    /**
     * Resolve portfolio URL for the CTA slide social block.
     *
     * Priority:
     *   1. Explicit `about.website_url` setting
     *   2. Derived from `creator_brand_tagline` (the bare domain stored in
     *      About admin) — prepend https:// if missing
     *   3. Laravel app.url config
     */
    protected function resolvePortfolioUrl(): string
    {
        $value = Setting::where('group', 'about')
            ->where('key', 'website_url')
            ->value('value');

        if (is_string($value) && trim($value) !== '') {
            return rtrim(trim($value), '/');
        }

        $tagline = Setting::where('group', 'creator_brand')
            ->where('key', 'creator_brand_tagline')
            ->value('value');

        if (is_string($tagline) && trim($tagline) !== '') {
            $clean = trim($tagline);
            if (! preg_match('#^https?://#i', $clean)) {
                $clean = 'https://' . preg_replace('/^www\./i', '', $clean);
            }
            return rtrim($clean, '/');
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
