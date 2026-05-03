<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Tighten the limits so we can exhaust the buckets quickly.
        RateLimiter::for('api-read', fn (Request $r) => Limit::perMinute(3)->by((string) $r->ip()));
        RateLimiter::for('api-mutate', fn (Request $r) => Limit::perMinute(2)->by((string) $r->ip()));
    }

    public function test_read_endpoints_return_429_after_exhausting_the_bucket(): void
    {
        $this->getJson('/api/teams')->assertOk();
        $this->getJson('/api/teams')->assertOk();
        $this->getJson('/api/teams')->assertOk();
        $this->getJson('/api/teams')->assertStatus(429);
    }

    public function test_mutate_endpoints_return_429_after_exhausting_the_bucket(): void
    {
        $this->postJson('/api/fixtures/generate')->assertStatus(200);
        $this->postJson('/api/simulation/play-week')->assertStatus(200);
        $this->postJson('/api/simulation/play-week')->assertStatus(429);
    }

    public function test_responses_include_rate_limit_headers(): void
    {
        $response = $this->getJson('/api/teams');
        $response->assertOk();
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }

    public function test_read_and_mutate_buckets_are_independent(): void
    {
        // Exhaust read bucket
        $this->getJson('/api/teams')->assertOk();
        $this->getJson('/api/teams')->assertOk();
        $this->getJson('/api/teams')->assertOk();
        $this->getJson('/api/teams')->assertStatus(429);

        // Mutate bucket is untouched
        $this->postJson('/api/fixtures/generate')->assertStatus(200);
    }
}
