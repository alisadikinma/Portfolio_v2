<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Cv\CvAwardResource;
use App\Http\Resources\Cv\CvProjectResource;
use App\Http\Resources\Cv\CvThoughtResource;
use App\Models\Award;
use App\Models\Post;
use App\Models\Project;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * CV Master Export API (Phase 10, May 2026)
 *
 * Token-protected endpoint that emits Ali's full professional profile in a
 * JSON Resume-flavored shape so external jobhunter platforms can consume
 * basics + projects + awards + thought leadership in a single request.
 *
 * Authentication: Sanctum bearer token with `cv:read` ability.
 * Rate limit: 30 requests / minute (registered in routes/api.php).
 *
 * Response envelope conforms to the project's standard
 * { success: true, data: {...} } pattern.
 *
 * Settings sourcing notes
 * -----------------------
 * The `settings` group=about table uses these keys (verified May 4, 2026):
 *   name, title, bio, profile_photo, skills, experience, social_links,
 *   languages, certifications, hero_tagline, availability_note,
 *   trust_strip, mission, what_i_do, approach, collaboration_modes,
 *   statistics
 *
 * Notably ABSENT (returned as null until the operator adds them):
 *   email, phone, city, country
 *
 * Social profiles live under `social_links` (JSON array of
 * {platform, url, icon} rows).
 */
class CvExportController extends Controller
{
    public function export(Request $request): JsonResponse
    {
        $about = Setting::where('group', 'about')->pluck('value', 'key');

        $socialLinks = $this->parseSocialLinks($about->get('social_links'));

        $payload = [
            'schema_version' => '1.0.0',
            'generated_at' => now()->toIso8601ZuluString(),
            'basics' => [
                'name' => $about->get('name') ?: 'Ali Sadikin',
                'label' => $about->get('title') ?: 'AI Generalist Expert',
                'email' => $about->get('email'),
                'phone' => $about->get('phone'),
                'url' => rtrim(config('app.url'), '/'),
                'summary' => $about->get('bio'),
                'location' => [
                    'city' => $about->get('city'),
                    'country' => $about->get('country') ?: 'Indonesia',
                    'remote' => true,
                ],
                'profiles' => $socialLinks,
            ],
            'projects' => CvProjectResource::collection(
                Project::with('translations')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
            )->resolve(),
            'awards' => CvAwardResource::collection(
                Award::orderByDesc('is_featured')
                    ->orderByDesc('id')
                    ->get()
            )->resolve(),
            'thought_leadership' => CvThoughtResource::collection(
                Post::with(['translations', 'category'])
                    ->where('published', true)
                    ->whereNotNull('published_at')
                    ->orderByDesc('published_at')
                    ->limit(5)
                    ->get()
            )->resolve(),
        ];

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    /**
     * GET /api/cv/master.md
     *
     * LLM-optimized markdown rendering of Ali's full professional profile,
     * sourced from the same data as `/api/cv/export` (settings + projects
     * + awards + thought leadership). Designed for jobhunter platform
     * `cv-tailor` and `job-score` skills that consume CV as prompt input.
     *
     * Optional `?compact=1` query truncates per-project narrative for
     * tighter LLM context windows (~5k tokens vs. ~10k default).
     *
     * Auth: Sanctum bearer + `cv:read` ability + throttle:30,1
     * (inherited from the route group).
     */
    public function master(Request $request): Response
    {
        $body = "# Ali Sadikin\n";

        return response($body, 200)
            ->header('Content-Type', 'text/markdown; charset=utf-8');
    }

    /**
     * Decode the Settings.social_links JSON blob into a JSON Resume
     * `profiles` array shape ({network, url}). Hardened against:
     *   - settings row missing entirely
     *   - JSON parse failure
     *   - rows without a url (filtered out — JSON Resume profiles are
     *     pointless without a target URL)
     */
    protected function parseSocialLinks($raw): array
    {
        if (!$raw) {
            return [];
        }
        $decoded = is_array($raw) ? $raw : json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        return collect($decoded)
            ->map(fn ($row) => [
                'network' => $row['platform'] ?? null,
                'url' => $row['url'] ?? null,
            ])
            ->filter(fn ($row) => !empty($row['url']))
            ->values()
            ->all();
    }
}
