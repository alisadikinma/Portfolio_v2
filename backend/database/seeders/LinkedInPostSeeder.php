<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\PostTranslation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds 11 mock LinkedInPost rows across 6 FSM states so the admin UI
 * (success feed + triage queue + detail view) is testable without the
 * plugin running.
 *
 * Invariant: one LIVE draft per post_id (enforced app-side in the
 * regenerate endpoint). Seeder creates 11 distinct "fake LinkedIn source"
 * blog posts if the DB doesn't have enough real ones, so every draft
 * gets a unique post_id.
 *
 * Fake posts are marked `title LIKE '[LinkedIn Test] %'` so they can be
 * spotted + purged if needed.
 *
 * Usage: php artisan db:seed --class=LinkedInPostSeeder
 */
class LinkedInPostSeeder extends Seeder
{
    private const TARGET_DRAFT_COUNT = 11;
    private const FAKE_POST_PREFIX = '[LinkedIn Test]';

    public function run(): void
    {
        $this->ensureEnoughPosts();

        // 4 published (feed main content)
        LinkedInPost::factory()->count(4)->published()->create();

        // 2 awaiting publish (feed "scheduled" tab + detail countdown UX)
        LinkedInPost::factory()->count(2)->awaitingPublish()->create();

        // 2 manual review (queue default tab)
        LinkedInPost::factory()->count(2)->manualReview()->create();

        // 1 failed (queue "failed" tab + error surface)
        LinkedInPost::factory()->failed()->create();

        // 1 cancelled (feed "cancelled" tab)
        LinkedInPost::factory()->cancelled()->create();

        // 1 generating (queue "in progress" tab + polling UX)
        LinkedInPost::factory()->generating()->create();

        $this->command->info('LinkedInPostSeeder: created 11 mock drafts across 6 states');
    }

    /**
     * Ensure Posts::count() (without an existing live LinkedInPost) is at
     * least 11 by creating fake "[LinkedIn Test]" posts.
     */
    private function ensureEnoughPosts(): void
    {
        $eligible = Post::whereDoesntHave('linkedinPosts')->count();
        if ($eligible >= self::TARGET_DRAFT_COUNT) {
            return;
        }

        $categoryId = Category::query()->value('id');
        if ($categoryId === null) {
            $this->command->error('LinkedInPostSeeder: no blog categories exist. Run CategorySeeder first.');
            throw new \RuntimeException('Cannot seed LinkedIn posts — no blog categories');
        }

        $deficit = self::TARGET_DRAFT_COUNT - $eligible;
        $this->command?->info("LinkedInPostSeeder: creating {$deficit} fake blog posts to satisfy 'one live draft per post' invariant");

        for ($i = 0; $i < $deficit; $i++) {
            $slug = 'linkedin-test-' . Str::random(8) . '-' . ($i + 1);
            $title = self::FAKE_POST_PREFIX . ' Draft Source ' . ($i + 1);

            $post = Post::create([
                'category_id' => $categoryId,
                'slug' => $slug,
                'published' => false,
                'published_at' => null,
            ]);

            // Post table has no title column — all titles live in post_translations.
            PostTranslation::create([
                'post_id' => $post->id,
                'language' => 'en',
                'title' => $title,
                'slug' => $slug . '-en',
                'content' => '<p>Fake source post for LinkedIn admin UI testing.</p>',
                'excerpt' => 'Fake source post for LinkedIn admin UI testing.',
            ]);
        }
    }
}
