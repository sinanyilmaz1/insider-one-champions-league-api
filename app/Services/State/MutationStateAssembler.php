<?php

namespace App\Services\State;

use App\Models\Fixture;
use App\Models\Team;
use App\Services\Predictions\ChampionshipPredictor;
use App\Services\Standings\LeagueStandingsCalculator;
use App\Services\Standings\ScoredMatch;

class MutationStateAssembler
{
    private const int MIN_PLAYED_WEEK_FOR_PREDICTIONS = 3;

    public function __construct(
        private readonly LeagueStandingsCalculator $calculator,
        private readonly ChampionshipPredictor $predictor,
    ) {}

    public function assemble(): MutationState
    {
        $teams = Team::query()->orderBy('id')->get()->all();

        $fixtures = Fixture::with(['homeTeam', 'awayTeam', 'matchResult'])
            ->orderBy('week')
            ->orderBy('id')
            ->get();

        $playedFixtures = $fixtures
            ->filter(fn (Fixture $f) => $f->matchResult !== null)
            ->values();

        $unplayedFixtures = $fixtures
            ->filter(fn (Fixture $f) => $f->matchResult === null)
            ->values()
            ->all();

        $playedMatches = $playedFixtures
            ->map(fn (Fixture $f) => new ScoredMatch(
                homeTeamId: $f->home_team_id,
                awayTeamId: $f->away_team_id,
                homeScore: $f->matchResult->home_score,
                awayScore: $f->matchResult->away_score,
            ))
            ->all();

        $lastPlayedWeek = $playedFixtures->isNotEmpty()
            ? (int) $playedFixtures->max('week')
            : null;

        $nextWeek = $unplayedFixtures !== []
            ? min(array_map(fn (Fixture $f) => (int) $f->week, $unplayedFixtures))
            : null;

        $standings = $this->calculator->calculate($teams, $playedMatches);

        $predictions = $lastPlayedWeek !== null && $lastPlayedWeek >= self::MIN_PLAYED_WEEK_FOR_PREDICTIONS
            ? $this->predictor->predict($teams, $playedMatches, $unplayedFixtures)
            : [];

        return new MutationState(
            standings: $standings,
            predictions: $predictions,
            fixtures: $fixtures,
            lastPlayedWeek: $lastPlayedWeek,
            nextWeek: $nextWeek,
        );
    }
}
