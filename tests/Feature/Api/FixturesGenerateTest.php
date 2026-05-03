<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesPredictionService;
use Tests\TestCase;

class FixturesGenerateTest extends TestCase
{
    use FakesPredictionService;
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakePredictionService();
    }

    public function test_generate_returns_zeroed_envelope_with_status_200(): void
    {
        $response = $this->postJson('/api/fixtures/generate');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'standings',
            'predictions',
            'fixtures',
            'lastPlayedWeek',
            'nextWeek',
        ]);
        $response->assertJsonPath('lastPlayedWeek', null);
        $response->assertJsonPath('nextWeek', 1);

        $this->assertCount(12, $response->json('fixtures'));
        $this->assertCount(4, $response->json('standings'));
        $this->assertSame([], $response->json('predictions'));

        foreach ($response->json('standings') as $row) {
            $this->assertSame(0, $row['points']);
            $this->assertSame(0, $row['played']);
        }
    }

    public function test_generate_returns_409_when_results_exist(): void
    {
        $this->postJson('/api/fixtures/generate')->assertStatus(200);
        $this->postJson('/api/simulation/play-all')->assertStatus(200);

        $response = $this->postJson('/api/fixtures/generate');

        $response->assertStatus(409);
        $response->assertJsonPath(
            'message',
            'Cannot regenerate fixtures while match results exist. Reset the simulation first.',
        );
    }
}
