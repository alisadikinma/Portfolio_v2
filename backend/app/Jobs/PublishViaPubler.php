<?php

namespace App\Jobs;

use App\Enums\FacebookPostStatus;
use App\Enums\InstagramPostStatus;
use App\Enums\ThreadsPostStatus;
use App\Enums\TiktokPostStatus;
use App\Models\FacebookPost;
use App\Models\InstagramPost;
use App\Models\ThreadsPost;
use App\Models\TiktokPost;
use App\Services\PipelineGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Stub Publer publish dispatcher for Phase E admin Approve action.
 *
 * NOT YET IMPLEMENTED — full Publer transport (uploadMedia + createPost +
 * pollJob to confirm publish + persist publer_post_id/external_url) lives
 * in Phase H'+ which is the next slice of Wave 3 work. For now this job
 * exists so the admin Approve endpoint can dispatch SOMETHING and advance
 * the draft FSM to Publishing — we just don't actually call Publer yet.
 *
 * When Phase H' lands the body of handle() will be replaced with real
 * PublerClient calls. Keeping the FSM transition Publishing → Published
 * gated to a real Publer success response (NOT this stub) so operators
 * don't see false-positive "published" UI.
 *
 * Operator behavior with this stub:
 *   - Click Approve → draft moves to Publishing
 *   - Stub job logs the dispatch + returns (does not advance to Published)
 *   - Draft visibly stuck in Publishing in admin UI until Phase H' ships
 *   - Admin can Cancel from Publishing per FSM (transitions to Cancelled)
 *
 * This is INTENTIONAL — it makes the Phase E layer testable end-to-end
 * (including FSM correctness) without requiring Publer production
 * connectivity.
 */
class PublishViaPubler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    /**
     * @param  class-string  $modelClass  FacebookPost / InstagramPost / TiktokPost
     */
    public function __construct(public string $modelClass, public int $draftId)
    {
    }

    public function handle(PipelineGuard $guard): void
    {
        $allowed = [
            FacebookPost::class,
            InstagramPost::class,
            TiktokPost::class,
            ThreadsPost::class,
        ];

        if (!in_array($this->modelClass, $allowed, true)) {
            Log::warning('[PublishViaPubler] Unknown model class — skipping', [
                'model_class' => $this->modelClass,
                'draft_id' => $this->draftId,
            ]);
            return;
        }

        /** @var FacebookPost|InstagramPost|TiktokPost|null $draft */
        $draft = $this->modelClass::find($this->draftId);
        if ($draft === null) {
            Log::info('[PublishViaPubler] Draft not found, skipping', [
                'model_class' => $this->modelClass,
                'draft_id' => $this->draftId,
            ]);
            return;
        }

        Log::info('[PublishViaPubler] Stub invoked — Publer integration pending Phase H+', [
            'model_class' => $this->modelClass,
            'draft_id' => $draft->id,
            'status' => $draft->status,
        ]);

        // Phase H+ TODO:
        //   1. Resolve PublerClient (api_key + workspace + account_id)
        //   2. Upload media (carousel slide PNGs) via /media/from-url + pollMediaJob
        //   3. createPost with platform-specific networks payload
        //   4. pollJob until publish-job 'complete' or 'failed'
        //   5. On success: advance to Published, persist publer_post_id +
        //      external_url
        //   6. On failure: advance to Failed, persist last_error
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[PublishViaPubler] Stub job exhausted retries', [
            'model_class' => $this->modelClass,
            'draft_id' => $this->draftId,
            'error' => $e->getMessage(),
        ]);
    }
}
