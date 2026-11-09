<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    public function test_missing_token_config_logs_warning_at_most_once_per_hour(): void
    {
        Cache::flush();
        config(['services.internal.token' => '']);
        Log::spy();

        $this->postJson(self::URL, ['bot' => 'GPTBot'])->assertStatus(503);
        $this->postJson(self::URL, ['bot' => 'GPTBot'])->assertStatus(503);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($msg) => str_contains($msg, 'INTERNAL_API_TOKEN'))
            ->once();
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
        Log::spy();

        $this->postJson(self::URL, ['bot' => 'GPTBot'], ['X-Internal-Token' => 'secret-token'])
            ->assertStatus(202);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($msg) => $msg === 'Failed to record crawler hit')
            ->once();
    }

    public function test_middleware_and_endpoint_increment_the_same_row(): void
    {
        $this->setServerToken();

        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; GPTBot/1.2)'])->get('/r/nocode1');
        $this->postJson(self::URL, ['bot' => 'GPTBot'], ['X-Internal-Token' => 'secret-token'])
            ->assertStatus(202);

        $this->assertSame(1, DB::table('geo_crawler_hits')->count());
        $this->assertDatabaseHas('geo_crawler_hits', [
            'date' => now()->toDateString(), 'bot' => 'GPTBot', 'hits' => 2,
        ]);
    }

    public function test_crawler_hits_do_not_consume_contact_throttle(): void
    {
        $this->setServerToken();
        $h = ['X-Internal-Token' => 'secret-token'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(self::URL, ['bot' => 'GPTBot'], $h)->assertStatus(202);
        }

        // /contact allows 3 per 15 min per IP; crawler hits must not eat that bucket.
        $this->assertNotSame(429, $this->postJson('/api/contact', [])->getStatusCode());
    }

    public function test_crawler_limiter_allows_6000_per_minute_per_ip(): void
    {
        $limit = \Illuminate\Support\Facades\RateLimiter::limiter('internal-crawler')(
            \Illuminate\Http\Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1'])
        );
        $this->assertSame(6000, $limit->maxAttempts);
        $this->assertSame('internal-crawler|127.0.0.1', $limit->key);
    }

    public function test_bot_config_shape(): void
    {
        $map = config('ai_bots.map');
        $this->assertIsArray($map);

        $expected = [
            'GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-Web',
            'Claude-User', 'Claude-SearchBot', 'PerplexityBot', 'Perplexity-User',
            'Meta-ExternalAgent', 'Amazonbot', 'Bytespider', 'CCBot',
            'DuckAssistBot', 'MistralAI-User', 'GoogleOther',
        ];
        $this->assertSame($expected, array_keys($map));
        $this->assertSame($expected, array_values($map));

        // robots.txt-only tokens never appear in a User-Agent.
        $this->assertArrayNotHasKey('Google-Extended', $map);
        $this->assertArrayNotHasKey('Applebot-Extended', $map);
    }

    public function test_no_bot_key_is_substring_of_an_earlier_key(): void
    {
        $keys = array_keys(config('ai_bots.map'));
        foreach ($keys as $i => $key) {
            foreach (array_slice($keys, 0, $i) as $earlier) {
                $this->assertFalse(
                    stripos($key, $earlier) !== false || stripos($earlier, $key) !== false,
                    "{$key} and {$earlier} overlap"
                );
            }
        }
    }
}
