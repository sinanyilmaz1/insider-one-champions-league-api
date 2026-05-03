<?php

namespace App\Http\Controllers;

use App\Http\Resources\MutationStateResource;
use App\Models\Fixture;
use App\Models\MatchResult;
use App\Services\Simulation\MatchSimulator;
use App\Services\State\MutationStateAssembler;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SimulationController extends Controller
{
    public function playWeek(MatchSimulator $simulator, MutationStateAssembler $assembler): JsonResponse|MutationStateResource
    {
        $nextWeek = Fixture::query()
            ->whereDoesntHave('matchResult')
            ->min('week');

        if ($nextWeek === null) {
            return $this->seasonCompleteResponse();
        }

        $fixtures = Fixture::with(['homeTeam', 'awayTeam'])
            ->where('week', (int) $nextWeek)
            ->orderBy('id')
            ->get();

        $this->simulateFixtures($fixtures, $simulator);

        return new MutationStateResource($assembler->assemble());
    }

    public function playAll(MatchSimulator $simulator, MutationStateAssembler $assembler): JsonResponse|MutationStateResource
    {
        $fixtures = Fixture::with(['homeTeam', 'awayTeam'])
            ->whereDoesntHave('matchResult')
            ->orderBy('week')
            ->orderBy('id')
            ->get();

        if ($fixtures->isEmpty()) {
            return $this->seasonCompleteResponse();
        }

        $this->simulateFixtures($fixtures, $simulator);

        return new MutationStateResource($assembler->assemble());
    }

    public function reset(MutationStateAssembler $assembler): MutationStateResource
    {
        DB::transaction(function (): void {
            MatchResult::query()->delete();
            Fixture::query()->delete();
        });

        return new MutationStateResource($assembler->assemble());
    }

    /**
     * @param  EloquentCollection<int, Fixture>  $fixtures
     */
    private function simulateFixtures(EloquentCollection $fixtures, MatchSimulator $simulator): void
    {
        DB::transaction(function () use ($fixtures, $simulator): void {
            foreach ($fixtures as $fixture) {
                $outcome = $simulator->simulate($fixture->homeTeam, $fixture->awayTeam);

                MatchResult::create([
                    'fixture_id' => $fixture->id,
                    'home_score' => $outcome->homeScore,
                    'away_score' => $outcome->awayScore,
                ]);
            }
        });
    }

    private function seasonCompleteResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'All weeks have been played. Reset to play again.',
        ], 409);
    }
}
