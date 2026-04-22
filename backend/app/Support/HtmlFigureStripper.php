<?php

namespace App\Support;

/**
 * Strips `<figure>...</figure>` blocks from HTML content.
 *
 * The content-engine stores translated article bodies in two sinks:
 *   - `post_translations.{lang}.content` — rendered blog version with images
 *     baked in via `<figure>` tags.
 *   - `content_ideas.generated_article.{lang}.content` — raw authored body
 *     with NO figures; the admin Finalize UI re-injects images at render
 *     time based on `image_prompts[]` positions.
 *
 * When mirroring between the two (saveTranslation and
 * SyncTranslationMirrors), the rendered content must be stripped back to
 * raw before it hits the idea JSON — otherwise Finalize double-injects
 * images and the preview shows each figure twice.
 */
class HtmlFigureStripper
{
    /**
     * Remove every `<figure ...>...</figure>` block (including contents) and
     * collapse the surrounding whitespace so the remaining paragraphs flow
     * cleanly. Non-destructive to inline `<img>`, `<p>`, or other markup.
     */
    public static function strip(string $html): string
    {
        if ($html === '' || stripos($html, '<figure') === false) {
            return $html;
        }

        $stripped = preg_replace('/<figure\b[^>]*>.*?<\/figure>/is', '', $html);
        if ($stripped === null) {
            return $html;
        }

        // Collapse the blank lines left behind by the removed block.
        $stripped = preg_replace("/(\r?\n\s*){3,}/", "\n\n", $stripped);

        return $stripped ?? $html;
    }
}
