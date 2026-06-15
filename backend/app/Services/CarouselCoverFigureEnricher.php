<?php

namespace App\Services;

use App\Models\LinkedInPost;
use Illuminate\Support\Facades\Log;

/**
 * Topic-aware public-figure interaction on the carousel COVER (slide 1).
 *
 * Standard (2026-06-14, see vault 10-Identity/visual-identity.md §Spotlight
 * Portrait scope): the v3 creator-fronted Spotlight Portrait is the default
 * ONLY for IG-source (repurpose) carousels. For ORIGINAL content (blog →
 * carousel) the cover/hook is TOPIC-AWARE — when the topic is about a public
 * figure (e.g. "Perjalanan Soumith Chintala", "IPO OpenAI: yang Altman
 * sembunyikan") the cover should show the creator INTERACTING with that figure
 * (coding together / coffee / whiteboard — whatever fits the topic). The human
 * touch raises curiosity far above a generic creator portrait.
 *
 * Fully dynamic — NO hardcoded names and NO fixed interaction scene:
 *   - the figure is resolved per-topic via VideoHookSceneAuthor (which carries
 *     the /carousel-gen hook+creator+visual bundle as its system prompt) — it
 *     names the figure AND authors the interaction scene that fits the topic;
 *   - the figure's license-clean photo is fetched via EntityReferenceService
 *     (Wikidata/Commons notability + license gate, same as blog covers);
 *   - the figure NAME never enters the image prompt (the author sanitises it);
 *     the figure is referenced only as "the person matching reference image 2".
 *
 * The figure belongs at IMAGE generation (this cover), NOT at video time — for
 * video the same figure goes into the keyframe and Veo merely animates it. This
 * enricher is the carousel-pipeline mirror of that principle.
 *
 * Idempotent + fail-safe: runs the SSH author at most ONCE per cover (guarded
 * by carousel_slides[cover].figure_enriched). Any gate miss / author failure /
 * unresolved figure leaves the plugin-authored creator cover untouched (zero
 * regression) — it never blocks or breaks image dispatch.
 *
 * @see CarouselSlideEnhancer for the face_refs[2] + 2-subject mandate injection
 */
class CarouselCoverFigureEnricher
{
    public function __construct(
        private readonly VideoHookSceneAuthor $author,
        private readonly EntityReferenceService $entities,
    ) {
    }

    /**
     * Resolve + inject a public figure onto the cover slide when the topic
     * warrants it. Returns true only when a figure was actually injected.
     */
    public function enrich(LinkedInPost $draft): bool
    {
        $slides = $draft->carousel_slides ?? [];
        if (! is_array($slides) || $slides === []) {
            return false;
        }

        $coverIdx = $this->coverIndex($slides);
        if ($coverIdx === null) {
            return false;
        }
        $cover = $slides[$coverIdx];

        // Idempotency — already processed (injected or resolved-to-none). Stops
        // the SSH author from re-running on every (re-)dispatch.
        if (! empty($cover['figure_enriched'])) {
            return false;
        }

        $topic = $this->topic($draft, $slides);
        if ($topic === '') {
            return $this->markResolved($draft, $slides, $coverIdx, 'empty_topic');
        }

        // Source gate — IG-source (repurpose) carousels keep the v3
        // creator-fronted Spotlight Portrait BY DEFAULT. Operator exception
        // (2026-06-15): when the repurpose topic is about Tools / Plugins /
        // Skills (the AI-tools niche), loosen the gate so a public figure (e.g.
        // a tool's creator) can interact with the creator on the cover. Every
        // other repurpose topic stays creator-only. Original blog→carousel is
        // always topic-aware. Mark enriched so we don't re-check forever.
        try {
            if ($draft->isRepurpose() && ! $this->isToolsSkillsPluginsTopic($topic)) {
                return $this->markResolved($draft, $slides, $coverIdx, 'ig_source_skip');
            }
        } catch (\Throwable $e) {
            Log::warning('[CarouselCoverFigure] isRepurpose check failed — treating as original', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);
        }

        $headline = trim((string) ($cover['copy_id'] ?? $cover['copy_en'] ?? $cover['copy'] ?? ''));

        try {
            $authored = $this->author->author($topic, true, 'carousel_cover', $headline !== '' ? $headline : null);
        } catch (\Throwable $e) {
            // Transient CLI failure — DON'T mark enriched, let the next dispatch retry.
            Log::warning('[CarouselCoverFigure] author threw — leaving plugin cover, will retry', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if (! ($authored['success'] ?? false)) {
            // Author failed (parse/exec) — transient, retry next dispatch.
            Log::info('[CarouselCoverFigure] author unsuccessful — leaving plugin cover, will retry', [
                'draft_id' => $draft->id,
                'error' => $authored['error'] ?? null,
            ]);

            return false;
        }

        $figureName = $authored['figure_name'] ?? null;
        if (! $figureName) {
            // Topic isn't about a person → creator-fronted cover stands.
            return $this->markResolved($draft, $slides, $coverIdx, 'no_figure_for_topic');
        }

        $hit = $this->entities->findOrFetch($figureName, 'person');
        $figureUrl = is_array($hit) ? ($hit['url'] ?? null) : null;
        if (! is_string($figureUrl) || $figureUrl === '' || ($hit['entity_type'] ?? null) !== 'person') {
            // Named but unresolvable (notability/license miss) → creator-only.
            Log::info('[CarouselCoverFigure] figure unresolved — creator-only cover', [
                'draft_id' => $draft->id,
                'figure' => $figureName,
            ]);

            return $this->markResolved($draft, $slides, $coverIdx, 'figure_unresolved');
        }

        $scene = trim((string) ($authored['scene_prompt'] ?? ''));
        if ($scene === '') {
            return $this->markResolved($draft, $slides, $coverIdx, 'empty_scene');
        }

        // Success — replace the cover scene with the authored interaction
        // (already name-sanitised by the author) + attach the figure photo as
        // reference image 2. Force a cover re-render so the new prompt renders.
        $slides[$coverIdx]['image_prompt'] = $scene;
        $slides[$coverIdx]['entity_face_ref'] = $figureUrl;
        $slides[$coverIdx]['figure_name'] = $figureName; // audit only — never in prompt
        $slides[$coverIdx]['figure_enriched'] = true;
        $slides[$coverIdx]['image_status'] = 'pending';
        $slides[$coverIdx]['image_url'] = null;
        $draft->update(['carousel_slides' => $slides]);

        Log::info('[CarouselCoverFigure] injected figure interaction on cover', [
            'draft_id' => $draft->id,
            'cover_index' => $coverIdx,
            'figure' => $figureName,
        ]);

        return true;
    }

    /** Mark the cover as figure-resolved (no injection) so the author never re-runs. */
    private function markResolved(LinkedInPost $draft, array $slides, int $coverIdx, string $reason): bool
    {
        $slides[$coverIdx]['figure_enriched'] = true;
        $draft->update(['carousel_slides' => $slides]);
        Log::info('[CarouselCoverFigure] cover left creator-fronted', [
            'draft_id' => $draft->id,
            'reason' => $reason,
        ]);

        return false;
    }

    /**
     * Locate the cover slide: first is_cover===true, else first
     * layout_hint==='cover', else slide index 0.
     *
     * @param array<int,array<string,mixed>> $slides
     */
    private function coverIndex(array $slides): ?int
    {
        foreach ($slides as $i => $s) {
            if (! empty($s['is_cover'])) {
                return $i;
            }
        }
        foreach ($slides as $i => $s) {
            if (($s['layout_hint'] ?? null) === 'cover') {
                return $i;
            }
        }

        return array_key_exists(0, $slides) ? 0 : null;
    }

    /**
     * Topic text for the author. PRIMARY source is the linked blog Post title —
     * the highest-signal, figure-naming string available ("OpenAI IPO: 3 Fakta
     * yang Altman sembunyikan"). Slide copy is only a FALLBACK for repurpose
     * drafts that have no Post: the sketchnote carousel style bakes all headline
     * text INTO image_prompt and leaves copy_id / copy_en empty, so a copy-only
     * topic came back empty for blog→carousel drafts → empty_topic → the figure
     * was never resolved even after the lock cleared. (2026-06-15 fix.)
     *
     * @param array<int,array<string,mixed>> $slides
     */
    private function topic(LinkedInPost $draft, array $slides): string
    {
        $title = $this->postTitle($draft);
        if ($title !== '') {
            return $title;
        }

        $lines = [];
        foreach ($slides as $s) {
            // carousel-gen slides store text in copy_id / copy_en (the adapter
            // never writes a plain `copy` key).
            $copy = trim((string) ($s['copy_id'] ?? $s['copy_en'] ?? $s['copy'] ?? ''));
            if ($copy !== '') {
                $lines[] = $copy;
            }
            if (count($lines) >= 4) {
                break;
            }
        }

        return trim(implode(' — ', $lines));
    }

    /**
     * Resolve the linked blog Post title (primary language EN, then ID, then
     * any). Empty string when there is no Post (repurpose drafts) or on any
     * lookup failure — the caller then falls back to slide copy. Never throws.
     */
    private function postTitle(LinkedInPost $draft): string
    {
        try {
            $post = $draft->post;
            if ($post === null) {
                return '';
            }
            $t = $post->translations->firstWhere('language', 'en')
                ?? $post->translations->firstWhere('language', 'id')
                ?? $post->translations->first();

            return trim((string) ($t->title ?? ''));
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Does the topic centre on Tools / Plugins / Skills (the AI-tools niche)?
     * Operator rule (2026-06-15): on IG-repurpose carousels the public-figure
     * cover interaction is loosened ONLY for this topic class — every other
     * repurpose topic keeps the creator-only Spotlight cover. Deliberately broad
     * for the niche (ai / llm / agent / code count), because a repurpose figure
     * here is almost always a tool/framework creator and the figure-resolution
     * gate downstream still leaves non-figure topics creator-only. Tunable —
     * narrow the term set if a non-tools repurpose ever picks up a stray figure.
     */
    private function isToolsSkillsPluginsTopic(string $topic): bool
    {
        return (bool) preg_match(
            '/\b(ai|llm|ml|tools?|plugins?|skills?|extensions?|librar(?:y|ies)|frameworks?|sdk|api|apps?|software|no[- ]?code|automation|otomasi|coding|code|kode|github|repo|models?|agents?|alat|aplikasi|pustaka)\b/i',
            $topic
        );
    }
}
