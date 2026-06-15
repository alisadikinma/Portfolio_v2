<?php

namespace Tests\Unit;

use App\Services\RepurposeCarouselBuilder;
use Tests\TestCase;

/**
 * Phase A — caption → tool-list parser for the source-mirrored repurpose
 * carousel. The clean tool list lives in the post CAPTION (numbered list), NOT
 * the noisy captured frames. One tool = one slide.
 *
 * @see docs/plans/2026-06-15-carousel-one-tool-per-slide.md
 */
class RepurposeCarouselToolParserTest extends TestCase
{
    private function builder(): RepurposeCarouselBuilder
    {
        return new RepurposeCarouselBuilder();
    }

    /** The real job-25 caption ("10 GitHub Gems"). */
    private function job25Caption(): string
    {
        return 'Big Tech is charging you thousands for tools that are free on GitHub. I went down a '
            . 'rabbit hole and found 10 open-source gems that quietly replace software costing $20 to '
            . '$24,000. Swipe through. 1) AutoHedge — run an AI hedge fund from your laptop. 2) Vibe '
            . 'Trading — 64 finance skills, agents that debate before they trade. 3) Fincept Terminal — '
            . 'Bloomberg-style terminal. Bloomberg: $24k/yr. This: $0. 4) LibreChat — every AI model in '
            . 'one app, your data stays yours. 5) Open Higgsfield AI — 200+ video & image models, no '
            . 'subscription. 6) Open LLM VTuber — an AI companion on your desktop, 100% offline. 7) '
            . 'Claude Ads — audits your ads on every platform (agencies charge $4k/mo). 8) Agentic Inbox '
            . '— AI email triage with a human in the loop. 9) Camofox — a Firefox fork with C++ '
            . 'fingerprint spoofing. 10) HyperFrames — render HTML and CSS as video frames.';
    }

    public function test_parses_ten_distinct_tools_from_real_caption(): void
    {
        $tools = $this->builder()->parseToolList($this->job25Caption());

        $this->assertCount(10, $tools, 'caption lists exactly 10 tools → 10 slides');
        $this->assertSame('AutoHedge', $tools[0]['name']);
        $this->assertSame('run an AI hedge fund from your laptop.', $tools[0]['desc']);
        $this->assertSame('Vibe Trading', $tools[1]['name']);
        $this->assertSame('HyperFrames', $tools[9]['name']);
    }

    public function test_ignores_leading_prose_before_item_one(): void
    {
        $tools = $this->builder()->parseToolList($this->job25Caption());
        // First parsed tool is AutoHedge, not the "Big Tech is charging…" intro.
        $this->assertSame('AutoHedge', $tools[0]['name']);
    }

    public function test_numbers_inside_descriptions_do_not_split_items(): void
    {
        // "$24k/yr", "200+", "100% offline", "$4k/mo" must NOT create spurious items.
        $tools = $this->builder()->parseToolList($this->job25Caption());
        $this->assertCount(10, $tools);
        // Fincept's desc keeps its embedded "$24k/yr. This: $0." intact (em-dash is the split, not the colon).
        $this->assertStringContainsString('Bloomberg-style terminal', $tools[2]['desc']);
        $this->assertStringContainsString('$24k/yr', $tools[2]['desc']);
    }

    public function test_name_only_item_without_separator(): void
    {
        $tools = $this->builder()->parseToolList('Tools: 1) AutoHedge 2) LibreChat — one app');
        $this->assertCount(2, $tools);
        $this->assertSame('AutoHedge', $tools[0]['name']);
        $this->assertSame('', $tools[0]['desc']);
        $this->assertSame('LibreChat', $tools[1]['name']);
        $this->assertSame('one app', $tools[1]['desc']);
    }

    public function test_empty_caption_returns_empty(): void
    {
        $this->assertSame([], $this->builder()->parseToolList(''));
        $this->assertSame([], $this->builder()->parseToolList('No numbered list here at all.'));
    }

    public function test_sanity_ceiling_trims_absurd_lists(): void
    {
        $parts = [];
        for ($i = 1; $i <= 25; $i++) {
            $parts[] = "{$i}) Tool{$i} — desc {$i}";
        }
        $tools = $this->builder()->parseToolList(implode(' ', $parts));
        $this->assertCount(RepurposeCarouselBuilder::MAX_TOOL_SLIDES, $tools);
    }
}
