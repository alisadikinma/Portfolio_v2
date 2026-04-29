<?php

namespace App\Support;

use App\Models\LinkedInPost;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Centralized progress emitter for the LinkedIn pipeline.
 *
 * Mirrors the ContentIdea progress pattern (progress_percentage +
 * current_step + progress_log) so the admin LinkedInDraftDetail page
 * can render a live progress modal with phase cards + sub-step pills.
 *
 * Why a static helper vs a service: this is called from inside long
 * service methods (LinkedInGenerationService::generate ~150 lines),
 * delayed jobs (GenerateLinkedInCarouselImages), and webhook handlers
 * (LinkedInCarouselImageService::handleWebhook). Constructor injection
 * everywhere would bloat signatures with no benefit. Static + try/catch
 * keeps the call site one line and never blocks pipeline progress on a
 * progress-write failure.
 */
class LinkedInProgressEmitter
{
    /**
     * Append a single progress entry + update snapshot fields. Idempotent —
     * if step + percentage match the last log entry exactly, skip (avoids
     * log noise when the same milestone fires twice via reaper redispatch).
     *
     * Failures are swallowed: progress logging must never block the
     * actual pipeline. If the DB write fails (e.g., connection blip,
     * concurrent lock), we Log::warning and proceed.
     */
    public static function emit(LinkedInPost $draft, string $step, int $pct, string $message): void
    {
        $pct = max(0, min(100, $pct));
        $log = $draft->progress_log ?? [];

        // Idempotency: skip if last entry has the same step + same percentage.
        $last = end($log) ?: null;
        if ($last && ($last['step'] ?? null) === $step && (int) ($last['percentage'] ?? -1) === $pct) {
            return;
        }

        $entry = [
            'timestamp' => now()->toISOString(),
            'step' => $step,
            'percentage' => $pct,
            'message' => $message,
        ];

        try {
            $draft->update([
                'progress_percentage' => $pct,
                'current_step' => $step,
                'progress_log' => array_merge($log, [$entry]),
            ]);
        } catch (Throwable $e) {
            Log::warning('[LinkedInProgress] emit failed', [
                'draft_id' => $draft->id,
                'step' => $step,
                'percentage' => $pct,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reset progress to zero. Called at the start of regenerate flows so
     * the UI doesn't show stale "85% complete" from the previous run.
     */
    public static function reset(LinkedInPost $draft, string $reason = 'pipeline_restart'): void
    {
        try {
            $draft->update([
                'progress_percentage' => 0,
                'current_step' => 'initializing',
                'progress_log' => [[
                    'timestamp' => now()->toISOString(),
                    'step' => 'initializing',
                    'percentage' => 0,
                    'message' => "Pipeline reset · reason={$reason}",
                ]],
            ]);
        } catch (Throwable $e) {
            Log::warning('[LinkedInProgress] reset failed', [
                'draft_id' => $draft->id,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mark the pipeline as failed at the current percentage. Appends a
     * log entry tagged step='failed' so the frontend can render it red.
     */
    public static function fail(LinkedInPost $draft, string $message, ?int $atPct = null): void
    {
        $pct = $atPct ?? (int) ($draft->progress_percentage ?? 0);
        $log = $draft->progress_log ?? [];

        $entry = [
            'timestamp' => now()->toISOString(),
            'step' => 'failed',
            'percentage' => $pct,
            'message' => $message,
        ];

        try {
            $draft->update([
                'current_step' => 'failed',
                'progress_log' => array_merge($log, [$entry]),
            ]);
        } catch (Throwable $e) {
            Log::warning('[LinkedInProgress] fail-emit failed', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
