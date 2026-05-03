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

    public function test_play_week_populates_predictions_starting_week_3(): void
    {
        $this->postJson('/api/fixtures/generate')->assertStatus(200);
        $this->postJson('/api/simulation/play-week')->assertStatus(200);
        $this->postJson('/api/simulation/play-week')->assertStatus(200);

        $response = $this->postJson('/api/simulation/play-week');

        $response->assertStatus(200);
        $response->assertJsonPath('lastPlayedWeek', 3);
        $this->assertCount(4, $response->json('predictions'));

        $sum = array_sum(array_column($response->json('predictions'), 'championPercentage'));
        $this->assertEqualsWithDelta(100.0, $sum, 0.1);
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
