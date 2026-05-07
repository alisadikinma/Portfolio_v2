<?php

namespace Tests\Unit;

use App\Models\ContentIdea;
use App\Models\LinkedInPost;
use Tests\TestCase;

/**
 * Phase B verification: ensures the new auto-retry bookkeeping fields are
 * mass-assignable on both pipeline models. DB-free (does not exercise the
 * migration itself — CI's `php artisan migrate --force` covers the schema).
 */
class AutoRetryColumnsModelTest extends TestCase
{
    public function test_linkedin_post_fillable_includes_auto_retry_count(): void
    {
        $this->assertContains(
            'auto_retry_count',
            (new LinkedInPost())->getFillable(),
            'LinkedInPost.auto_retry_count must be mass-assignable for the retry cron'
        );
    }

    public function test_linkedin_post_fillable_includes_last_classified_error_class(): void
    {
        $this->assertContains(
            'last_classified_error_class',
            (new LinkedInPost())->getFillable()
        );
    }

    public function test_linkedin_post_casts_auto_retry_count_to_integer(): void
    {
        $casts = (new LinkedInPost())->getCasts();
        $this->assertSame('integer', $casts['auto_retry_count'] ?? null);
    }

    public function test_content_idea_fillable_includes_auto_retry_count(): void
    {
        $this->assertContains(
            'auto_retry_count',
            (new ContentIdea())->getFillable()
        );
    }

    public function test_content_idea_fillable_includes_last_classified_error_class(): void
    {
        $this->assertContains(
            'last_classified_error_class',
            (new ContentIdea())->getFillable()
        );
    }

    public function test_content_idea_casts_auto_retry_count_to_integer(): void
    {
        $casts = (new ContentIdea())->getCasts();
        $this->assertSame('integer', $casts['auto_retry_count'] ?? null);
    }
}
