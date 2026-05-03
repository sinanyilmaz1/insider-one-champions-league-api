<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulationPlayAllTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_play_all_returns_full_envelope(): void
    {
        // Predictions content is owned by the prediction microservice and tested in
        // PredictionsBffTest. Here we verify the envelope shape and the BE-owned
        // fields (lastPlayedWeek, nextWeek, fixtures count).
        $this->postJson('/api/fixtures/generate')->assertStatus(200);

        $response = $this->postJson('/api/simulation/play-all');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'standings',
            'predictions',
            'fixtures',
            'lastPlayedWeek',
            'nextWeek',
        ]);
        $response->assertJsonPath('lastPlayedWeek', 6);
        $response->assertJsonPath('nextWeek', null);

        $this->assertCount(12, $response->json('fixtures'));
    }

    public function test_play_all_returns_409_when_nothing_to_play(): void
    {
        $this->postJson('/api/fixtures/generate')->assertStatus(200);
        $this->postJson('/api/simulation/play-all')->assertStatus(200);

        $response = $this->postJson('/api/simulation/play-all');

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'All weeks have been played. Reset to play again.');
    }
}
