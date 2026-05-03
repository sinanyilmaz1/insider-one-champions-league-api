<?php

namespace App\Http\Controllers;

use App\Http\Resources\TeamPredictionResource;
use App\Models\Fixture;
use App\Models\MatchResult;
use App\Models\Team;
use App\Services\Predictions\ChampionshipPredictor;
use App\Services\Standings\ScoredMatch;
use Illuminate\Http\JsonResponse;

class PredictionController extends Controller
{
    private const int MIN_PLAYED_WEEK = 3;

    public function index(ChampionshipPredictor $predictor): JsonResponse
    {
        $lastPlayedWeek = $this->lastPlayedWeek();
        $nextWeek = $this->nextUnplayedWeek();

        if ($lastPlayedWeek === null || $lastPlayedWeek < self::MIN_PLAYED_WEEK) {
            return response()->json([
                'lastPlayedWeek' => $lastPlayedWeek,
                'nextWeek' => $nextWeek,
                'predictions' => [],
            ]);
        }

        $teams = Team::query()->orderBy('id')->get()->all();

        $playedMatches = MatchResult::with('fixture')
            ->get()
            ->map(fn (MatchResult $r) => new ScoredMatch(
                homeTeamId: $r->fixture->home_team_id,
                awayTeamId: $r->fixture->away_team_id,
                homeScore: $r->home_score,
                awayScore: $r->away_score,
            ))
            ->all();

        $unplayedFixtures = Fixture::with(['homeTeam', 'awayTeam'])
            ->whereDoesntHave('matchResult')
            ->orderBy('week')
            ->orderBy('id')
            ->get()
            ->all();

        $predictions = $predictor->predict($teams, $playedMatches, $unplayedFixtures);

        return response()->json([
            'lastPlayedWeek' => $lastPlayedWeek,
            'nextWeek' => $nextWeek,
            'predictions' => TeamPredictionResource::collection($predictions),
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
