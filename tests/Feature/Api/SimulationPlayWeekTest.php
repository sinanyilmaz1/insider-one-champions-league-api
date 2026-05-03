<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulationPlayWeekTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_play_week_returns_envelope_after_first_call(): void
    {
        $this->postJson('/api/fixtures/generate')->assertStatus(200);

        $response = $this->postJson('/api/simulation/play-week');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'standings',
            'predictions',
            'fixtures',
            'lastPlayedWeek',
            'nextWeek',
        ]);
        $response->assertJsonPath('lastPlayedWeek', 1);
        $response->assertJsonPath('nextWeek', 2);
        $this->assertSame([], $response->json('predictions'));
    }

    public function test_play_week_keeps_predictions_empty_before_week_3(): void
    {
        $this->postJson('/api/fixtures/generate')->assertStatus(200);
        $this->postJson('/api/simulation/play-week')->assertStatus(200);

        $response = $this->postJson('/api/simulation/play-week');

        $response->assertStatus(200);
        $response->assertJsonPath('lastPlayedWeek', 2);
        $this->assertSame([], $response->json('predictions'));
    }

    public function test_play_week_envelope_at_week_3_includes_predictions_key(): void
    {
        // Predictions content (championPercentage values) is owned by the prediction
        // microservice and tested in PredictionsBffTest. Here we only verify the BFF
        // wires the predictions key into the envelope from week 3 onwards.
        $this->postJson('/api/fixtures/generate')->assertStatus(200);
        $this->postJson('/api/simulation/play-week')->assertStatus(200);
        $this->postJson('/api/simulation/play-week')->assertStatus(200);

        $response = $this->postJson('/api/simulation/play-week');

        $response->assertStatus(200);
        $response->assertJsonPath('lastPlayedWeek', 3);
        $response->assertJsonStructure(['predictions']);
    }

    public function test_play_week_returns_409_when_season_complete(): void
    {
        $this->postJson('/api/fixtures/generate')->assertStatus(200);
        $this->postJson('/api/simulation/play-all')->assertStatus(200);

        $response = $this->postJson('/api/simulation/play-week');

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'All weeks have been played. Reset to play again.');
    }
}
