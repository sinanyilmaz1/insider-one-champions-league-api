<?php

namespace App\Services\Predictions;

use App\Models\Fixture;
use App\Models\Team;
use App\Services\Simulation\MatchSimulator;
use App\Services\Standings\LeagueStandingsCalculator;
use App\Services\Standings\ScoredMatch;

class ChampionshipPredictor
{
    public function __construct(
        private readonly MatchSimulator $simulator,
        private readonly LeagueStandingsCalculator $calculator,
        private readonly int $iterations = 10000,
    ) {}

    /**
     * @param  list<Team>  $teams
     * @param  list<ScoredMatch>  $playedMatches
     * @param  list<Fixture>  $unplayedFixtures  with homeTeam and awayTeam loaded
     * @return list<TeamPrediction>
     */
    public function predict(array $teams, array $playedMatches, array $unplayedFixtures): array
    {
        $wins = [];
        foreach ($teams as $team) {
            $wins[$team->id] = 0;
        }

        for ($i = 0; $i < $this->iterations; $i++) {
            $simulated = [];
            foreach ($unplayedFixtures as $fixture) {
                $outcome = $this->simulator->simulate($fixture->homeTeam, $fixture->awayTeam);
                $simulated[] = new ScoredMatch(
                    homeTeamId: $fixture->home_team_id,
                    awayTeamId: $fixture->away_team_id,
                    homeScore: $outcome->homeScore,
                    awayScore: $outcome->awayScore,
                );
            }

            $standings = $this->calculator->calculate($teams, [...$playedMatches, ...$simulated]);
            $champion = $standings[0]->team;
            $wins[$champion->id]++;
        }

        $predictions = [];
        foreach ($teams as $team) {
            $predictions[] = new TeamPrediction(
                team: $team,
                championPercentage: ($wins[$team->id] / $this->iterations) * 100,
            );
        }

        usort(
            $predictions,
            fn (TeamPrediction $a, TeamPrediction $b) => $b->championPercentage <=> $a->championPercentage,
        );

        return $predictions;
    }
}
