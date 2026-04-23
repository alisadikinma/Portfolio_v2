<?php

namespace App\Jobs;

use App\Models\LinkedInAccount;
use App\Models\LinkedInPost;
use App\Services\LinkedInPublishService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Posts the blog-link first-comment on a published UGC post.
 * Dispatched (delayed) from LinkedInPublishService::publishText after a
 * successful publish. The delay is configurable via
 * `linkedin_first_comment_delay_seconds` (default 30s) — LinkedIn
 * weights the first few comments heavily for dwell-time scoring.
 */
class PostLinkedInFirstComment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];

    public function __construct(
        public int $draftId,
        public string $postUrn,
        public int $accountId,
    ) {
    }

    public function handle(LinkedInPublishService $publisher): void
    {
        $draft = LinkedInPost::find($this->draftId);
        if ($draft === null) {
            Log::info('[PostLinkedInFirstComment] Draft missing, skipping', [
                'draft_id' => $this->draftId,
            ]);
            return;
        }

        $account = LinkedInAccount::find($this->accountId);
        if ($account === null) {
            Log::warning('[PostLinkedInFirstComment] Account missing, skipping', [
                'draft_id' => $this->draftId,
                'account_id' => $this->accountId,
            ]);
            return;
        }

        $linkComment = trim((string) $draft->link_comment);
        if ($linkComment === '') {
            Log::info('[PostLinkedInFirstComment] No link_comment text, skipping', [
                'draft_id' => $this->draftId,
            ]);
            return;
        }

        $result = $publisher->postFirstComment($this->postUrn, $linkComment, $account);

        if (!$result['success']) {
            Log::warning('[PostLinkedInFirstComment] API call failed', [
                'draft_id' => $this->draftId,
                'post_urn' => $this->postUrn,
                'error' => $result['error'] ?? null,
            ]);
            throw new \RuntimeException('First-comment API error: ' . ($result['error'] ?? 'unknown'));
        }

        Log::info('[PostLinkedInFirstComment] Comment posted', [
            'draft_id' => $this->draftId,
            'post_urn' => $this->postUrn,
        ]);
    }
}
