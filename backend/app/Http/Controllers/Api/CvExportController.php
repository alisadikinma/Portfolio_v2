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
use App\Services\CvMasterMarkdownService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * CV Master Export API
 *
 * Token-protected endpoint that emits Ali's full professional profile in a
 * JSON Resume-flavored shape so external jobhunter platforms can consume
 * basics + work + education + skills + projects + awards + thought
 * leadership in a single request.
 *
 * Authentication: Sanctum bearer token with `cv:read` ability.
 * Rate limit: 30 requests / minute (registered in routes/api.php).
 *
 * Schema version 2.0.0 (May 6, 2026 — BREAKING CHANGES):
 * -------------------------------------------------------
 * 1. Dropped {success, data} envelope — endpoint returns the JSON body
 *    directly. HTTP status code already conveys success/failure.
 * 2. Added basics.summary_text (HTML-stripped parallel of summary).
 * 3. Added basics.summary_variants (3 hand-crafted opening paragraphs:
 *    vibe_coding / ai_automation / ai_video — sourced from settings.cv).
 * 4. Added work[] (employment history, separate from projects[]).
 * 5. Added education[] (degrees + courses, may be empty list).
 * 6. Added skills{} (categorized object, e.g. languages / frameworks /
 *    ai_tools). ATS keyword matchers read this shape directly.
 * 7. Per-project: variant_hint (strict 3-value enum) split from
 *    relevance_hint. tech_stack made explicit (was conflated with tags).
 * 8. Per-award: summary_text parallel to summary.
 * 9. Mojibake sanitizer applied recursively at output time
 *    (U+FFFD REPLACEMENT CHARACTER → "·" middle dot).
 *
 * Settings sourcing
 * -----------------
 * - basics: settings group=about (name, title, bio, social_links, etc.)
 * - summary_variants / work / skills / education: settings group=cv
 *   (4 JSON keys — see CvSettingsSeeder)
 * - projects / awards / thought_leadership: respective tables
 */
class CvExportController extends Controller
{
    /**
     * Replacement string for U+FFFD characters (mojibake artifacts from a
     * prior charset migration). Middle dot is a safe default — fits the
     * common case where the original was an em-dash or bullet separator.
     */
    protected const MOJIBAKE_REPLACEMENT = '·';

    public function export(Request $request): JsonResponse
    {
        $about = Setting::where('group', 'about')->pluck('value', 'key');
        $cv = Setting::where('group', 'cv')->pluck('value', 'key');

        $socialLinks = $this->parseSocialLinks($about->get('social_links'));
        $bio = $about->get('bio');

        $payload = [
            'schema_version' => '2.0.0',
            'generated_at' => now()->toIso8601ZuluString(),
            'basics' => [
                'name' => $about->get('name') ?: 'Ali Sadikin',
                'label' => $about->get('title') ?: 'AI Generalist Expert',
                'email' => $about->get('email'),
                'phone' => $about->get('phone'),
                'url' => rtrim(config('app.url'), '/'),
                'summary' => $bio,
                'summary_text' => $this->stripHtmlPlain($bio),
                'summary_variants' => $this->decodeJsonSetting($cv->get('summary_variants'), [
                    'vibe_coding' => '',
                    'ai_automation' => '',
                    'ai_video' => '',
                ]),
                'location' => [
                    'city' => $about->get('city'),
                    'country' => $about->get('country') ?: 'Indonesia',
                    'remote' => true,
                ],
                'profiles' => $socialLinks,
            ],
            'work' => $this->decodeJsonSetting($cv->get('work_experience'), []),
            'education' => $this->decodeJsonSetting($cv->get('education'), []),
            'skills' => $this->decodeJsonSetting($cv->get('skills_matrix'), []),
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

        // Apply mojibake sanitizer recursively across the whole tree before
        // emitting. Cheap (one walk), output-only (DB rows untouched).
        $payload = $this->sanitizeMojibake($payload);

        // No envelope — JSON body IS the resource. HTTP status conveys
        // success/failure (200 / 401 / 403 / 5xx). Schema v2.0.0 contract.
        return response()->json($payload);
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
    public function master(Request $request, CvMasterMarkdownService $service): Response
    {
        $body = $service->render($request->boolean('compact'));

        return response($body, 200)
            ->header('Content-Type', 'text/markdown; charset=utf-8');
    }

    /**
     * Decode a Settings.value JSON string into PHP. Falls back to the
     * provided default when the row is missing, empty, or invalid JSON
     * (safer than throwing on the public API surface — operator gets a
     * clean empty array/object instead of a 500).
     */
    protected function decodeJsonSetting($raw, $default)
    {
        if ($raw === null || $raw === '') return $default;
        if (is_array($raw)) return $raw;
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : $default;
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

    /**
     * Strip HTML tags + collapse whitespace + decode entities. Returns null
     * for null input (preserves null vs empty-string semantics on the wire).
     */
    protected function stripHtmlPlain(?string $html): ?string
    {
        if ($html === null) return null;
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    /**
     * Walk the payload recursively and replace U+FFFD (REPLACEMENT
     * CHARACTER, the mojibake artifact) with a safe default. Output-only
     * sanitizer — does not touch the source rows. Real fix is a DB
     * charset migration; until then the API surface stays clean.
     */
    protected function sanitizeMojibake($value)
    {
        if (is_string($value)) {
            // U+FFFD = "\xEF\xBF\xBD" in UTF-8.
            return str_replace("\xEF\xBF\xBD", self::MOJIBAKE_REPLACEMENT, $value);
        }
        if (is_array($value)) {
            return array_map(fn ($v) => $this->sanitizeMojibake($v), $value);
        }
        return $value;
    }
}
