<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_preflight_from_allowed_origin_returns_204_with_cors_headers(): void
    {
        $response = $this->call(
            method: 'OPTIONS',
            uri: '/api/teams',
            server: [
                'HTTP_ORIGIN' => 'http://localhost:5173',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
            ],
        );

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('http://localhost:5173', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_actual_request_from_allowed_origin_includes_allow_origin_header(): void
    {
        $response = $this->getJson('/api/teams', ['Origin' => 'http://localhost:5173']);

        $response->assertOk();
        self::assertSame('http://localhost:5173', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_request_from_disallowed_origin_does_not_echo_that_origin(): void
    {
        // With a single allowed origin, the CORS lib emits Access-Control-Allow-Origin
        // statically. The protection is browser-side: since the response Allow-Origin
        // header (http://localhost:5173) does not match the disallowed request Origin
        // (http://evil.example.com), the browser refuses to expose the response to JS.
        $response = $this->getJson('/api/teams', ['Origin' => 'http://evil.example.com']);

        $response->assertOk();
        self::assertNotSame(
            'http://evil.example.com',
            $response->headers->get('Access-Control-Allow-Origin'),
        );
    }

    public function test_preflight_from_disallowed_origin_does_not_echo_that_origin(): void
    {
        $response = $this->call(
            method: 'OPTIONS',
            uri: '/api/teams',
            server: [
                'HTTP_ORIGIN' => 'http://evil.example.com',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            ],
        );

        self::assertNotSame(
            'http://evil.example.com',
            $response->headers->get('Access-Control-Allow-Origin'),
        );
    }
}
