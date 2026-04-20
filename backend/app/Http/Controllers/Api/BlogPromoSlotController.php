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
 *   2. Latest `featured` project
 *   3. Most recently received award
 *   4. Generic CTA (reads site settings for headline/link)
 *
 * Response is a flat, renderer-friendly payload — the frontend card does not
 * need to know which tier fired.
 */
class BlogPromoSlotController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $payload = Cache::remember('blog_promo_slot', 60, function () {
            return $this->resolvePayload();
        });

        return response()->json(['data' => $payload]);
    }

    private function resolvePayload(): array
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

        $featured = Project::where('featured', true)
            ->where('published', true)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();
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
            'image' => $project->image,
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
            'image' => $award->image,
            'link' => '/awards',
            'cta_label' => 'See the story',
        ];
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
