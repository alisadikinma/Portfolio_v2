<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\FacebookPostStatus;
use App\Http\Controllers\Api\Admin\Concerns\HandlesCrossPostDraftActions;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateFacebookPost;
use App\Models\FacebookPost;

/**
 * Admin CRUD for Facebook Page cross-post drafts. Format-aware: drafts can
 * be 'text' (LinkedIn content reuse) or 'carousel' (/instagram-gen reuse).
 *
 * Hashtag bound 0-5 (FB algorithm 2026 barely uses hashtags vs IG/TikTok;
 * accept the IG-authored 3-5 set on carousel format and let operators
 * edit down to 1-2 brand tags via PUT).
 */
class FacebookDraftController extends Controller
{
    use HandlesCrossPostDraftActions;

    protected function modelClass(): string
    {
        return FacebookPost::class;
    }

    protected function statusEnumClass(): string
    {
        return FacebookPostStatus::class;
    }

    protected function generateJobClass(): string
    {
        return GenerateFacebookPost::class;
    }

    protected function hashtagBounds(): array
    {
        // Permissive — FB hashtags are weak signal; allow operators to drop
        // to 0-2 via admin UI without triggering 422.
        return [0, 5];
    }

    protected function captionMaxLength(): int
    {
        return 5000; // FB Page caption hard limit is ~63k; keep manageable
    }

    protected function hasFormatColumn(): bool
    {
        return true;
    }
}
