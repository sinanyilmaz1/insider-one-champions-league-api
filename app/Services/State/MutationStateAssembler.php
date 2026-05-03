<?php

namespace App\Services\State;

use App\Models\Fixture;
use App\Models\Team;
use App\Services\Prediction\Exceptions\PredictionServiceException;
use App\Services\Prediction\PredictionServiceClient;
use App\Services\Predictions\TeamPrediction;
use App\Services\Standings\LeagueStandingsCalculator;
use App\Services\Standings\ScoredMatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MutationStateAssembler
{
    private const int MIN_PLAYED_WEEK_FOR_PREDICTIONS = 3;

    private const int MONTE_CARLO_ITERATIONS = 10_000;

    public function __construct(
        private readonly LeagueStandingsCalculator $calculator,
        private readonly PredictionServiceClient $predictionClient,
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

        $unplayedWeeks = $fixtures
            ->filter(fn (Fixture $f) => $f->matchResult === null)
            ->pluck('week');

        $nextWeek = $unplayedWeeks->isNotEmpty()
            ? (int) $unplayedWeeks->min()
            : null;

        $standings = $this->calculator->calculate($teams, $playedMatches);

        $predictions = $this->fetchPredictions($teams, $fixtures, $playedFixtures, $lastPlayedWeek);

        return new MutationState(
            standings: $standings,
            predictions: $predictions,
            fixtures: $fixtures,
            lastPlayedWeek: $lastPlayedWeek,
            nextWeek: $nextWeek,
        );
    }

    /**
     * @param  list<Team>  $teams
     * @param  Collection<int, Fixture>  $fixtures
     * @param  Collection<int, Fixture>  $playedFixtures
     * @return list<TeamPrediction>
     */
    private function fetchPredictions(array $teams, $fixtures, $playedFixtures, ?int $lastPlayedWeek): array
    {
        if ($lastPlayedWeek === null || $lastPlayedWeek < self::MIN_PLAYED_WEEK_FOR_PREDICTIONS) {
            return [];
        }

        $payload = $this->buildPayload($teams, $fixtures, $playedFixtures);

        try {
            $response = $this->predictionClient->predict($payload);
        } catch (PredictionServiceException $e) {
            Log::warning('Prediction service call failed, returning empty predictions', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }

        $teamsById = [];
        foreach ($teams as $team) {
            $teamsById[$team->id] = $team;
        }

        $predictions = [];
        foreach ($response['predictions'] ?? [] as $entry) {
            $teamId = (int) ($entry['teamId'] ?? 0);
            if (! isset($teamsById[$teamId])) {
                continue;
            }
            $probability = (float) ($entry['championshipProbability'] ?? 0.0);

            $predictions[] = new TeamPrediction(
                team: $teamsById[$teamId],
                championPercentage: $probability * 100,
            );
        }

        usort(
            $predictions,
            fn (TeamPrediction $a, TeamPrediction $b) => $b->championPercentage <=> $a->championPercentage,
        );

        return $predictions;
    }

    /**
     * @param  list<Team>  $teams
     * @param  Collection<int, Fixture>  $fixtures
     * @param  Collection<int, Fixture>  $playedFixtures
     * @return array<string, mixed>
     */
    private function buildPayload(array $teams, $fixtures, $playedFixtures): array
    {
        return [
            'teams' => array_map(fn (Team $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'attack' => $t->attack,
                'defense' => $t->defense,
                'midfield' => $t->midfield,
                'homeAdvantage' => (float) $t->home_advantage,
            ], $teams),
            'fixtures' => $fixtures
                ->map(fn (Fixture $f) => [
                    'id' => $f->id,
                    'week' => $f->week,
                    'homeTeamId' => $f->home_team_id,
                    'awayTeamId' => $f->away_team_id,
                ])
                ->values()
                ->all(),
            'playedResults' => $playedFixtures
                ->map(fn (Fixture $f) => [
                    'fixtureId' => $f->id,
                    'homeScore' => $f->matchResult->home_score,
                    'awayScore' => $f->matchResult->away_score,
                ])
                ->values()
                ->all(),
            'iterations' => self::MONTE_CARLO_ITERATIONS,
        ];
    }
}
