<?php

declare(strict_types=1);

namespace App\Services\Simulation;

use App\Models\Team;
use Random\Randomizer;

/**
 * SYNCHRONIZATION NOTE
 *
 * This class is intentionally duplicated between:
 *   - insider-one-champions-league-api (this repo)
 *   - insider-one-prediction-service
 *
 * Any change to constants or formula MUST be applied to both repos AND verified
 * by the calibration test in BOTH repos. Drift will cause the prediction
 * microservice's outputs to disagree with this simulator.
 *
 * Last sync: 2026-05-03 — initial Poisson formula with attack/defense/midfield/home_advantage.
 */
final class MatchSimulator
{
    /** Premier League historical avg goals per team per match. */
    private const float LEAGUE_AVG = 1.4;

    /**
     * Midfield contribution to attack/defense.
     * Reflects empirical ~30-35% midfielder goal contribution vs ~50-55% forwards.
     * Calibrated against PL historical averages — see MatchSimulatorCalibrationTest.
     */
    private const float MIDFIELD_OFFENSIVE_WEIGHT = 0.5;

    private const float MIDFIELD_DEFENSIVE_WEIGHT = 0.5;

    public function __construct(
        private readonly Randomizer $rng = new Randomizer,
    ) {}

    public function simulate(Team $home, Team $away): MatchOutcome
    {
        $lambdaHome = $this->computeLambda($home, $away, isHome: true, opponent: $away);
        $lambdaAway = $this->computeLambda($away, $home, isHome: false, opponent: $home);

        return new MatchOutcome(
            homeScore: $this->poissonSample($lambdaHome),
            awayScore: $this->poissonSample($lambdaAway),
        );
    }

    private function computeLambda(Team $attacker, Team $defender, bool $isHome, Team $opponent): float
    {
        $attackStrength = ($attacker->attack + self::MIDFIELD_OFFENSIVE_WEIGHT * $attacker->midfield) / 100.0;
        $defenseStrength = ($defender->defense + self::MIDFIELD_DEFENSIVE_WEIGHT * $defender->midfield) / 100.0;

        $base = self::LEAGUE_AVG * ($attackStrength / $defenseStrength);

        $homeTeamAdv = (float) ($isHome ? $attacker->home_advantage : $opponent->home_advantage);

        return $isHome ? ($base * $homeTeamAdv) : ($base / $homeTeamAdv);
    }

    /**
     * Knuth's algorithm for sampling a Poisson(lambda) integer.
     */
    private function poissonSample(float $lambda): int
    {
        $L = exp(-$lambda);
        $k = 0;
        $p = 1.0;

        do {
            $k++;
            $p *= $this->rng->getFloat(0.0, 1.0);
        } while ($p > $L);

        return $k - 1;
    }
}
