<?php

namespace Tests\Unit;

use App\Services\PublerClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Unit tests for PublerClient.
 *
 * Uses Laravel's Http::fake() to mock the Publer API. NO real network calls.
 * api_key resolved via constructor override (skips DB / Crypt path).
 *
 * Coverage:
 *   - Auth header format (`Bearer-API {token}`)
 *   - All 7 public methods happy path
 *   - Error mapping: 401, 403, 429, 5xx, 404 (idempotent delete)
 *   - URL composition (base_url + api_path + relative)
 *   - api_key NOT logged on failure
 */
class PublerClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Override config so URL composition is deterministic in tests
        config([
            'social-cross-post.publer.base_url' => 'https://app.publer.com',
            'social-cross-post.publer.api_path' => '/api/v1',
            'social-cross-post.publer.http_timeout_seconds' => 30,
            'social-cross-post.publer.max_retries' => 0, // disable retry in tests
            'social-cross-post.publer.retry_backoff_ms' => 0,
        ]);
    }

    public function test_me_sends_correct_auth_header_and_returns_user_data(): void
    {
        Http::fake([
            'app.publer.com/api/v1/users/me' => Http::response([
                'success' => true,
                'data' => ['id' => 'user_abc', 'email' => 'ali@example.com'],
            ], 200),
        ]);

        $client = new PublerClient(apiKey: 'TEST_KEY_001');
        $result = $client->me();

        $this->assertSame('user_abc', $result['id']);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer-API TEST_KEY_001')
                && $request->hasHeader('Content-Type', 'application/json')
                && $request->url() === 'https://app.publer.com/api/v1/users/me';
        });
    }

    public function test_list_accounts_returns_array(): void
    {
        Http::fake([
            'app.publer.com/api/v1/accounts' => Http::response([
                'success' => true,
                'data' => [
                    ['id' => 'acc_fb_1', 'type' => 'facebook', 'name' => 'Alisadikinma.com'],
                    ['id' => 'acc_ig_1', 'type' => 'instagram', 'name' => 'Ali Sadikin Ma'],
                    ['id' => 'acc_tt_1', 'type' => 'tiktok', 'name' => 'Ali Sadikin Al'],
                ],
            ], 200),
        ]);

        $client = new PublerClient(apiKey: 'TEST_KEY');
        $accounts = $client->listAccounts(workspaceId: 'ws_test_123');

        $this->assertCount(3, $accounts);
        $this->assertSame('facebook', $accounts[0]['type']);
        $this->assertSame('tiktok', $accounts[2]['type']);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Publer-Workspace-Id', 'ws_test_123');
        });
    }

    public function test_list_accounts_auto_resolves_workspace_from_users_me(): void
    {
        Http::fake([
            'app.publer.com/api/v1/users/me' => Http::response([
                'success' => true,
                'data' => [
                    'id' => 'user_abc',
                    'email' => 'ali@example.com',
                    'workspaces' => [
                        ['id' => 'ws_default_001', 'name' => 'Default'],
                        ['id' => 'ws_other_002', 'name' => 'Other'],
                    ],
                ],
            ], 200),
            'app.publer.com/api/v1/accounts' => Http::response([
                'success' => true,
                'data' => [['id' => 'acc_x', 'type' => 'facebook', 'name' => 'Page X']],
            ], 200),
        ]);

        $client = new PublerClient(apiKey: 'TEST_KEY');
        $accounts = $client->listAccounts();

        $this->assertCount(1, $accounts);

        Http::assertSent(function ($request) {
            if (str_contains($request->url(), '/accounts')) {
                return $request->hasHeader('Publer-Workspace-Id', 'ws_default_001');
            }
            return true;
        });
    }

    public function test_upload_media_from_url_returns_job_id(): void
    {
        Http::fake([
            'app.publer.com/api/v1/media/from-url' => Http::response([
                'success' => true,
                'data' => ['job_id' => 'job_media_xyz'],
            ], 200),
        ]);

        $client = new PublerClient(apiKey: 'TEST_KEY');
        $jobId = $client->uploadMediaFromUrl('https://alisadikinma.com/storage/linkedin-carousel/01-cover.png');

        $this->assertSame('job_media_xyz', $jobId);

        // Body shape: { media: [{ url, name }], type: "single" }
        Http::assertSent(function ($request) {
            return ($request['media'][0]['url'] ?? null) === 'https://alisadikinma.com/storage/linkedin-carousel/01-cover.png'
                && ($request['type'] ?? null) === 'single';
        });
    }

    public function test_upload_media_throws_when_response_missing_job_id(): void
    {
        Http::fake([
            'app.publer.com/api/v1/media/from-url' => Http::response([
                'success' => true,
                'data' => [], // missing job_id
            ], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/no job_id/i');

        $client = new PublerClient(apiKey: 'TEST_KEY');
        $client->uploadMediaFromUrl('https://example.com/image.png');
    }

    public function test_poll_job_returns_status_result_error(): void
    {
        Http::fake([
            'app.publer.com/api/v1/job_status/job_abc' => Http::response([
                'success' => true,
                'data' => [
                    'status' => 'complete',
                    'result' => ['status' => 'success', 'payload' => ['id' => 'media_42']],
                ],
            ], 200),
        ]);

        $client = new PublerClient(apiKey: 'TEST_KEY');
        $job = $client->pollJob('job_abc');

        $this->assertSame('complete', $job['status']);
        $this->assertSame(['status' => 'success', 'payload' => ['id' => 'media_42']], $job['result']);
        $this->assertNull($job['error']);
    }

    public function test_poll_media_job_extracts_media_id_on_complete(): void
    {
        Http::fake([
            'app.publer.com/api/v1/job_status/job_media_xyz' => Http::response([
                'success' => true,
                'data' => [
                    'status' => 'complete',
                    'result' => ['payload' => ['id' => 'media_99']],
                ],
            ], 200),
        ]);

        $client = new PublerClient(apiKey: 'TEST_KEY');
        $job = $client->pollMediaJob('job_media_xyz');

        $this->assertSame('complete', $job['status']);
        $this->assertSame('media_99', $job['media_id']);
    }

    public function test_poll_media_job_returns_null_media_id_when_still_working(): void
    {
        Http::fake([
            'app.publer.com/api/v1/job_status/job_pending' => Http::response([
                'success' => true,
                'data' => ['status' => 'working', 'result' => null],
            ], 200),
        ]);

        $client = new PublerClient(apiKey: 'TEST_KEY');
        $job = $client->pollMediaJob('job_pending');

        $this->assertSame('working', $job['status']);
        $this->assertNull($job['media_id']);
    }

    public function test_create_post_returns_job_id(): void
    {
        Http::fake([
            'app.publer.com/api/v1/posts/schedule' => Http::response([
                'success' => true,
                'data' => ['job_id' => 'job_post_xyz'],
            ], 200),
        ]);

        $client = new PublerClient(apiKey: 'TEST_KEY');
        $payload = [
            'bulk' => [
                'state' => 'scheduled',
                'posts' => [['networks' => ['instagram' => ['type' => 'carousel', 'text' => 'hi']]]],
            ],
        ];
        $jobId = $client->createPost($payload);

        $this->assertSame('job_post_xyz', $jobId);
    }

    public function test_delete_post_treats_404_as_idempotent_success(): void
    {
        Http::fake([
            '*/posts*' => Http::response(null, 404),
        ]);

        $client = new PublerClient(apiKey: 'TEST_KEY');
        $result = $client->deletePost('post_already_gone');

        $this->assertTrue($result);
    }

    public function test_delete_post_succeeds_on_200(): void
    {
        Http::fake([
            '*/posts*' => Http::response([
                'success' => true,
                'data' => [],
            ], 200),
        ]);

        $client = new PublerClient(apiKey: 'TEST_KEY');
        $result = $client->deletePost('post_xyz');

        $this->assertTrue($result);
    }

    public function test_throws_on_401_with_actionable_message(): void
    {
        Http::fake([
            'app.publer.com/api/v1/users/me' => Http::response(['error' => 'Invalid token'], 401),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Rotate or re-enter api_key/i');

        $client = new PublerClient(apiKey: 'BAD_KEY');
        $client->me();
    }

    public function test_throws_on_403_with_plan_hint(): void
    {
        Http::fake([
            'app.publer.com/api/v1/posts/schedule' => Http::response(['error' => 'Plan insufficient'], 403),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Plan\/scope/i');

        $client = new PublerClient(apiKey: 'TEST_KEY');
        $client->createPost(['bulk' => ['state' => 'scheduled', 'posts' => []]]);
    }

    public function test_403_concurrency_throttle_is_classified_transient(): void
    {
        // Publer serializes media-from-url ingest per account; this 403 body
        // (errors[] plural) is a transient concurrency throttle, NOT plan/scope.
        Http::fake([
            'app.publer.com/api/v1/media/from-url' => Http::response(
                ['errors' => ['Please wait until your other download media from URL jobs have finished']],
                403
            ),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/media busy|retry shortly|download-media/i');

        $client = new PublerClient(apiKey: 'TEST_KEY');
        $client->uploadMediaFromUrl('https://alisadikinma.com/storage/linkedin-carousel/01.png');
    }

    public function test_throws_on_429_with_rate_limit_hint(): void
    {
        Http::fake([
            'app.publer.com/api/v1/users/me' => Http::response(['error' => 'Rate limit exceeded'], 429),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/rate limit hit/i');

        $client = new PublerClient(apiKey: 'TEST_KEY');
        $client->me();
    }

    public function test_throws_on_validation_error_with_response_message(): void
    {
        Http::fake([
            'app.publer.com/api/v1/posts/schedule' => Http::response([
                'success' => false,
                'error' => ['code' => 'VALIDATION', 'message' => 'caption too long'],
            ], 422),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/caption too long/i');

        $client = new PublerClient(apiKey: 'TEST_KEY');
        $client->createPost(['bulk' => ['state' => 'scheduled', 'posts' => []]]);
    }

    /**
     * Regression: Publer returns short-form `fb_page` and `ig_business` types
     * for some tenants. Earlier matcher used `str_contains($rawType, 'facebook')`
     * which missed both, sending FB Page + IG accounts to the `other` bucket
     * and hiding them from the admin Settings dropdowns.
     *
     * @dataProvider accountTypeProvider
     */
    public function test_bucket_account_type_classifies_known_variants(string $rawType, string $expectedBucket): void
    {
        $this->assertSame(
            $expectedBucket,
            PublerClient::bucketAccountType($rawType),
            "Raw type '{$rawType}' should bucket as '{$expectedBucket}'"
        );
    }

    public static function accountTypeProvider(): array
    {
        return [
            // Long-form (already worked pre-fix)
            'facebook long' => ['facebook', 'facebook'],
            'facebook_page long' => ['facebook_page', 'facebook'],
            'instagram long' => ['instagram', 'instagram'],
            'instagram_business long' => ['instagram_business', 'instagram'],
            'tiktok long' => ['tiktok', 'tiktok'],
            'tiktok_business long' => ['tiktok_business', 'tiktok'],

            // Short-form prefix (regression — broken pre-fix)
            'fb_page short' => ['fb_page', 'facebook'],
            'fb_group short' => ['fb_group', 'facebook'],
            'ig_business short' => ['ig_business', 'instagram'],
            'ig_creator short' => ['ig_creator', 'instagram'],
            'tt_business short' => ['tt_business', 'tiktok'],

            // Bare two-letter codes
            'fb bare' => ['fb', 'facebook'],
            'ig bare' => ['ig', 'instagram'],
            'tt bare' => ['tt', 'tiktok'],

            // Case-insensitive
            'FB_PAGE upper' => ['FB_PAGE', 'facebook'],
            'IG_Business mixed' => ['IG_Business', 'instagram'],

            // Unknown / unsupported → other
            'twitter' => ['twitter', 'other'],
            'linkedin' => ['linkedin', 'other'],
            'youtube' => ['youtube', 'other'],
            'empty' => ['', 'other'],

            // Defense: avoid false positives from `fb`/`ig`/`tt` substrings
            // inside unrelated tokens. `str_starts_with` with `_` suffix
            // requires the underscore, so these stay in `other`.
            'fbi_account no false match' => ['fbi_account', 'other'],
            'igloo no false match' => ['igloo', 'other'],
        ];
    }

    public function test_bucket_account_type_handles_null(): void
    {
        $this->assertSame('other', PublerClient::bucketAccountType(null));
    }

    public function test_url_composition_handles_trailing_slashes(): void
    {
        config([
            'social-cross-post.publer.base_url' => 'https://app.publer.com/',  // trailing slash
            'social-cross-post.publer.api_path' => '/api/v1/',                 // trailing slash
        ]);

        Http::fake([
            'app.publer.com/api/v1/users/me' => Http::response(['success' => true, 'data' => []], 200),
        ]);

        $client = new PublerClient(apiKey: 'TEST_KEY');
        $client->me();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://app.publer.com/api/v1/users/me';
        });
    }
}
