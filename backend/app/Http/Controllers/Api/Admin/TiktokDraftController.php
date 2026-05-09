<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\TiktokPostStatus;
use App\Http\Controllers\Api\Admin\Concerns\HandlesCrossPostDraftActions;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateTiktokPost;
use App\Models\TiktokPost;

/**
 * Admin CRUD for TikTok cross-post drafts. Hashtag bound 5-8 (TikTok 2026
 * search-index signal floor + ceiling).
 */
class TiktokDraftController extends Controller
{
    use HandlesCrossPostDraftActions;

    protected function modelClass(): string
    {
        return TiktokPost::class;
    }

    protected function statusEnumClass(): string
    {
        return TiktokPostStatus::class;
    }

    protected function generateJobClass(): string
    {
        return GenerateTiktokPost::class;
    }

    protected function hashtagBounds(): array
    {
        return [5, 8];
    }

    protected function captionMaxLength(): int
    {
        return 2200;
    }
}
