<?php

namespace App\Http\Controllers;

use App\Http\Resources\FixtureResource;
use App\Models\Fixture;
use App\Models\MatchResult;
use App\Services\Simulation\MatchSimulator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SimulationController extends Controller
{
    public function playWeek(MatchSimulator $simulator): JsonResponse
    {
        $nextWeek = Fixture::query()
            ->whereDoesntHave('matchResult')
            ->min('week');

        if ($nextWeek === null) {
            return response()->json([
                'message' => 'All weeks have been played. Reset to play again.',
            ], 409);
        }

        $playedWeek = (int) $nextWeek;

        $fixtures = Fixture::with(['homeTeam', 'awayTeam'])
            ->where('week', $playedWeek)
            ->orderBy('id')
            ->get();

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

        $playedFixtures = Fixture::with(['homeTeam', 'awayTeam', 'matchResult'])
            ->where('week', $playedWeek)
            ->orderBy('id')
            ->get();

        $nextWeekAfter = Fixture::query()
            ->whereDoesntHave('matchResult')
            ->min('week');

        return response()->json([
            'played_week' => $playedWeek,
            'last_played_week' => $playedWeek,
            'next_week' => $nextWeekAfter !== null ? (int) $nextWeekAfter : null,
            'fixtures' => FixtureResource::collection($playedFixtures),
        ]);
    }
}
