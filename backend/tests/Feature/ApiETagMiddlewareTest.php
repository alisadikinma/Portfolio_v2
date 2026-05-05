<?php

namespace Tests\Feature;

use App\Http\Middleware\ApiETag;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class ApiETagMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Default APP_URL contains the XAMPP subpath which breaks getJson()
        // routing — same workaround the other Feature tests use (see
        // HomepageApiTest::setUp).
        config(['app.url' => 'http://localhost']);
        url()->forceRootUrl('http://localhost');
    }

    public function test_adds_etag_header_to_a_json_get_response(): void
    {
        $middleware = new ApiETag();
        $request = Request::create('/api/health', 'GET');

        $response = $middleware->handle($request, function () {
            return new Response(json_encode(['status' => 'ok']), 200, [
                'Content-Type' => 'application/json',
            ]);
        });

        $etag = $response->headers->get('ETag');
        $this->assertNotNull($etag);
        $this->assertMatchesRegularExpression('/^W\/"[a-f0-9]{32}"$/', $etag);
    }

    public function test_returns_304_with_empty_body_when_if_none_match_matches(): void
    {
        $middleware = new ApiETag();
        $payload = json_encode(['status' => 'ok']);
        $expectedEtag = 'W/"'.md5($payload).'"';

        $request = Request::create('/api/health', 'GET');
        $request->headers->set('If-None-Match', $expectedEtag);

        $response = $middleware->handle($request, function () use ($payload) {
            return new Response($payload, 200, ['Content-Type' => 'application/json']);
        });

        $this->assertSame(304, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    public function test_returns_200_with_full_body_when_if_none_match_does_not_match(): void
    {
        $middleware = new ApiETag();
        $payload = json_encode(['status' => 'ok']);

        $request = Request::create('/api/health', 'GET');
        $request->headers->set('If-None-Match', '"stale-etag-xyz"');

        $response = $middleware->handle($request, function () use ($payload) {
            return new Response($payload, 200, ['Content-Type' => 'application/json']);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($payload, $response->getContent());
    }

    public function test_matches_wildcard_if_none_match(): void
    {
        $middleware = new ApiETag();
        $request = Request::create('/api/health', 'GET');
        $request->headers->set('If-None-Match', '*');

        $response = $middleware->handle($request, function () {
            return new Response('{"a":1}', 200);
        });

        $this->assertSame(304, $response->getStatusCode());
    }

    public function test_skips_non_get_requests(): void
    {
        $middleware = new ApiETag();
        $request = Request::create('/api/posts', 'POST', [], [], [], [], '{"title":"x"}');

        $response = $middleware->handle($request, function () {
            return new Response('{"created":true}', 201);
        });

        $this->assertNull($response->headers->get('ETag'));
        $this->assertSame(201, $response->getStatusCode());
    }

    public function test_skips_non_2xx_responses(): void
    {
        $middleware = new ApiETag();
        $request = Request::create('/api/missing', 'GET');

        $response = $middleware->handle($request, function () {
            return new Response('{"error":"not found"}', 404);
        });

        $this->assertNull($response->headers->get('ETag'));
    }

    public function test_skips_empty_responses(): void
    {
        $middleware = new ApiETag();
        $request = Request::create('/api/empty', 'GET');

        $response = $middleware->handle($request, function () {
            return new Response('', 204);
        });

        $this->assertNull($response->headers->get('ETag'));
    }

    public function test_skips_responses_larger_than_1mb(): void
    {
        $middleware = new ApiETag();
        $request = Request::create('/api/big', 'GET');

        $bigPayload = str_repeat('x', 1_048_577); // 1MB + 1 byte

        $response = $middleware->handle($request, function () use ($bigPayload) {
            return new Response($bigPayload, 200);
        });

        $this->assertNull($response->headers->get('ETag'));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_middleware_is_registered_in_api_pipeline(): void
    {
        // /api/health response includes a fresh timestamp on every call so
        // the ETag value naturally differs each request — what we verify
        // here is that the middleware is wired into the api pipeline at all
        // (i.e., the ETag header gets attached to the response).
        $response = $this->getJson('/api/health');
        $response->assertStatus(200);

        $etag = $response->headers->get('ETag');
        $this->assertNotNull(
            $etag,
            'ApiETag middleware should attach ETag header to api responses. '
            .'If null, check bootstrap/app.php registration.'
        );
        $this->assertMatchesRegularExpression('/^W\/"[a-f0-9]{32}"$/', $etag);
    }

    public function test_appends_origin_to_vary_header_without_duplicating(): void
    {
        $middleware = new ApiETag();
        $request = Request::create('/api/health', 'GET');

        $response = $middleware->handle($request, function () {
            return new Response('{}', 200, ['Vary' => 'Origin, Accept-Encoding']);
        });

        $vary = $response->headers->get('Vary');
        // Should contain Origin once, not twice
        $this->assertSame(1, substr_count($vary, 'Origin'));
    }
}
