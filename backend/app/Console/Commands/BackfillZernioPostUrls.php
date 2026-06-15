<?php

namespace App\Console\Commands;

use App\Exceptions\ZernioApiException;
use App\Models\InstagramPost;
use App\Models\ThreadsPost;
use App\Models\TiktokPost;
use App\Services\ZernioClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Backfill external_url for Zernio-published cross-post siblings (June 15, 2026).
 *
 * Zernio's createPost is ASYNCHRONOUS: for Instagram/TikTok the response carries
 * no platformPostUrl yet (the post is still being pushed to the network), so
 * PublishViaZernio::markPublished stores external_url=null. The admin's
 * "Open on Instagram/TikTok/Threads" links only render when external_url is set,
 * so they never appeared for async platforms.
 *
 * This per-2-min cron polls GET /posts/{zernio_post_id} for any published sibling
 * still missing external_url and backfills the live URL once Zernio finishes
 * processing. Mirrors the "cron is the sole completion driver" pattern already
 * used for GROK hook videos + Veo rebrand assets (Zernio, like GeminiGen, gives
 * us no webhook to lean on).
 *
 * Bounded + idempotent: only touches recently-published rows (older nulls are
 * almost always 409-fallback request-ids that will never resolve), per-platform
 * workspace key via ZernioClient::forPlatform, and a getPost failure just logs
 * and skips (the next tick retries).
 */
class BackfillZernioPostUrls extends Command
{
    protected $signature = 'crosspost:backfill-zernio-urls
        {--limit=50 : Max siblings polled per platform per tick}
        {--days=7 : Only backfill siblings published within the last N days}
        {--dry-run : Log candidates without writing external_url}';

    protected $description = 'Backfill external_url for async Zernio-published IG/TikTok/Threads siblings';

    /** @var array<string, class-string> */
    private const PLATFORMS = [
        'instagram' => InstagramPost::class,
        'tiktok' => TiktokPost::class,
        'threads' => ThreadsPost::class,
    ];

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);
        $backfilled = 0;

        foreach (self::PLATFORMS as $platform => $model) {
            $candidates = $model::query()
                ->where('status', 'published')
                ->whereNotNull('zernio_post_id')
                ->whereNull('external_url')
                ->where('updated_at', '>=', $cutoff)
                ->orderByDesc('updated_at')
                ->limit($limit)
                ->get();

            if ($candidates->isEmpty()) {
                continue;
            }

            $client = app(ZernioClient::class)->forPlatform($platform);

            foreach ($candidates as $sibling) {
                try {
                    $post = $client->getPost((string) $sibling->zernio_post_id);
                    $url = data_get($post, 'platforms.0.platformPostUrl');

                    if (! is_string($url) || $url === '') {
                        continue; // still processing — try again next tick
                    }

                    $this->line("  {$platform} #{$sibling->id} → {$url}");

                    if ($dryRun) {
                        $backfilled++;

                        continue;
                    }

                    $sibling->update(['external_url' => $url]);
                    $backfilled++;

                    Log::info('[BackfillZernioPostUrls] external_url backfilled', [
                        'platform' => $platform, 'sibling_id' => $sibling->id, 'url' => $url,
                    ]);
                } catch (ZernioApiException $e) {
                    Log::warning('[BackfillZernioPostUrls] getPost failed — will retry next tick', [
                        'platform' => $platform, 'sibling_id' => $sibling->id, 'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($backfilled > 0) {
            $this->info(($dryRun ? 'Dry run — would backfill ' : 'Backfilled ')."{$backfilled} URL(s).");
        }

        return self::SUCCESS;
    }
}
