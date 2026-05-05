<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Award;
use App\Models\Project;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves the mid-article promo slot shown by BlogDetail.vue between article
 * sections. Priority order:
 *   1. Project explicitly pinned via setting `blog.blog_promo_project_id`
 *   2. Featured project — rotated deterministically by blog slug (so every
 *      post shows a *different* but stable case study, instead of every post
 *      promoting the same Mysatnusa card)
 *   3. Most recently received award
 *   4. Generic CTA (reads site settings for headline/link)
 *
 * Variation strategy: the optional `?slug=` query param identifies the host
 * blog post. We hash that slug into the featured-project list so each post
 * gets a stable pick (good for SEO + repeat readers), but readers moving
 * across posts see variety.
 *
 * Response is a flat, renderer-friendly payload — the frontend card does not
 * need to know which tier fired.
 */
class BlogPromoSlotController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $slug = $this->normalizeSlug($request->query('slug'));

        // Cache key per slug so different posts get different promos (still
        // 60s TTL so an admin pinning a project sees the change quickly).
        $cacheKey = 'blog_promo_slot:' . ($slug ?? 'default');

        $payload = Cache::remember($cacheKey, 60, function () use ($slug) {
            return $this->resolvePayload($slug);
        });

        return response()->json(['data' => $payload]);
    }

    private function resolvePayload(?string $slug): array
    {
        $pinnedId = Setting::where('group', 'blog')
            ->where('key', 'blog_promo_project_id')
            ->value('value');

        if ($pinnedId) {
            $project = Project::where('id', $pinnedId)
                ->where('published', true)
                ->first();
            if ($project) {
                return $this->projectPayload($project);
            }
        }

        $featured = $this->pickFeaturedProject($slug);
        if ($featured) {
            return $this->projectPayload($featured);
        }

        $award = Award::orderByDesc('received_at')
            ->orderByDesc('id')
            ->first();
        if ($award) {
            return $this->awardPayload($award);
        }

        return $this->genericPayload();
    }

    /**
     * Pick a featured project rotated by blog slug. With N featured projects
     * and a stable hash, every post N steps apart cycles back — but for
     * realistic catalogs (4-12 featured projects) every post effectively
     * gets a different one.
     *
     * Falls back to the most recent featured project when slug is absent
     * (e.g. caller hasn't been updated, listing pages, sitemap fetches).
     */
    private function pickFeaturedProject(?string $slug): ?Project
    {
        $featured = Project::where('featured', true)
            ->where('published', true)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get();

        if ($featured->isEmpty()) return null;
        if ($featured->count() === 1 || !$slug) return $featured->first();

        // crc32 → unsigned int → modulo gives a stable index per slug.
        $index = crc32($slug) % $featured->count();
        return $featured[$index];
    }

    private function normalizeSlug(mixed $raw): ?string
    {
        if (!is_string($raw)) return null;
        $clean = trim($raw);
        if ($clean === '') return null;
        // Cap length so a malicious caller can't blow up the cache namespace.
        return mb_substr($clean, 0, 200);
    }

    private function projectPayload(Project $project): array
    {
        return [
            'type' => 'project',
            'eyebrow' => 'Featured case study',
            'title' => $project->title,
            'description' => $this->firstNonEmpty([
                $project->ai_summary,
                $project->meta_description,
                $project->description,
            ], 180),
            'image' => $this->resolveProjectImage($project->image),
            'link' => "/projects/{$project->slug}",
            'cta_label' => 'Read case study',
        ];
    }

    private function awardPayload(Award $award): array
    {
        return [
            'type' => 'award',
            'eyebrow' => 'Recent recognition',
            'title' => $award->title,
            'description' => $this->firstNonEmpty([
                $award->description,
                $award->organization,
            ], 180),
            'image' => $this->resolveAwardImage($award->image),
            'link' => '/awards',
            'cta_label' => 'See the story',
        ];
    }

    /**
     * Mirrors ProjectResource::getImageUrl — raw `projects/...` stored paths
     * resolve to `/storage/projects/...`. Absolute URLs pass through.
     */
    private function resolveProjectImage(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) return $path;
        if (str_starts_with($path, '/')) return url($path);
        return asset('storage/' . $path);
    }

    /**
     * Mirrors AwardResource — awards sit under `/uploads/awards/` on disk,
     * not storage/. Absolute URLs pass through.
     */
    private function resolveAwardImage(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) return $path;
        if (str_starts_with($path, '/')) return url($path);
        return asset('uploads/awards/' . $path);
    }

    private function genericPayload(): array
    {
        $siteCtaTitle = Setting::where('group', 'site')->where('key', 'cta_title')->value('value');
        $siteCtaBody  = Setting::where('group', 'site')->where('key', 'cta_description')->value('value');
        $siteCtaLink  = Setting::where('group', 'site')->where('key', 'cta_link')->value('value');
        $siteCtaLabel = Setting::where('group', 'site')->where('key', 'cta_label')->value('value');

        return [
            'type' => 'cta',
            'eyebrow' => 'Work with me',
            'title' => $siteCtaTitle ?: 'Building something with AI?',
            'description' => $siteCtaBody ?: "I help teams ship AI features from prototype to production. Let's talk.",
            'image' => null,
            'link' => $siteCtaLink ?: '/contact',
            'cta_label' => $siteCtaLabel ?: 'Get in touch',
        ];
    }

    private function firstNonEmpty(array $candidates, int $truncate): ?string
    {
        foreach ($candidates as $value) {
            if (is_string($value) && trim($value) !== '') {
                $clean = trim(strip_tags($value));
                return mb_strlen($clean) > $truncate
                    ? rtrim(mb_substr($clean, 0, $truncate)) . '…'
                    : $clean;
            }
        }
        return null;
    }
}
