<?php

namespace App\Http\Controllers;

use App\Http\Resources\FixtureResource;
use App\Models\Fixture;
use App\Models\MatchResult;
use App\Services\Simulation\MatchSimulator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class SimulationController extends Controller
{
    public function playWeek(MatchSimulator $simulator): JsonResponse
    {
        $nextWeek = Fixture::query()
            ->whereDoesntHave('matchResult')
            ->min('week');

        if ($nextWeek === null) {
            return $this->seasonCompleteResponse();
        }

        $playedWeek = (int) $nextWeek;

        $fixtures = Fixture::with(['homeTeam', 'awayTeam'])
            ->where('week', $playedWeek)
            ->orderBy('id')
            ->get();

        $this->simulateFixtures($fixtures, $simulator);

        $playedFixtures = Fixture::with(['homeTeam', 'awayTeam', 'matchResult'])
            ->where('week', $playedWeek)
            ->orderBy('id')
            ->get();

        return response()->json([
            'playedWeek' => $playedWeek,
            'lastPlayedWeek' => $playedWeek,
            'nextWeek' => $this->nextUnplayedWeek(),
            'fixtures' => FixtureResource::collection($playedFixtures),
        ]);
    }

    public function reset(): Response
    {
        DB::transaction(function (): void {
            MatchResult::query()->delete();
            Fixture::query()->delete();
        });

        return response()->noContent();
    }

    public function playAll(MatchSimulator $simulator): JsonResponse
    {
        $fixtures = Fixture::with(['homeTeam', 'awayTeam'])
            ->whereDoesntHave('matchResult')
            ->orderBy('week')
            ->orderBy('id')
            ->get();

        if ($fixtures->isEmpty()) {
            return $this->seasonCompleteResponse();
        }

        $playedWeeks = $fixtures->pluck('week')->unique()->sort()->values()->all();

        $this->simulateFixtures($fixtures, $simulator);

        $playedFixtures = Fixture::with(['homeTeam', 'awayTeam', 'matchResult'])
            ->whereIn('week', $playedWeeks)
            ->orderBy('week')
            ->orderBy('id')
            ->get();

        return response()->json([
            'playedWeeks' => array_map('intval', $playedWeeks),
            'lastPlayedWeek' => (int) end($playedWeeks),
            'nextWeek' => $this->nextUnplayedWeek(),
            'fixtures' => FixtureResource::collection($playedFixtures),
        ]);
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

    private function nextUnplayedWeek(): ?int
    {
        $week = Fixture::query()
            ->whereDoesntHave('matchResult')
            ->min('week');

        return $week !== null ? (int) $week : null;
    }

    private function seasonCompleteResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'All weeks have been played. Reset to play again.',
        ], 409);
    }
}
