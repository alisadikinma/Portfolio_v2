<?php

namespace Database\Factories;

use App\Enums\LinkedInPostStatus;
use App\Models\LinkedInPost;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Generates realistic LinkedInPost rows for UI development + testing.
 * Each state-specific factory sets the fields that state requires
 * (e.g., Published needs published_at + linkedin_post_url).
 */
class LinkedInPostFactory extends Factory
{
    protected $model = LinkedInPost::class;

    public function definition(): array
    {
        $format = $this->faker->randomElement(['text', 'carousel']);
        $charCount = $format === 'text'
            ? $this->faker->numberBetween(1100, 1300)
            : $this->faker->numberBetween(80, 200);

        // Pick a post that has NO existing LinkedInPost row (enforces
        // "one live draft per post" invariant). Falls back to random
        // when pool is exhausted.
        $availablePostId = Post::whereDoesntHave('linkedinPosts')->inRandomOrder()->value('id')
            ?? Post::inRandomOrder()->value('id');

        return [
            'post_id' => $availablePostId ?? Post::factory(),
            'linkedin_account_id' => null,
            'format' => $format,
            'content' => $this->faker->realText($charCount),
            'link_comment' => $format === 'text'
                ? 'Full article here: ' . $this->faker->url()
                : null,
            'hashtags' => $this->faker->randomElements(
                ['#AI', '#Solopreneur', '#VibeCoding', '#Automation', '#SaaS', '#Productivity', '#Growth'],
                $this->faker->numberBetween(3, 5)
            ),
            'carousel_slides' => $format === 'carousel' ? $this->makeCarouselSlides() : null,
            'carousel_pdf_path' => null,
            'depth_score' => $this->faker->numberBetween(60, 95),
            'validation_log' => null,
            'scheduled_at' => null,
            'cancel_window_ends_at' => null,
            'published_at' => null,
            'linkedin_post_urn' => null,
            'linkedin_asset_urn' => null,
            'linkedin_post_url' => null,
            'status' => LinkedInPostStatus::PendingGeneration->value,
            'pipeline_state_log' => [],
            'last_error' => null,
            'retry_count' => 0,
        ];
    }

    private function makeCarouselSlides(): array
    {
        $count = $this->faker->numberBetween(7, 10);
        $slides = [];
        for ($i = 1; $i <= $count; $i++) {
            $slides[] = [
                'slide_number' => $i,
                'layout_hint' => match (true) {
                    $i === 1 => 'cover',
                    $i === $count => 'cta',
                    $i === 3 || $i === 4 => 'human_fingerprint',
                    default => 'body',
                },
                'copy' => $this->faker->sentence($i === 1 ? 8 : 12),
                'image_prompt' => $this->faker->paragraph(3),
                'image_url' => null,
                'is_cover' => $i === 1,
                'is_cta' => $i === $count,
            ];
        }
        return $slides;
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => LinkedInPostStatus::Published->value,
            'scheduled_at' => now()->subMinutes(30),
            'cancel_window_ends_at' => now()->subMinutes(15),
            'published_at' => now()->subMinutes(14),
            'linkedin_post_urn' => 'urn:li:share:' . $this->faker->numerify('##########'),
            'linkedin_post_url' => 'https://linkedin.com/posts/alisadikinma_' . $this->faker->slug(),
            'pipeline_state_log' => $this->makeLog([
                ['pending_generation', 'generating', 'cron_scan'],
                ['generating', 'validating', 'plugin_completed'],
                ['validating', 'awaiting_publish', 'depth_score_passed'],
                ['awaiting_publish', 'published', 'publisher_success'],
            ]),
        ]);
    }

    public function awaitingPublish(): static
    {
        return $this->state(function () {
            $scheduledAt = now();
            $windowMinutes = config('linkedin.cancel_window_minutes', 15);
            return [
                'status' => LinkedInPostStatus::AwaitingPublish->value,
                'scheduled_at' => $scheduledAt,
                'cancel_window_ends_at' => $scheduledAt->copy()->addMinutes(
                    $this->faker->numberBetween(3, $windowMinutes)
                ),
                'pipeline_state_log' => $this->makeLog([
                    ['pending_generation', 'generating', 'cron_scan'],
                    ['generating', 'validating', 'plugin_completed'],
                    ['validating', 'awaiting_publish', 'depth_score_passed'],
                ]),
            ];
        });
    }

    public function manualReview(): static
    {
        return $this->state(fn () => [
            'status' => LinkedInPostStatus::ManualReview->value,
            'depth_score' => $this->faker->numberBetween(60, 79),
            'validation_log' => [
                'failures' => [
                    ['rule' => 'hook_strength', 'deduction' => -10, 'message' => 'Slide 1 doesn\'t promise a specific payoff'],
                    ['rule' => 'char_count_below_min', 'deduction' => -5, 'message' => 'Text post 980 chars — below 1100 sweet spot'],
                ],
                'suggestions' => [
                    'Rewrite hook using PAS framework (Problem → Agitation → Solution)',
                    'Extend with specific metric or personal anecdote to reach 1100-1300 char target',
                ],
            ],
            'pipeline_state_log' => $this->makeLog([
                ['pending_generation', 'generating', 'cron_scan'],
                ['generating', 'validating', 'plugin_completed'],
                ['validating', 'manual_review', 'depth_score_below_threshold'],
            ]),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => LinkedInPostStatus::Failed->value,
            'last_error' => $this->faker->randomElement([
                'Plugin SSH exit code 1: refs-linkedin-playbook.md not found on VPS',
                'Plugin timeout after 180s — Claude CLI hung',
                'GeminiGen 429 rate-limit on slide 4 after 3 retries',
            ]),
            'retry_count' => $this->faker->numberBetween(1, 3),
            'pipeline_state_log' => $this->makeLog([
                ['pending_generation', 'generating', 'cron_scan'],
                ['generating', 'failed', 'plugin_error'],
            ]),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => LinkedInPostStatus::Cancelled->value,
            'pipeline_state_log' => $this->makeLog([
                ['pending_generation', 'generating', 'cron_scan'],
                ['generating', 'validating', 'plugin_completed'],
                ['validating', 'awaiting_publish', 'depth_score_passed'],
                ['awaiting_publish', 'cancelled', 'admin_cancel'],
            ]),
        ]);
    }

    public function generating(): static
    {
        return $this->state(fn () => [
            'status' => LinkedInPostStatus::Generating->value,
            'pipeline_state_log' => $this->makeLog([
                ['pending_generation', 'generating', 'cron_scan'],
            ]),
        ]);
    }

    private function makeLog(array $transitions): array
    {
        $entries = [];
        $time = now()->subHour();
        foreach ($transitions as [$from, $to, $reason]) {
            $entries[] = [
                'from' => $from,
                'to' => $to,
                'reason' => $reason,
                'timestamp' => $time->toIso8601String(),
            ];
            $time = $time->copy()->addMinutes($this->faker->numberBetween(1, 15));
        }
        return $entries;
    }
}
