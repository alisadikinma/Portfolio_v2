<?php

namespace App\Services;

use App\Models\ContentIdea;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CoverBrandingEnhancer
{
    public const HUMAN_KEYWORDS = [
        // English
        'person', 'people', 'man', 'woman', 'face', 'portrait', 'human',
        'creator', 'founder', 'speaker', 'author', 'selfie', 'figure',
        // Indonesian
        'pria', 'wanita', 'orang', 'manusia', 'muka', 'wajah',
        'pembicara', 'tokoh', 'penulis', 'pencipta',
    ];

    private const TITLE_INSTRUCTION_TEMPLATE =
        '. Add large bold thumbnail-style title text "{TITLE}" overlaid in the upper-left third of the frame. Use clean sans-serif typography in white with a subtle dark drop shadow for high contrast against the background. Styled like a premium YouTube thumbnail — text must be clearly legible and not obscure focal subjects. Do not stretch, distort, or duplicate the text.';

    public function enhance(array $prompt, ContentIdea $idea): array
    {
        if (!config('content.cover_branding.enabled', true)) {
            return $prompt;
        }

        if (($prompt['type'] ?? null) !== 'cover') {
            return $prompt;
        }

        // 1. Inject title instruction into prompt_text
        $title = $this->sanitizeTitle($this->resolveTitle($idea));
        $title = $this->truncateTitle($title);

        $promptText = $prompt['prompt_text'] ?? '';
        $prompt['prompt_text'] = $this->injectTitleInstruction($promptText, $title);

        // 2. Keyword scan → prepend creator face URL if human detected
        $scanText = ($prompt['visual_direction'] ?? '') . ' ' . ($prompt['prompt_text'] ?? '');
        if ($this->hasHumanKeyword($scanText)) {
            $creatorUrl = $this->getCreatorFaceUrl();
            if ($creatorUrl !== null) {
                $existing = $prompt['face_refs'] ?? [];
                if (!is_array($existing)) {
                    $existing = [];
                }
                $prompt['face_refs'] = array_merge([$creatorUrl], $existing);
            }
        }

        // 3. Force model
        $prompt['model'] = config('content.cover_branding.model', 'nano-banana-pro');

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
        $diskPath = preg_replace('#^storage/#', '', $relative);

        if (!Storage::disk('public')->exists($diskPath)) {
            Log::warning('Cover branding: profile_photo file not found on disk', ['path' => $diskPath]);
            return null;
        }

        return url('/storage/' . $diskPath);
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
