<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * TokenController
 *
 * Manages API tokens for automation platforms
 */
class TokenController extends Controller
{
    /**
     * Get all tokens for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $tokens = DB::table('personal_access_tokens')
            ->where('tokenable_type', 'App\\Models\\User')
            ->where('tokenable_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'abilities' => json_decode($token->abilities, true),
                    'last_used_at' => $token->last_used_at,
                    'created_at' => $token->created_at,
                    'revoked_at' => null, // Sanctum doesn't have revoked_at by default
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $tokens,
        ]);
    }

    /**
     * Create a new API token
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'abilities' => 'required|array|min:1',
            'abilities.*' => 'string|in:post:read,post:write,post:delete,category:read',
        ]);

        $user = $request->user();

        // Create token with specified abilities
        $token = $user->createToken(
            $request->input('name'),
            $request->input('abilities')
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $token->accessToken->id,
                'name' => $token->accessToken->name,
                'abilities' => $token->accessToken->abilities,
                'created_at' => $token->accessToken->created_at,
            ],
            'token' => $token->plainTextToken, // Only shown once!
            'message' => 'Token created successfully',
        ], 201);
    }

    /**
     * Revoke (delete) a token
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
     * Get automation logs
     */
    public function logs(Request $request): JsonResponse
    {
        $query = DB::table('automation_logs')
            ->orderBy('created_at', 'desc');

        // Filter by action
        if ($request->has('action') && $request->input('action') !== '') {
            $query->where('action', $request->input('action'));
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        // Pagination
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
     * Clear all logs (admin only)
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
