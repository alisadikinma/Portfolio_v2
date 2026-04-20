<?php

namespace App\Services;

use App\Enums\ContentIdeaStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\ContentIdea;
use Illuminate\Support\Facades\Log;

class PipelineGuard
{
    public function advance(ContentIdea $idea, ContentIdeaStatus $next, string $reason, array $context = []): ContentIdea
    {
        $previous = $idea->status;

        try {
            $idea->transitionTo($next, $reason);
            Log::info("[PipelineGuard] idea #{$idea->id} {$reason}: {$previous} → {$next->value}", $context);
            return $idea;
        } catch (InvalidStateTransitionException $e) {
            Log::error("[PipelineGuard] illegal transition on idea #{$idea->id}: {$e->getMessage()}", $context);
            throw $e;
        }
    }
}
