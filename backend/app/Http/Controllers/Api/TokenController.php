<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * TokenController
 *
 * Manages API tokens grouped by category. Each category drives its own
 * (name prefix → ability whitelist) contract so a single admin UI can mint
 * tokens for distinct API surfaces (automation webhooks, CV master export,
 * future surfaces) without ability sprawl.
 *
 * Category contract:
 *   automation → name LIKE 'api-%',  abilities ⊆ POST_ABILITIES   (n8n / Zapier)
 *   cv         → name LIKE 'cv-%',   abilities ⊆ CV_ABILITIES     (jobhunter)
 *
 * Adding a new category = add one row to CATEGORIES + a public scope on
 * `personal_access_tokens.name`. No schema change, no new endpoints.
 */
class TokenController extends Controller
{
    /**
     * Category registry — single source of truth for prefix + ability lists.
     * Keys are the category slugs sent by the frontend; values describe how
     * to validate + filter tokens that belong to each surface.
     */
    private const CATEGORIES = [
        'automation' => [
            'label' => 'Automation',
            'prefix' => 'api-',
            'abilities' => ['post:read', 'post:write', 'post:delete', 'category:read'],
            'description' => 'Webhook ingestion for n8n / Zapier / Make.com',
        ],
        'cv' => [
            'label' => 'CV API',
            'prefix' => 'cv-',
            'abilities' => ['cv:read'],
            'description' => 'Read-only CV export for jobhunter platform (JSON Resume + master.md)',
        ],
        'geminigen_relay' => [
            'label' => 'GeminiGen Relay',
            'prefix' => 'geminigen-',
            'abilities' => ['geminigen:relay'],
            'description' => 'Webhook callbacks from GeminiGen.ai (used by geminigen-api-client plugin).',
        ],
    ];

    /**
     * Resolve a token's category from its name prefix. Tokens that don't
     * match any registered prefix are tagged 'other' (kept invisible to
     * the UI by default — auth-token, legacy tokens, etc.).
     */
    private function categoryForName(string $name): string
    {
        foreach (self::CATEGORIES as $slug => $cfg) {
            if (str_starts_with($name, $cfg['prefix'])) {
                return $slug;
            }
        }
        return 'other';
    }

    /**
     * GET /api/admin/automation/categories
     *
     * Static config for the admin UI — lets the frontend render the category
     * dropdown + ability checkboxes without hardcoding values that drift from
     * the backend whitelist.
     */
    public function categories(): JsonResponse
    {
        $payload = [];
        foreach (self::CATEGORIES as $slug => $cfg) {
            $payload[] = [
                'slug' => $slug,
                'label' => $cfg['label'],
                'prefix' => $cfg['prefix'],
                'abilities' => $cfg['abilities'],
                'description' => $cfg['description'],
            ];
        }
        return response()->json(['success' => true, 'data' => $payload]);
    }

    /**
     * GET /api/admin/automation/tokens
     *
     * List tokens belonging to the authenticated user across known
     * categories. Optional `?category=cv` filter narrows to one surface;
     * absent filter returns automation + cv tokens (excludes auth-token
     * and 'other' legacy entries).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filterCategory = $request->query('category');

        // Build the WHERE for token name across all known prefixes
        $allowedPrefixes = collect(self::CATEGORIES);
        if ($filterCategory && isset(self::CATEGORIES[$filterCategory])) {
            $allowedPrefixes = $allowedPrefixes->only($filterCategory);
        }

        $query = DB::table('personal_access_tokens')
            ->where('tokenable_type', 'App\\Models\\User')
            ->where('tokenable_id', $user->id)
            ->where(function ($q) use ($allowedPrefixes) {
                foreach ($allowedPrefixes as $cfg) {
                    $q->orWhere('name', 'LIKE', $cfg['prefix'] . '%');
                }
            })
            ->orderBy('created_at', 'desc');

        $tokens = $query->get()->map(function ($token) {
            // Request count from automation_logs (only meaningful for
            // automation-category tokens; CV calls aren't logged there yet)
            $requestCount = DB::table('automation_logs')
                ->where('token_id', $token->id)
                ->count();

            return [
                'id' => $token->id,
                'name' => $token->name,
                'category' => $this->categoryForName($token->name),
                'abilities' => json_decode($token->abilities, true),
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
                'requests_count' => $requestCount,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $tokens,
        ]);
    }

    /**
     * POST /api/admin/automation/tokens
     *
     * Mint a new token. Frontend supplies `category` slug + `name` (without
     * prefix — backend prepends it) + `abilities` (must be subset of the
     * category's whitelist).
     *
     * Backward-compat: when `category` is absent AND `name` already starts
     * with 'api-', falls through to legacy automation behavior. Other
     * unprefixed names without category default to automation.
     */
    public function store(Request $request): JsonResponse
    {
        // Resolve category (default = automation for backward compat)
        $categorySlug = $request->input('category', 'automation');
        if (!isset(self::CATEGORIES[$categorySlug])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_CATEGORY',
                    'message' => "Unknown category '{$categorySlug}'. Valid: " . implode(', ', array_keys(self::CATEGORIES)),
                ],
            ], 422);
        }
        $category = self::CATEGORIES[$categorySlug];

        // Validate against category-specific rules
        $request->validate([
            'name' => 'required|string|max:255',
            'abilities' => 'required|array|min:1',
            'abilities.*' => ['string', Rule::in($category['abilities'])],
        ], [
            'abilities.*.in' => "Abilities must be one of: " . implode(', ', $category['abilities']) . " for category '{$categorySlug}'.",
        ]);

        // Auto-prefix name if user didn't include it. Both `n8n-prod` and
        // `api-n8n-prod` resolve to `api-n8n-prod` for category=automation.
        $rawName = $request->input('name');
        $finalName = str_starts_with($rawName, $category['prefix'])
            ? $rawName
            : $category['prefix'] . $rawName;

        $user = $request->user();
        $token = $user->createToken($finalName, $request->input('abilities'));

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $token->accessToken->id,
                'name' => $token->accessToken->name,
                'category' => $categorySlug,
                'abilities' => $token->accessToken->abilities,
                'created_at' => $token->accessToken->created_at,
            ],
            'token' => $token->plainTextToken, // Only shown once!
            'message' => 'Token created successfully',
        ], 201);
    }

    /**
     * DELETE /api/admin/automation/tokens/{id}
     *
     * Revoke a token. Works across categories — operator just deletes by id.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $deleted = DB::table('personal_access_tokens')
            ->where('id', $id)
            ->where('tokenable_type', 'App\\Models\\User')
            ->where('tokenable_id', $user->id)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TOKEN_NOT_FOUND',
                    'message' => 'Token not found or already revoked',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Token revoked successfully',
        ]);
    }

    /**
     * Get automation logs (unchanged — only meaningful for automation-category
     * tokens; CV API calls aren't logged via automation_logs).
     */
    public function logs(Request $request): JsonResponse
    {
        $query = DB::table('automation_logs')
            ->orderBy('created_at', 'desc');

        if ($request->has('action') && $request->input('action') !== '') {
            $query->where('action', $request->input('action'));
        }
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $perPage = min($request->query('per_page', 20), 100);
        $page = $request->query('page', 1);

        $total = $query->count();
        $logs = $query
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user_id' => $log->user_id,
                    'token_id' => $log->token_id,
                    'action' => $log->action,
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'metadata' => $log->metadata ? json_decode($log->metadata, true) : null,
                    'created_at' => $log->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $logs,
            'meta' => [
                'current_page' => (int) $page,
                'last_page' => (int) ceil($total / $perPage),
                'per_page' => (int) $perPage,
                'total' => (int) $total,
            ],
        ]);
    }

    /**
     * Clear all logs (admin only).
     */
    public function clearLogs(Request $request): JsonResponse
    {
        DB::table('automation_logs')->truncate();

        return response()->json([
            'success' => true,
            'message' => 'All logs cleared successfully',
        ]);
    }
}
