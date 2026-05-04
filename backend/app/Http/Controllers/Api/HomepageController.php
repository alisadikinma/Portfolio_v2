<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AwardResource;
use App\Http\Resources\HomepageStatsResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\TestimonialResource;
use App\Models\Award;
use App\Models\Post;
use App\Models\Project;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Homepage public API. Two endpoints:
 *
 *   GET /api/homepage/stats     — hero counters + brand strip
 *   GET /api/homepage/featured  — stats + 4 curated lists for the rest
 *                                 of the homepage (awards, testimonials,
 *                                 projects, articles)
 *
 * Both are wrapped by the `set.locale.by.geoip` middleware so the
 * translation-aware Resources (PostResource, ProjectResource) pick up
 * the right language without an explicit `?lang=` query param.
 *
 * Phase 1, Homepage Redesign — see
 * docs/plans/2026-05-04-homepage-redesign-plan.md (lines 184-352).
 */
class HomepageController extends Controller
{
    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => (new HomepageStatsResource($this->computeStats()))->toArray(request()),
        ]);
    }

    public function featured(): JsonResponse
    {
        // Awards: featured first, then most recent. AwardResource exposes
        // gallery relations via `whenLoaded` — leaving them out here keeps
        // the homepage payload lean (the public Awards page handles the
        // photo-count UI). The `total_photos` accessor still resolves on
        // demand inside the model.
        $awards = Award::query()
            ->where('is_active', true)
            ->orderByDesc('is_featured')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        // LinkedIn-only social proof. Inactive testimonials hidden from
        // the public-facing homepage by default.
        $testimonials = Testimonial::query()
            ->where('is_active', true)
            ->where('source', 'linkedin')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit(4)
            ->get();

        // Projects: published only, sort_order asc (matches existing
        // /api/projects ordering convention). ProjectResource expects
        // `translations` eager-loaded.
        $projects = Project::query()
            ->where('published', true)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit(5)
            ->with('translations')
            ->get();

        // Articles: latest 5 published. Post.scopePublished filters
        // `published=true` AND `published_at <= now()`. PostResource needs
        // `translations` + `category` to render title/excerpt/category badge.
        $articles = Post::query()
            ->published()
            ->latest('published_at')
            ->limit(5)
            ->with(['translations', 'category'])
            ->get();

        $request = request();

        // Resolve each Resource via ->resolve($request) so JsonResource's
        // MissingValue filtering, conditional attributes, and nested Resource
        // wrappers are all flattened to plain arrays. Calling ->toArray()
        // directly skips the filter pass and trips up nested
        // `Resource::collection(whenLoaded(...))` patterns inside AwardResource.
        $resolveMany = fn (string $resourceClass, $items) => $items
            ->map(fn ($model) => (new $resourceClass($model))->resolve($request))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => (new HomepageStatsResource($this->computeStats()))->resolve($request),
                'featured_awards' => $resolveMany(AwardResource::class, $awards),
                'featured_testimonials' => $resolveMany(TestimonialResource::class, $testimonials),
                'featured_projects' => $resolveMany(ProjectResource::class, $projects),
                'latest_articles' => $resolveMany(PostResource::class, $articles),
            ],
        ]);
    }

    /**
     * Shared compute used by both endpoints. Counts read live so admin
     * publishes / archives surface immediately — no caching here yet.
     */
    private function computeStats(): array
    {
        return [
            'years_experience' => (int) config('app.years_experience', 17),
            // Filter to publicly-visible rows only — matches the same
            // `is_active=true` / `published=true` filters used in featured()
            // so the hero counter cannot exceed what the rest of the page
            // actually shows.
            'awards_count' => Award::where('is_active', true)->count(),
            'projects_count' => Project::where('published', true)->count(),
            'enterprise_brands' => config('homepage.enterprise_brands', [
                'Outskill', 'Telkomsel', 'XIAOMI', 'MPA Singapore', 'Satnusa', 'BOTIKA',
            ]),
        ];
    }
}
