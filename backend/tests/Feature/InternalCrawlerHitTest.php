<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InternalCrawlerHitTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/internal/crawler-hit';

    private function setServerToken(string $token = 'secret-token'): void
    {
        config(['services.internal.token' => $token]);
    }

    public function test_valid_token_records_hits_and_increments(): void
    {
        $this->setServerToken();
        $h = ['X-Internal-Token' => 'secret-token'];

        $this->postJson(self::URL, ['bot' => 'GPTBot', 'path' => '/id/faq'], $h)->assertStatus(202);
        $this->assertDatabaseHas('geo_crawler_hits', [
            'date' => now()->toDateString(), 'bot' => 'GPTBot', 'hits' => 1,
        ]);

        $this->postJson(self::URL, ['bot' => 'GPTBot', 'path' => '/id/faq'], $h)->assertStatus(202);
        $this->assertDatabaseHas('geo_crawler_hits', ['bot' => 'GPTBot', 'hits' => 2]);
        $this->assertSame(1, DB::table('geo_crawler_hits')->count());
    }

    public function test_wrong_token_is_401(): void
    {
        $this->setServerToken();
        $this->postJson(self::URL, ['bot' => 'GPTBot'], ['X-Internal-Token' => 'nope'])->assertStatus(401);
        $this->assertSame(0, DB::table('geo_crawler_hits')->count());
    }

    public function test_missing_token_is_401(): void
    {
        $this->setServerToken();
        $this->postJson(self::URL, ['bot' => 'GPTBot'])->assertStatus(401);
    }

    public function test_unconfigured_server_token_is_503(): void
    {
        config(['services.internal.token' => '']);
        $this->postJson(self::URL, ['bot' => 'GPTBot'], ['X-Internal-Token' => ''])
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'INTERNAL_TOKEN_NOT_CONFIGURED');

        config(['services.internal.token' => null]);
        $this->postJson(self::URL, ['bot' => 'GPTBot'], ['X-Internal-Token' => 'x'])->assertStatus(503);
    }

    public function test_unknown_bot_is_422(): void
    {
        $this->setServerToken();
        $h = ['X-Internal-Token' => 'secret-token'];
        $this->postJson(self::URL, ['bot' => 'EvilBot'], $h)->assertStatus(422);
        $this->postJson(self::URL, [], $h)->assertStatus(422);
        $this->postJson(self::URL, ['bot' => ['GPTBot']], $h)->assertStatus(422);
        $this->assertSame(0, DB::table('geo_crawler_hits')->count());
    }

    public function test_db_failure_is_fail_open_202(): void
    {
        $this->setServerToken();
        Schema::dropIfExists('geo_crawler_hits');
        $this->postJson(self::URL, ['bot' => 'GPTBot'], ['X-Internal-Token' => 'secret-token'])
            ->assertStatus(202);
    }

    public function test_bot_config_matches_middleware_list(): void
    {
        $map = config('ai_bots.map');
        $this->assertIsArray($map);
        $this->assertArrayHasKey('GPTBot', $map);
        $this->assertCount(8, $map);
    }
}
