<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ScheduledCommandResource;
use App\Models\ScheduledCommand;
use Cron\CronExpression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Phase E — admin scheduler tab API.
 *
 * Three actions:
 *  - index(): list all rows grouped by category (sort_order ASC, display_name ASC)
 *  - update(): persist edits to enabled/cron/timezone/etc. Blocks placeholder rows.
 *  - run(): dispatch an Artisan command via Artisan::queue() asynchronously.
 *           Blocks placeholder + disabled rows. Optimistically marks
 *           last_status='running' so the UI reflects the queued state
 *           even before the kernel hook lands the real audit.
 *
 * Read flow honors the live DB state. The DynamicScheduleRegistrar
 * (Phase B) reads the same rows for actual cron firing.
 */
class SchedulerController extends Controller
{
    /**
     * GET /api/admin/scheduler
     * Returns: { groups: { <category>: [<resource>, ...], ... } }
     */
    public function index(Request $request): JsonResponse
    {
        $rows = ScheduledCommand::query()
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get();

        $groups = $rows->groupBy('category')->map(function ($bucket) {
            return ScheduledCommandResource::collection($bucket->values())->resolve();
        });

        return response()->json([
            'groups' => $groups,
        ]);
    }

    /**
     * PUT /api/admin/scheduler/{cmd}
     * Persist allowed edits. Placeholder rows are read-only.
     */
    public function update(Request $request, ScheduledCommand $cmd): JsonResponse
    {
        if ($cmd->is_placeholder) {
            return response()->json([
                'message' => 'Placeholder schedules cannot be edited',
            ], 403);
        }

        $validated = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'cron_expression' => [
                'sometimes',
                'string',
                'max:64',
                function ($attr, $value, $fail) {
                    if (!CronExpression::isValidExpression($value)) {
                        $fail('Invalid cron expression.');
                    }
                },
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'without_overlapping_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1440'],
            'timezone' => [
                'sometimes',
                'string',
                function ($attr, $value, $fail) {
                    if (!in_array($value, \DateTimeZone::listIdentifiers(), true)) {
                        $fail('Invalid timezone.');
                    }
                },
            ],
            'run_in_background' => ['sometimes', 'boolean'],
        ]);

        $cmd->update($validated);

        return response()->json([
            'data' => new ScheduledCommandResource($cmd->fresh()),
        ]);
    }

    /**
     * POST /api/admin/scheduler/{cmd}/run
     * Dispatch the artisan command asynchronously via Artisan::queue().
     * Optimistically marks last_status='running' for UI feedback.
     */
    public function run(ScheduledCommand $cmd): JsonResponse
    {
        if ($cmd->is_placeholder) {
            return response()->json([
                'message' => 'Placeholder schedules cannot be run',
            ], 403);
        }

        if (!$cmd->enabled) {
            return response()->json([
                'message' => 'Disabled schedules cannot be run',
            ], 403);
        }

        // Parse stored arguments ('--key=value' strings) into an assoc map
        // that Artisan::queue() understands.
        $params = [];
        foreach ($cmd->arguments ?? [] as $arg) {
            if (!is_string($arg)) {
                continue;
            }
            // Match "--key=value" or "--key" (boolean flag)
            if (preg_match('/^--([^=]+)=(.+)$/', $arg, $m)) {
                $params[$m[1]] = $m[2];
            } elseif (preg_match('/^--(.+)$/', $arg, $m)) {
                $params[$m[1]] = true;
            }
        }

        Artisan::queue($cmd->signature, $params);

        $cmd->update([
            'last_run_at' => now(),
            'last_status' => 'running',
            'last_error' => null,
        ]);

        return response()->json([
            'message' => 'Queued',
            'data' => new ScheduledCommandResource($cmd->fresh()),
        ], 202);
    }
}
