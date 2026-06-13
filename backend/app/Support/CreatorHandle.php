<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Single source of truth for the creator's @handle across the video_rebrand
 * chrome (footer + CTA overlay) and the caption. Resolution order:
 *   linkedin.creator_handle → creator_brand.creator_brand_slug → @alisadikinma.
 * Known placeholder slugs (creator-brand / creator_brand / empty) are treated as
 * unset so a literal "@creator-brand" never surfaces (the #4 bug).
 */
class CreatorHandle
{
    private const PLACEHOLDER_SLUGS = ['creator-brand', 'creator_brand', ''];

    public static function resolve(): string
    {
        $handle = (string) Setting::query()->where('group', 'linkedin')->where('key', 'creator_handle')->value('value');
        if ($handle !== '') {
            return str_starts_with($handle, '@') ? $handle : '@' . $handle;
        }

        $slug = trim((string) Setting::query()->where('group', 'creator_brand')->where('key', 'creator_brand_slug')->value('value'));

        return in_array($slug, self::PLACEHOLDER_SLUGS, true) ? '@alisadikinma' : '@' . $slug;
    }
}
