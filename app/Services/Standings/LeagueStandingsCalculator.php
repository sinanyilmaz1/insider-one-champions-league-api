<?php

namespace App\Services\Standings;

use App\Models\MatchResult;
use App\Models\Team;

class LeagueStandingsCalculator
{
    private const int WIN_POINTS = 3;

    private const int DRAW_POINTS = 1;

    /**
     * @param  list<Team>  $teams
     * @param  list<MatchResult>  $results  match results with fixture relation eager-loaded
     * @return list<StandingRow>
     */
    public function calculate(array $teams, array $results): array
    {
        $stats = [];
        foreach ($teams as $team) {
            $stats[$team->id] = [
                'team' => $team,
                'played' => 0,
                'won' => 0,
                'drawn' => 0,
                'lost' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'points' => 0,
            ];
        }

        foreach ($results as $result) {
            $homeId = $result->fixture->home_team_id;
            $awayId = $result->fixture->away_team_id;
            $home = $result->home_score;
            $away = $result->away_score;

            $stats[$homeId]['played']++;
            $stats[$awayId]['played']++;
            $stats[$homeId]['goals_for'] += $home;
            $stats[$homeId]['goals_against'] += $away;
            $stats[$awayId]['goals_for'] += $away;
            $stats[$awayId]['goals_against'] += $home;

            if ($home > $away) {
                $stats[$homeId]['won']++;
                $stats[$awayId]['lost']++;
                $stats[$homeId]['points'] += self::WIN_POINTS;
            } elseif ($home < $away) {
                $stats[$awayId]['won']++;
                $stats[$homeId]['lost']++;
                $stats[$awayId]['points'] += self::WIN_POINTS;
            } else {
                $stats[$homeId]['drawn']++;
                $stats[$awayId]['drawn']++;
                $stats[$homeId]['points'] += self::DRAW_POINTS;
                $stats[$awayId]['points'] += self::DRAW_POINTS;
            }
        }

        $rows = [];
        foreach ($stats as $row) {
            $rows[] = new StandingRow(
                team: $row['team'],
                played: $row['played'],
                won: $row['won'],
                drawn: $row['drawn'],
                lost: $row['lost'],
                goalsFor: $row['goals_for'],
                goalsAgainst: $row['goals_against'],
                goalDifference: $row['goals_for'] - $row['goals_against'],
                points: $row['points'],
            );
        }

        usort($rows, function (StandingRow $a, StandingRow $b): int {
            return $b->points <=> $a->points
                ?: $b->goalDifference <=> $a->goalDifference
                ?: $b->goalsFor <=> $a->goalsFor
                ?: $a->team->name <=> $b->team->name;
        });

        return $rows;
    }
}
