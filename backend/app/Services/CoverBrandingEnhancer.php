<?php

namespace App\Services;

use App\Models\ContentIdea;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CoverBrandingEnhancer
{
    public const HUMAN_KEYWORDS = [
        // English — core
        'person', 'people', 'man', 'woman', 'face', 'portrait', 'human',
        'creator', 'founder', 'speaker', 'author', 'selfie', 'figure',
        // English — expanded (common article subjects)
        'developer', 'engineer', 'designer', 'marketer', 'user', 'student',
        'entrepreneur', 'coder', 'programmer', 'executive',
        // Indonesian
        'pria', 'wanita', 'orang', 'manusia', 'muka', 'wajah',
        'pembicara', 'tokoh', 'penulis', 'pencipta',
        'pengembang', 'perancang', 'mahasiswa', 'wirausaha',
    ];

    private const TITLE_INSTRUCTION_TEMPLATE =
        '. Add large bold thumbnail-style title text "{TITLE}" overlaid in the upper-left third of the frame. Use clean sans-serif typography in white with a subtle dark drop shadow for high contrast against the background. Styled like a premium YouTube thumbnail — text must be clearly legible and not obscure focal subjects. Do not stretch, distort, or duplicate the text.';

    public function enhance(array $prompt, ContentIdea $idea): array
    {
        if (!config('content.cover_branding.enabled', true)) {
            return $prompt;
        }

        $type = $prompt['type'] ?? null;

        if ($type === 'cover') {
            // 1. Inject title instruction into prompt_text.
            // Source priority: user-editable caption first, then article title.
            // Caption is the field users actually edit in the admin UI — per
            // CLAUDE.md it's "article title (exact or light SEO paraphrase)"
            // for covers, so using it as the overlay source matches user mental
            // model (edit caption → see it rendered) and keeps semantics intact.
            $caption = is_string($prompt['caption'] ?? null) ? trim($prompt['caption']) : '';
            $titleSource = $caption !== '' ? $caption : $this->resolveTitle($idea);
            $title = $this->sanitizeTitle($titleSource);
            $title = $this->truncateTitle($title);
            $promptText = $prompt['prompt_text'] ?? '';
            $prompt['prompt_text'] = $this->injectTitleInstruction($promptText, $title);

            // 2. Named-entity gate (see docs/plans/2026-04-20-named-entity-aware-cover-generation.md).
            // When the cover subject is a detected public figure (person
            // entity in entity_refs[]), skip creator-face injection and skip
            // VD auto-rewrite — the plugin already named the person in VD.
            // Landmark/logo/product entities alone don't skip creator face
            // (Ali is still in the scene, visiting the landmark).
            $entityRefs = $prompt['entity_refs'] ?? [];
            $hasPersonEntity = collect($entityRefs)
                ->contains(fn ($e) => is_array($e) && ($e['entity_type'] ?? null) === 'person');

            if ($hasPersonEntity) {
                $prompt = $this->mergeEntityRefsIntoFileUrls($prompt, $entityRefs);
            } else {
                $prompt = $this->prependCreatorFace($prompt, $idea);
                // Non-person entity URLs (landmarks/logos/products) still go
                // into file_urls so GeminiGen has the visual reference.
                if (!empty($entityRefs)) {
                    $prompt = $this->mergeEntityRefsIntoFileUrls($prompt, $entityRefs);
                }
            }

            // 3. Append watermark (brand consistency across all images, incl. entity covers)
            $prompt = $this->appendWatermark($prompt);

            // 4. Force model
            $prompt['model'] = config('content.cover_branding.model', 'nano-banana-pro');

            return $prompt;
        }

        if ($type === 'inline') {
            // Inline: inject only when plugin flags `needs_creator_face: true`
            // OR when expanded human-keyword list matches the VD/prompt text.
            $flagged = ($prompt['needs_creator_face'] ?? false) === true;
            $scanText = ($prompt['visual_direction'] ?? '') . ' ' . ($prompt['prompt_text'] ?? '');
            if ($flagged || $this->hasHumanKeyword($scanText)) {
                $prompt = $this->prependCreatorFace($prompt, $idea);
            }

            // Watermark applies to inline too (brand consistency)
            $prompt = $this->appendWatermark($prompt);

            return $prompt;
        }

        return $prompt;
    }

    /**
     * Append watermark instruction to prompt_text and add brand logo URL to file_urls.
     * No-op when watermark_enabled is false OR when the brand logo file cannot be
     * resolved on disk. Reads config from the creator_brand Settings group.
     */
    private function appendWatermark(array $prompt): array
    {
        $enabled = Setting::where('group', 'creator_brand')
            ->where('key', 'watermark_enabled')
            ->value('value');

        if ($enabled !== 'true' && $enabled !== true && $enabled !== '1' && $enabled !== 1) {
            return $prompt;
        }

        $logoUrl = $this->getCreatorBrandLogoUrl();
        if ($logoUrl === null) {
            Log::warning('Cover branding: watermark_enabled but creator_brand_logo unresolvable — skipping watermark');
            return $prompt;
        }

        $tagline = (string) (Setting::where('group', 'creator_brand')
            ->where('key', 'creator_brand_tagline')
            ->value('value') ?? 'alisadikinma.com');

        $opacityRaw = Setting::where('group', 'creator_brand')
            ->where('key', 'watermark_opacity')
            ->value('value');
        $opacity = is_numeric($opacityRaw) ? (float) $opacityRaw : 0.30;
        $opacity = max(0.05, min(0.95, $opacity));
        $pct = (int) round($opacity * 100);

        $instruction = ". Apply a small compact brand watermark at the bottom-center of the frame using the provided brand logo reference image — logo must be no larger than 6% of the image width and rendered at {$pct}% opacity. Directly below the logo, render the text '{$tagline}' in clean sans-serif typography roughly 2x the height of the logo, in white or light-neutral color at 60-70% opacity so the tagline reads clearly and crisply while the logo stays subtle. Tagline must be noticeably more prominent than the logo itself, but the whole watermark block must not dominate or obscure focal subjects.";
        $prompt['prompt_text'] = ($prompt['prompt_text'] ?? '') . $instruction;

        $fileUrls = $prompt['file_urls'] ?? [];
        if (!is_array($fileUrls)) {
            $fileUrls = [];
        }
        if (!in_array($logoUrl, $fileUrls, true)) {
            $fileUrls[] = $logoUrl;
        }
        $prompt['file_urls'] = $fileUrls;

        return $prompt;
    }

    /**
     * Resolve creator brand logo URL from Setting. Returns null if not configured
     * or file missing from public disk. Follows the same pattern as getCreatorFaceUrl().
     */
    public function getCreatorBrandLogoUrl(): ?string
    {
        $value = Setting::where('group', 'creator_brand')
            ->where('key', 'creator_brand_logo')
            ->value('value');

        if (empty($value) || !is_string($value)) {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        $relative = ltrim($value, '/');
        $relative = preg_replace('#^storage/#', '', $relative);

        // Files are saved to webroot (public_path) by SettingsController::updateCreatorBrandSettings,
        // not to storage/app/public. Check webroot first, then fall back to storage disk for legacy paths.
        if (file_exists(public_path($relative))) {
            return url('/' . $relative);
        }
        if (Storage::disk('public')->exists($relative)) {
            return url('/storage/' . $relative);
        }

        Log::warning('Cover branding: creator_brand_logo file not found on disk', ['path' => $relative]);
        return null;
    }

    /**
     * Prepend creator face URL to face_refs and, when this is the first auto-inject
     * (no visual_direction_original snapshot yet), trigger the sync VD rewrite so
     * the VD text matches the actual reference photo's demographics.
     */
    private function prependCreatorFace(array $prompt, ContentIdea $idea): array
    {
        $creatorUrl = $this->getCreatorFaceUrl();
        if ($creatorUrl === null) {
            return $prompt;
        }

        $existing = $prompt['face_refs'] ?? [];
        if (!is_array($existing)) {
            $existing = [];
        }
        // Idempotency: don't duplicate the creator URL if it's already first
        if (!in_array($creatorUrl, $existing, true)) {
            $prompt['face_refs'] = array_merge([$creatorUrl], $existing);
        } else {
            $prompt['face_refs'] = $existing;
        }

        // Auto-rewrite VD on first-time auto-inject so VD describes actual person
        $hasOriginal = !empty($prompt['visual_direction_original'] ?? '');
        $currentVd = $prompt['visual_direction'] ?? '';
        if (!$hasOriginal && trim($currentVd) !== '') {
            try {
                $articleGen = app(\App\Services\ArticleGenerationService::class);
                $result = $articleGen->rewriteVisualDirectionForFace(
                    $currentVd,
                    $creatorUrl,
                    [
                        'type' => $prompt['type'] ?? null,
                        'concept' => $prompt['concept'] ?? null,
                    ]
                );

                if (($result['success'] ?? false) === true && !empty($result['rewritten_vd'])) {
                    $prompt['visual_direction_original'] = $currentVd;
                    $prompt['visual_direction'] = $result['rewritten_vd'];
                } else {
                    Log::warning('Cover branding: VD auto-rewrite failed — keeping original VD', [
                        'error' => $result['error'] ?? 'unknown',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Cover branding: VD auto-rewrite threw — keeping original VD', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $prompt;
    }

    public function hasHumanKeyword(string $text): bool
    {
        if ($text === '') {
            return false;
        }

        $pattern = '/\b(' . implode('|', array_map('preg_quote', self::HUMAN_KEYWORDS)) . ')\b/iu';

        return (bool) preg_match($pattern, $text);
    }

    public function injectTitleInstruction(string $promptText, string $title): string
    {
        $instruction = str_replace('{TITLE}', $title, self::TITLE_INSTRUCTION_TEMPLATE);
        return $promptText . $instruction;
    }

    /**
     * Merge entity_refs[].url values into file_urls (dedupe-preserving).
     * Called on covers when the plugin has populated entity_refs from
     * /article-images Phase 3.5b Wikidata lookup. GeminiGen consumes
     * file_urls as additional reference images for generation.
     */
    private function mergeEntityRefsIntoFileUrls(array $prompt, array $entityRefs): array
    {
        $fileUrls = $prompt['file_urls'] ?? [];
        if (!is_array($fileUrls)) {
            $fileUrls = [];
        }

        foreach ($entityRefs as $entity) {
            if (!is_array($entity)) {
                continue;
            }
            $url = $entity['url'] ?? null;
            if (!is_string($url) || $url === '') {
                continue;
            }
            if (!in_array($url, $fileUrls, true)) {
                $fileUrls[] = $url;
            }
        }

        $prompt['file_urls'] = $fileUrls;
        return $prompt;
    }

    public function getCreatorFaceUrl(): ?string
    {
        $value = Setting::where('group', 'about')
            ->where('key', 'profile_photo')
            ->value('value');

        if (empty($value) || !is_string($value)) {
            Log::warning('Cover branding: profile_photo setting missing');
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        $relative = ltrim($value, '/');
        // Strip leading "storage/" if stored with the prefix
        $relative = preg_replace('#^storage/#', '', $relative);

        // SettingsController::updateAboutSettings saves profile_photo to webroot (public_path).
        // Check webroot first, fall back to storage disk for legacy paths.
        if (file_exists(public_path($relative))) {
            return url('/' . $relative);
        }
        if (Storage::disk('public')->exists($relative)) {
            return url('/storage/' . $relative);
        }

        Log::warning('Cover branding: profile_photo file not found on disk', ['path' => $relative]);
        return null;
    }

    private function resolveTitle(ContentIdea $idea): string
    {
        $article = $idea->generated_article ?? [];
        $lang = $article['language'] ?? 'id';
        $langNode = $article[$lang] ?? null;

        $langTitle = is_array($langNode) ? ($langNode['title'] ?? null) : null;

        return $langTitle
            ?? $article['title']
            ?? $idea->title
            ?? '';
    }

    private function sanitizeTitle(string $title): string
    {
        // Strip emoji — broad unicode ranges covering emoji, pictographs,
        // symbols, supplementary planes, skin-tone modifiers and variation selectors.
        $title = preg_replace(
            "/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{2300}-\x{23FF}\x{FE00}-\x{FE0F}\x{1FA70}-\x{1FAFF}\x{E0000}-\x{E007F}]/u",
            '',
            $title
        ) ?? $title;

        // Escape double quotes so prompt JSON doesn't break
        $title = str_replace('"', "'", $title);

        return $title;
    }

    private function truncateTitle(string $title): string
    {
        $max = (int) config('content.cover_branding.title_max_len', 70);
        if ($max <= 0 || mb_strlen($title) <= $max) {
            return $title;
        }

        return mb_substr($title, 0, $max - 3) . '...';
    }
}
