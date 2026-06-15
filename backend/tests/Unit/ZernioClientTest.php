<?php

namespace Tests\Unit;

use App\Exceptions\ZernioApiException;
use App\Models\Setting;
use App\Services\ZernioClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase D — ZernioClient: per-platform key routing (two workspaces) + createPost
 * + listAccounts + 409-as-duplicate + 401/403-throws.
 */
class ZernioClientTest extends TestCase
{
    use RefreshDatabase;

    private function seedKeys(): void
    {
        Setting::create(['group' => 'zernio', 'key' => 'zernio_api_key_igtt', 'value' => Crypt::encryptString('sk_igtt_key'), 'type' => 'text']);
        Setting::create(['group' => 'zernio', 'key' => 'zernio_api_key_threads', 'value' => Crypt::encryptString('sk_threads_key'), 'type' => 'text']);
    }

    public function test_instagram_and_tiktok_use_igtt_key(): void
    {
        $this->seedKeys();
        Http::fake(['zernio.com/api/v1/posts' => Http::response(['post' => ['_id' => 'z1']], 201)]);

        (new ZernioClient)->forPlatform('instagram')->createPost(['content' => 'hi']);
        (new ZernioClient)->forPlatform('tiktok')->createPost(['content' => 'hi']);

        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer sk_igtt_key')
            && str_starts_with($r->url(), 'https://zernio.com/api/v1/posts'));
    }

    public function test_threads_uses_threads_key(): void
    {
        $this->seedKeys();
        Http::fake(['zernio.com/api/v1/posts' => Http::response(['post' => ['_id' => 'z2']], 201)]);

        (new ZernioClient)->forPlatform('threads')->createPost(['content' => 'hi']);

        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer sk_threads_key'));
    }

    public function test_create_post_sends_x_request_id_when_given(): void
    {
        $this->seedKeys();
        Http::fake(['zernio.com/api/v1/posts' => Http::response(['post' => ['_id' => 'z3']], 201)]);

        (new ZernioClient)->forPlatform('instagram')->createPost(['content' => 'hi'], 'req-uuid-123');

        Http::assertSent(fn ($r) => $r->hasHeader('x-request-id', 'req-uuid-123'));
    }

    public function test_409_returns_duplicate_result_not_exception(): void
    {
        $this->seedKeys();
        Http::fake(['zernio.com/api/v1/posts' => Http::response([
            'error' => 'duplicate',
            'details' => ['existingPostId' => 'z-existing'],
        ], 409)]);

        $result = (new ZernioClient)->forPlatform('instagram')->createPost(['content' => 'dup']);

        $this->assertTrue($result['duplicate']);
        $this->assertSame('z-existing', $result['existingPostId']);
    }

    public function test_401_throws_zernio_api_exception(): void
    {
        $this->seedKeys();
        Http::fake(['zernio.com/api/v1/posts' => Http::response(['error' => 'Unauthorized'], 401)]);

        $this->expectException(ZernioApiException::class);
        (new ZernioClient)->forPlatform('instagram')->createPost(['content' => 'x']);
    }

    public function test_list_accounts_hits_accounts_endpoint(): void
    {
        $this->seedKeys();
        Http::fake(['zernio.com/api/v1/accounts' => Http::response(['accounts' => [['_id' => 'a1', 'platform' => 'instagram']]], 200)]);

        $accounts = (new ZernioClient)->forPlatform('instagram')->listAccounts();

        $this->assertSame('a1', $accounts[0]['_id']);
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://zernio.com/api/v1/accounts'));
    }

    public function test_missing_key_throws(): void
    {
        // No keys seeded.
        $this->expectException(ZernioApiException::class);
        (new ZernioClient)->forPlatform('instagram')->createPost(['content' => 'x']);
    }
}
