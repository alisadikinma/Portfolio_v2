<?php

namespace App\Jobs;

use App\Models\LinkedInPost;
use App\Services\TelegramNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Generic queued telegram notify for pipeline events that aren't tied to a
 * single ContentIdea (e.g., LinkedIn auto-retry exhaustion, carousel slide
 * tier-2 failure). Sister to `DispatchTelegramNotification` which is
 * ContentIdea-coupled and predates the LinkedIn pipeline.
 *
 * Constructor signature: (string $eventType, array $payload).
 * Handle dispatches to TelegramNotificationService methods based on event.
 *
 * 3 retries (30s / 2m / 5m backoff). Telegram alerts are non-critical so
 * permanent failure logs but doesn't escalate.
 */
class DispatchPipelineTelegramEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];

    public function __construct(
        public string $eventType,
        public array $payload = [],
    ) {
    }

    public function handle(TelegramNotificationService $service): void
    {
        $sent = match ($this->eventType) {
            'linkedin_auto_retry_exhausted' => $this->sendLinkedInAutoRetryExhausted($service),
            'idea_auto_retry_exhausted' => $this->sendIdeaAutoRetryExhausted($service),
            'carousel_slide_tier2_failed' => $this->sendCarouselSlideTier2Failed($service),
            default => $this->logUnknownEvent(),
        };

        if (!$sent) {
            Log::info('DispatchPipelineTelegramEvent: send returned false', [
                'event' => $this->eventType,
                'payload' => $this->payload,
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('DispatchPipelineTelegramEvent: permanently failed after retries', [
            'event' => $this->eventType,
            'payload' => $this->payload,
            'error' => $e->getMessage(),
        ]);
    }

    private function sendLinkedInAutoRetryExhausted(TelegramNotificationService $service): bool
    {
        $draftId = (int) ($this->payload['draft_id'] ?? 0);
        if ($draftId <= 0) {
            return false;
        }
        $draft = LinkedInPost::find($draftId);
        if (!$draft) {
            return false;
        }
        return $service->sendLinkedInAutoRetryExhausted($draft, (string) ($this->payload['last_error'] ?? ''));
    }

    private function sendIdeaAutoRetryExhausted(TelegramNotificationService $service): bool
    {
        $ideaId = (int) ($this->payload['idea_id'] ?? 0);
        if ($ideaId <= 0) {
            return false;
        }
        $idea = \App\Models\ContentIdea::find($ideaId);
        if (!$idea) {
            return false;
        }
        return $service->sendIdeaAutoRetryExhausted($idea, (string) ($this->payload['last_error'] ?? ''));
    }

    private function sendCarouselSlideTier2Failed(TelegramNotificationService $service): bool
    {
        $draftId = (int) ($this->payload['draft_id'] ?? 0);
        $slideIndex = (int) ($this->payload['slide_index'] ?? -1);
        if ($draftId <= 0 || $slideIndex < 0) {
            return false;
        }
        $draft = LinkedInPost::find($draftId);
        if (!$draft) {
            return false;
        }
        return $service->sendCarouselSlideTier2Failed(
            $draft,
            $slideIndex,
            (string) ($this->payload['error'] ?? '')
        );
    }

    private function logUnknownEvent(): bool
    {
        Log::warning('DispatchPipelineTelegramEvent: unknown event type', [
            'event' => $this->eventType,
        ]);
        return false;
    }
}
