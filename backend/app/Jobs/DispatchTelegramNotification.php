<?php

namespace App\Jobs;

use App\Models\ContentIdea;
use App\Services\TelegramNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued wrapper around TelegramNotificationService. Dispatched from
 * ContentIdeaController pipeline events so the HTTP response isn't blocked
 * on the Telegram API.
 *
 * Retries 3x with 30s / 2min / 5min backoff on transient failures (network
 * hiccups, Telegram API flakes). After exhausting retries, the failure is
 * logged but the pipeline continues — Telegram alerts are non-critical.
 */
class DispatchTelegramNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];

    public int $contentIdeaId;
    public string $notificationType;

    public function __construct(int|ContentIdea $idea, string $notificationType)
    {
        // Dispatchable passes constructor args directly, so we accept either
        // an int id (dispatched after a DB lookup elsewhere) or the ContentIdea
        // model itself (most common — dispatch from controller action).
        $this->contentIdeaId = $idea instanceof ContentIdea ? $idea->id : $idea;
        $this->notificationType = $notificationType;
    }

    public function handle(TelegramNotificationService $service): void
    {
        $idea = ContentIdea::find($this->contentIdeaId);
        if ($idea === null) {
            Log::info('DispatchTelegramNotification: content idea missing, skipping', [
                'content_idea_id' => $this->contentIdeaId,
            ]);
            return;
        }

        $success = match ($this->notificationType) {
            'manifest_needed' => $service->sendManifestAlert($idea),
            'generation_failed' => $service->sendGenerationFailed($idea),
            'publish_success' => $service->sendPublishSuccess($idea),
            default => false,
        };

        if (!$success) {
            Log::info('DispatchTelegramNotification: send returned false', [
                'idea_id' => $idea->id,
                'type' => $this->notificationType,
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::warning('DispatchTelegramNotification: permanently failed after retries', [
            'idea_id' => $this->contentIdeaId,
            'type' => $this->notificationType,
            'error' => $exception->getMessage(),
        ]);
    }
}
