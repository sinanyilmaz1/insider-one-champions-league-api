<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesPredictionService;
use Tests\TestCase;

class SimulationPlayAllTest extends TestCase
{
    use FakesPredictionService;
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakePredictionService();
    }

    public function test_play_all_returns_full_envelope_with_predictions(): void
    {
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
        $this->assertCount(4, $response->json('predictions'));

        $champion = collect($response->json('predictions'))
            ->firstWhere('championPercentage', 100.0);
        $this->assertNotNull($champion);
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
