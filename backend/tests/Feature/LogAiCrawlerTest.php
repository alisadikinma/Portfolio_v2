<?php

namespace Tests\Feature;

use App\Http\Middleware\LogAiCrawler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LogAiCrawlerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Drive the middleware with a spoofed User-Agent. Returns the response so
     * callers can assert it's untouched (the request always passes through).
     */
    private function dispatch(?string $userAgent): Response
    {
        $request = Request::create('/', 'GET');
        if ($userAgent !== null) {
            $request->headers->set('User-Agent', $userAgent);
        }

        return (new LogAiCrawler())->handle($request, function () {
            return new Response('ok', 200);
        });
    }

    public function test_gptbot_request_increments_todays_row(): void
    {
        $today = now()->toDateString();

        $this->dispatch('Mozilla/5.0 (compatible; GPTBot/1.2; +https://openai.com/gptbot)');

        $this->assertDatabaseHas('geo_crawler_hits', [
            'date' => $today,
            'bot' => 'GPTBot',
            'hits' => 1,
        ]);

        // Second hit → 2 (atomic increment on the same daily row).
        $this->dispatch('GPTBot/1.2');

        $this->assertDatabaseHas('geo_crawler_hits', [
            'date' => $today,
            'bot' => 'GPTBot',
            'hits' => 2,
        ]);

        $this->assertSame(1, DB::table('geo_crawler_hits')->count());
    }

    public function test_middleware_is_wired_to_the_web_group(): void
    {
        // Goes through the real HTTP/web stack — catches a bootstrap/app.php
        // misregistration the unit-level dispatch() tests can't. The SSR route
        // may redirect (no dist shell in test), but the web middleware still
        // runs before the controller.
        $this->withHeaders(['User-Agent' => 'GPTBot/1.2'])->get('/');

        $this->assertDatabaseHas('geo_crawler_hits', ['bot' => 'GPTBot']);
    }

    public function test_normal_browser_ua_does_not_create_a_row(): void
    {
        $response = $this->dispatch(
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 '
            .'(KHTML, like Gecko) Chrome/120.0 Safari/537.36'
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(0, DB::table('geo_crawler_hits')->count());
    }

    public function test_db_failure_does_not_break_the_response(): void
    {
        // Force the recording path to throw by removing the target table.
        Schema::dropIfExists('geo_crawler_hits');

        $response = $this->dispatch('ClaudeBot/1.0 (+https://anthropic.com/claudebot)');

        // Fail-open: the crawl response is unaffected even though the write threw.
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }
}
