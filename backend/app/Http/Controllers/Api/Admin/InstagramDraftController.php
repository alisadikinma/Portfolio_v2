<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\InstagramPostStatus;
use App\Http\Controllers\Api\Admin\Concerns\HandlesCrossPostDraftActions;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateInstagramPost;
use App\Models\InstagramPost;

/**
 * Admin CRUD for Instagram cross-post drafts. Routes mounted at
 * /api/admin/instagram-drafts/* — see routes/api.php.
 *
 * Hashtag bound 3-5 (IG Dec 2025 hardcap, also enforced by plugin Zod
 * schema — server validation is defense-in-depth on operator manual edits).
 */
class InstagramDraftController extends Controller
{
    use HandlesCrossPostDraftActions;

    protected function modelClass(): string
    {
        return InstagramPost::class;
    }

    protected function statusEnumClass(): string
    {
        return InstagramPostStatus::class;
    }

    protected function generateJobClass(): string
    {
        return GenerateInstagramPost::class;
    }

    protected function hashtagBounds(): array
    {
        return [3, 5];
    }

    protected function captionMaxLength(): int
    {
        return 2200; // IG hard limit
    }
}
