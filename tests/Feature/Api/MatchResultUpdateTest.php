<?php

namespace Tests\Feature\Api;

use App\Models\MatchResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesPredictionService;
use Tests\TestCase;

class MatchResultUpdateTest extends TestCase
{
    use FakesPredictionService;
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakePredictionService();
    }

    public function test_update_returns_envelope_reflecting_the_edit(): void
    {
        $this->postJson('/api/fixtures/generate')->assertStatus(200);
        $this->postJson('/api/simulation/play-all')->assertStatus(200);

        $matchResult = MatchResult::query()->firstOrFail();

        $response = $this->putJson("/api/match-results/{$matchResult->id}", [
            'homeScore' => 50,
            'awayScore' => 0,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'standings',
            'predictions',
            'fixtures',
            'lastPlayedWeek',
            'nextWeek',
        ]);

        $matchResult->refresh();
        $this->assertSame(50, $matchResult->home_score);
        $this->assertSame(0, $matchResult->away_score);

        $totalGoalsFor = array_sum(array_column($response->json('standings'), 'goalsFor'));
        $this->assertGreaterThanOrEqual(50, $totalGoalsFor);
    }

    public function test_update_returns_422_for_invalid_body(): void
    {
        $this->postJson('/api/fixtures/generate')->assertStatus(200);
        $this->postJson('/api/simulation/play-all')->assertStatus(200);

        $matchResult = MatchResult::query()->firstOrFail();

        $response = $this->putJson("/api/match-results/{$matchResult->id}", [
            'homeScore' => -5,
            'awayScore' => 1000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['homeScore', 'awayScore']);
    }

    public function test_update_returns_422_for_snake_case_keys(): void
    {
        $this->postJson('/api/fixtures/generate')->assertStatus(200);
        $this->postJson('/api/simulation/play-all')->assertStatus(200);

        $matchResult = MatchResult::query()->firstOrFail();

        $response = $this->putJson("/api/match-results/{$matchResult->id}", [
            'home_score' => 1,
            'away_score' => 2,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['homeScore', 'awayScore']);
    }

    public function test_update_returns_404_for_unknown_id(): void
    {
        $response = $this->putJson('/api/match-results/9999', [
            'homeScore' => 1,
            'awayScore' => 2,
        ]);

        $response->assertStatus(404);
    }
}
