<?php

namespace Tests\Feature;

use App\Exceptions\InvalidStateTransitionException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class InvalidStateTransitionHandlerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function api_request_mapping_returns_http_409_with_structured_error(): void
    {
        $request = Request::create('/api/anything', 'GET');
        $request->headers->set('Accept', 'application/json');

        $handler = $this->app->make(ExceptionHandler::class);

        $response = $handler->render(
            $request,
            new InvalidStateTransitionException('Cannot transition draft → completed on idea #1')
        );

        $this->assertSame(409, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame([
            'success' => false,
            'error' => [
                'code' => 'INVALID_STATE_TRANSITION',
                'message' => 'Cannot transition draft → completed on idea #1',
            ],
        ], $body);
    }
}
