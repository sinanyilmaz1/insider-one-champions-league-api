<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesPredictionService;
use Tests\TestCase;

class SimulationDeleteTest extends TestCase
{
    use FakesPredictionService;
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakePredictionService();
    }

    public function test_delete_simulation_returns_zeroed_envelope_with_status_200(): void
    {
        $this->postJson('/api/fixtures/generate')->assertStatus(200);
        $this->postJson('/api/simulation/play-all')->assertStatus(200);

        $response = $this->deleteJson('/api/simulation');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'standings',
            'predictions',
            'fixtures',
            'lastPlayedWeek',
            'nextWeek',
        ]);
        $response->assertJsonPath('lastPlayedWeek', null);
        $response->assertJsonPath('nextWeek', null);

        $this->assertSame([], $response->json('fixtures'));
        $this->assertSame([], $response->json('predictions'));
        $this->assertCount(4, $response->json('standings'));

        foreach ($response->json('standings') as $row) {
            $this->assertSame(0, $row['points']);
            $this->assertSame(0, $row['played']);
        }
    }
}
