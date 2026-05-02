<?php

namespace App\Http\Controllers;

use App\Http\Resources\StandingRowResource;
use App\Models\Fixture;
use App\Models\MatchResult;
use App\Models\Team;
use App\Services\Standings\LeagueStandingsCalculator;
use Illuminate\Http\JsonResponse;

class LeagueController extends Controller
{
    public function standings(LeagueStandingsCalculator $calculator): JsonResponse
    {
        $teams = Team::query()->orderBy('id')->get()->all();
        $results = MatchResult::with('fixture')->get()->all();

        $rows = $calculator->calculate($teams, $results);

        return response()->json([
            'last_played_week' => $this->lastPlayedWeek(),
            'next_week' => $this->nextUnplayedWeek(),
            'standings' => StandingRowResource::collection($rows),
        ]);
    }

    private function lastPlayedWeek(): ?int
    {
        $week = Fixture::query()->whereHas('matchResult')->max('week');

        return $week !== null ? (int) $week : null;
    }

    private function nextUnplayedWeek(): ?int
    {
        $week = Fixture::query()->whereDoesntHave('matchResult')->min('week');

        return $week !== null ? (int) $week : null;
    }
}
