<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PredictionsBffTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_predictions_endpoint_calls_microservice_and_maps_response(): void
    {
        Http::fake([
            '*/api/v1/predictions' => Http::response([
                'predictions' => [
                    ['teamId' => 1, 'teamName' => 'Chelsea', 'championshipProbability' => 0.45, 'expectedPoints' => 0.0, 'expectedGoalDifference' => 0.0, 'positionDistribution' => []],
                    ['teamId' => 2, 'teamName' => 'Arsenal', 'championshipProbability' => 0.30, 'expectedPoints' => 0.0, 'expectedGoalDifference' => 0.0, 'positionDistribution' => []],
                    ['teamId' => 3, 'teamName' => 'Manchester City', 'championshipProbability' => 0.20, 'expectedPoints' => 0.0, 'expectedGoalDifference' => 0.0, 'positionDistribution' => []],
                    ['teamId' => 4, 'teamName' => 'Liverpool', 'championshipProbability' => 0.05, 'expectedPoints' => 0.0, 'expectedGoalDifference' => 0.0, 'positionDistribution' => []],
                ],
                'metadata' => ['iterations' => 10000, 'algorithm' => 'monte_carlo_v1', 'computedAt' => '2026-05-03T00:00:00Z', 'computeMs' => 100.0],
            ], 200),
        ]);

        $this->postJson('/api/fixtures/generate')->assertStatus(200);
        $this->postJson('/api/simulation/play-week')->assertStatus(200);
        $this->postJson('/api/simulation/play-week')->assertStatus(200);
        $this->postJson('/api/simulation/play-week')->assertStatus(200);

        $response = $this->getJson('/api/predictions');

        $response->assertOk()
            ->assertJsonPath('lastPlayedWeek', 3)
            ->assertJsonPath('predictions.0.team.name', 'Chelsea')
            ->assertJsonPath('predictions.0.championPercentage', 45)
            ->assertJsonPath('predictions.3.team.name', 'Liverpool')
            ->assertJsonPath('predictions.3.championPercentage', 5);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/api/v1/predictions')
                && $request->hasHeader('X-Service-Auth', 'test-secret')
                && is_array($request->data()['teams'])
                && is_array($request->data()['fixtures'])
                && is_array($request->data()['playedResults']);
        });
    }

    public function test_predictions_endpoint_skips_microservice_before_week_3(): void
    {
        Http::fake();

        $this->postJson('/api/fixtures/generate')->assertStatus(200);
        $this->postJson('/api/simulation/play-week')->assertStatus(200);
        $this->postJson('/api/simulation/play-week')->assertStatus(200);

        $response = $this->getJson('/api/predictions');

        $response->assertOk()
            ->assertJsonPath('lastPlayedWeek', 2)
            ->assertJsonPath('predictions', []);

        Http::assertNothingSent();
    }

    public function test_predictions_endpoint_degrades_gracefully_on_microservice_rate_limit(): void
    {
        Http::fake([
            '*/api/v1/predictions' => Http::response(['message' => 'Too many'], 429, ['Retry-After' => '30']),
        ]);

        $this->postJson('/api/fixtures/generate')->assertStatus(200);
        $this->postJson('/api/simulation/play-week')->assertStatus(200);
        $this->postJson('/api/simulation/play-week')->assertStatus(200);
        $this->postJson('/api/simulation/play-week')->assertStatus(200);

        $response = $this->getJson('/api/predictions');

        $response->assertOk()
            ->assertJsonPath('predictions', []);
    }

    public function test_predictions_endpoint_degrades_gracefully_on_microservice_error(): void
    {
        Http::fake([
            '*/api/v1/predictions' => Http::response([], 503),
        ]);

        $this->postJson('/api/fixtures/generate')->assertStatus(200);
        $this->postJson('/api/simulation/play-week')->assertStatus(200);
        $this->postJson('/api/simulation/play-week')->assertStatus(200);
        $this->postJson('/api/simulation/play-week')->assertStatus(200);

        $response = $this->getJson('/api/predictions');

        $response->assertOk()
            ->assertJsonPath('lastPlayedWeek', 3)
            ->assertJsonPath('predictions', []);
    }

    public function test_predictions_endpoint_degrades_gracefully_on_connection_failure(): void
    {
        Http::fake([
            '*/api/v1/predictions' => fn () => throw new ConnectionException('Connection refused'),
        ]);

        $this->postJson('/api/fixtures/generate')->assertStatus(200);
        $this->postJson('/api/simulation/play-week')->assertStatus(200);
        $this->postJson('/api/simulation/play-week')->assertStatus(200);
        $this->postJson('/api/simulation/play-week')->assertStatus(200);

        $response = $this->getJson('/api/predictions');

        $response->assertOk()
            ->assertJsonPath('predictions', []);
    }
}
