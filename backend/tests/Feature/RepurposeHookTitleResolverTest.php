<?php

namespace Tests\Feature;

use App\Models\RepurposeJob;
use App\Services\RepurposeHookTitleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * video_rebrand bookend HOOK title → bilingual (Indonesian primary + English
 * companion). The resolver translates the captured source headline ONCE, caches
 * { id, en } onto the job, and degrades gracefully on a CLI miss.
 */
class RepurposeHookTitleResolverTest extends TestCase
{
    use RefreshDatabase;

    /** Resolver subclass injecting a canned translation (no real Claude CLI). */
    private function resolverReturning(array $parsed, bool $success = true): RepurposeHookTitleResolver
    {
        return new class($parsed, $success) extends RepurposeHookTitleResolver {
            public int $calls = 0;

            public function __construct(private array $parsed, private bool $ok)
            {
            }

            protected function runHookTitleTranslate(string $prompt): array
            {
                $this->calls++;

                return ['success' => $this->ok, 'parsed' => $this->ok ? $this->parsed : null, 'output' => '', 'error' => $this->ok ? null : 'boom', 'repaired' => false];
            }
        };
    }

    public function test_translates_and_caches_bilingual_pair(): void
    {
        $job = RepurposeJob::factory()->create([
            'mode' => 'video_rebrand',
            'extracted' => ['source_hook_title' => 'AI Tools That Save Hours'],
        ]);

        $resolver = $this->resolverReturning(['title_id' => '7 AI Tools Penghemat Waktu', 'title_en' => 'AI Tools That Save Hours']);

        $pair = $resolver->resolve($job);

        $this->assertSame('7 AI Tools Penghemat Waktu', $pair['id']);
        $this->assertSame('AI Tools That Save Hours', $pair['en']);

        // Cached onto the job.
        $job->refresh();
        $this->assertSame('7 AI Tools Penghemat Waktu', $job->extracted['source_hook_title_id']);
        $this->assertSame('AI Tools That Save Hours', $job->extracted['source_hook_title_en']);
    }

    public function test_cache_hit_does_not_call_cli(): void
    {
        $job = RepurposeJob::factory()->create([
            'mode' => 'video_rebrand',
            'extracted' => [
                'source_hook_title' => 'AI Tools That Save Hours',
                'source_hook_title_id' => 'Tools AI Penghemat Waktu',
                'source_hook_title_en' => 'AI Tools That Save Hours',
            ],
        ]);

        $resolver = $this->resolverReturning(['title_id' => 'SHOULD NOT RUN', 'title_en' => 'x']);

        $pair = $resolver->resolve($job);

        $this->assertSame(0, $resolver->calls);
        $this->assertSame('Tools AI Penghemat Waktu', $pair['id']);
        $this->assertSame('AI Tools That Save Hours', $pair['en']);
    }

    public function test_no_source_headline_returns_empty_without_cli(): void
    {
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'extracted' => []]);

        $resolver = $this->resolverReturning(['title_id' => 'x', 'title_en' => 'y']);

        $pair = $resolver->resolve($job);

        $this->assertSame(['id' => '', 'en' => ''], $pair);
        $this->assertSame(0, $resolver->calls);
    }

    public function test_translation_failure_degrades_to_original_id_no_companion(): void
    {
        $job = RepurposeJob::factory()->create([
            'mode' => 'video_rebrand',
            'extracted' => ['source_hook_title' => 'AI Tools That Save Hours'],
        ]);

        $resolver = $this->resolverReturning([], success: false);

        $pair = $resolver->resolve($job);

        $this->assertSame('AI Tools That Save Hours', $pair['id']);
        $this->assertSame('', $pair['en']);

        // The un-translated fallback is NOT cached → a later re-skin retries the CLI
        // rather than sticking the hook in the source language forever.
        $job->refresh();
        $this->assertArrayNotHasKey('source_hook_title_id', (array) $job->extracted);
    }
}
