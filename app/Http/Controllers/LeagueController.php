<?php

namespace App\Http\Controllers;

use App\Http\Resources\StandingRowResource;
use App\Models\Fixture;
use App\Models\MatchResult;
use App\Models\Team;
use App\Services\Standings\LeagueStandingsCalculator;
use App\Services\Standings\ScoredMatch;
use Illuminate\Http\JsonResponse;

class LeagueController extends Controller
{
    public function standings(LeagueStandingsCalculator $calculator): JsonResponse
    {
        $teams = Team::query()->orderBy('id')->get()->all();
        $matches = MatchResult::with('fixture')
            ->get()
            ->map(fn (MatchResult $r) => new ScoredMatch(
                homeTeamId: $r->fixture->home_team_id,
                awayTeamId: $r->fixture->away_team_id,
                homeScore: $r->home_score,
                awayScore: $r->away_score,
            ))
            ->all();

        $rows = $calculator->calculate($teams, $matches);

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
