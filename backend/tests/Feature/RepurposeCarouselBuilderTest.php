<?php

namespace Tests\Feature;

use App\Models\RepurposeJob;
use App\Services\RepurposeCarouselBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase B (per-slide bilingual author) + Phase D (assembly) for the
 * source-mirrored repurpose carousel. CLI is stubbed via the test seams.
 *
 * @see docs/plans/2026-06-15-carousel-one-tool-per-slide.md
 */
class RepurposeCarouselBuilderTest extends TestCase
{
    use RefreshDatabase;

    // ---- Phase B: authorSlide ------------------------------------------------

    public function test_author_slide_returns_bilingual_copy_and_image_prompt(): void
    {
        $b = new class extends RepurposeCarouselBuilder {
            protected function runSlideAuthor(string $prompt): array
            {
                return ['success' => true, 'parsed' => [
                    'copy_id' => 'AutoHedge — jalankan hedge fund AI dari laptop.',
                    'copy_en' => 'AutoHedge — run an AI hedge fund from your laptop.',
                    'image_prompt' => 'sketchnote doodle of AutoHedge',
                ], 'output' => '', 'error' => null, 'repaired' => false];
            }
        };

        $r = $b->authorSlide('tool', ['name' => 'AutoHedge', 'desc' => 'd', 'position' => 1, 'total' => 10]);

        $this->assertTrue($r['success']);
        $this->assertStringContainsString('hedge fund AI', $r['copy_id']);
        $this->assertStringContainsString('hedge fund', $r['copy_en']);
        $this->assertNotSame('', $r['image_prompt']);
    }

    public function test_author_slide_fails_when_english_only(): void
    {
        // copy_id missing → bilingual contract violated → failure (never EN-only).
        $b = new class extends RepurposeCarouselBuilder {
            protected function runSlideAuthor(string $prompt): array
            {
                return ['success' => true, 'parsed' => [
                    'copy_en' => 'English only', 'image_prompt' => 'img',
                ], 'output' => '', 'error' => null, 'repaired' => false];
            }
        };

        $r = $b->authorSlide('tool', ['name' => 'X']);
        $this->assertFalse($r['success']);
        $this->assertSame('incomplete_slide', $r['error']);
    }

    public function test_author_slide_handles_exec_failure(): void
    {
        $b = new class extends RepurposeCarouselBuilder {
            protected function runSlideAuthor(string $prompt): array
            {
                return ['success' => false, 'parsed' => null, 'output' => '', 'error' => 'timeout', 'repaired' => false];
            }
        };

        $this->assertFalse($b->authorSlide('cta', ['topic' => 't'])['success']);
    }

    // ---- Phase D: buildSlides ------------------------------------------------

    private function carouselJob(): RepurposeJob
    {
        $job = RepurposeJob::factory()->create(['mode' => 'carousel']);
        $job->extracted = [
            'caption' => 'Free tools. 1) AutoHedge — AI hedge fund. 2) LibreChat — every model in one app. 3) Camofox — Firefox fork.',
            'claims' => ['AutoHedge runs an AI hedge fund with 4 agents', 'Camofox spoofs fingerprints'],
        ];
        $job->rewritten = ['title' => '3 GitHub Tools Gratis'];

        return $job;
    }

    private function stubBuilder(?string $failOn = null): RepurposeCarouselBuilder
    {
        return new class($failOn) extends RepurposeCarouselBuilder {
            public array $calls = [];
            public function __construct(public ?string $failOn = null)
            {
            }

            public function authorSlide(string $role, array $payload): array
            {
                $label = $role === 'tool' ? (string) ($payload['name'] ?? '') : $role;
                $this->calls[] = [$role, $payload];
                if ($this->failOn !== null && $label === $this->failOn) {
                    return ['success' => false, 'copy_id' => '', 'copy_en' => '', 'image_prompt' => '', 'error' => 'boom'];
                }

                return ['success' => true, 'copy_id' => "ID:{$label}", 'copy_en' => "EN:{$label}", 'image_prompt' => "IMG:{$label}", 'error' => null];
            }
        };
    }

    public function test_build_slides_one_tool_per_slide_plus_cover_and_cta(): void
    {
        $slides = $this->stubBuilder()->buildSlides($this->carouselJob());

        // 3 tools → cover + 3 tool slides + cta = 5.
        $this->assertCount(5, $slides);
        $this->assertSame('cover', $slides[0]['layout_hint']);
        $this->assertTrue($slides[0]['is_cover']);
        $this->assertSame('body', $slides[1]['layout_hint']);
        $this->assertSame('cta', $slides[4]['layout_hint']);
        $this->assertTrue($slides[4]['is_cta']);

        // Contiguous slide numbers + one tool per body slide (per-tool mapping).
        $this->assertSame([1, 2, 3, 4, 5], array_column($slides, 'slide_number'));
        $this->assertSame('ID:AutoHedge', $slides[1]['copy_id']);
        $this->assertSame('ID:LibreChat', $slides[2]['copy_id']);
        $this->assertSame('ID:Camofox', $slides[3]['copy_id']);
    }

    public function test_build_slides_every_slide_is_bilingual_and_pending(): void
    {
        $slides = $this->stubBuilder()->buildSlides($this->carouselJob());

        foreach ($slides as $s) {
            $this->assertNotSame('', $s['copy_id'], 'copy_id (ID) required on every slide');
            $this->assertNotSame('', $s['copy_en'], 'copy_en (EN) required on every slide');
            $this->assertNotSame('', $s['image_prompt']);
            $this->assertSame('pending', $s['image_status']);
        }
    }

    public function test_build_slides_passes_fact_checked_claim_to_tool_author(): void
    {
        $b = $this->stubBuilder();
        $b->buildSlides($this->carouselJob());

        $autoHedge = collect($b->calls)->first(fn ($c) => $c[0] === 'tool' && $c[1]['name'] === 'AutoHedge');
        $this->assertNotNull($autoHedge);
        $this->assertStringContainsString('4 agents', $autoHedge[1]['fact'], 'matched fact-checked claim handed to the slide author');
    }

    public function test_build_slides_degrades_to_fallback_on_author_failure(): void
    {
        $slides = $this->stubBuilder('LibreChat')->buildSlides($this->carouselJob());

        // Still 5 slides; the failed tool degrades but is never empty.
        $this->assertCount(5, $slides);
        $libre = collect($slides)->firstWhere('slide_number', 3);
        $this->assertNotSame('', $libre['copy_id']);
        $this->assertStringContainsString('LibreChat', $libre['copy_id']);
    }

    public function test_build_slides_empty_when_no_tool_list(): void
    {
        $job = RepurposeJob::factory()->create(['mode' => 'carousel']);
        $job->extracted = ['caption' => 'No numbered list here.'];

        $this->assertSame([], $this->stubBuilder()->buildSlides($job));
    }
}
