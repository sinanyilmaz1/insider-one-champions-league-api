<?php

namespace App\Services\Simulation;

use App\Models\Team;
use Random\Randomizer;

class MatchSimulator
{
    private const float AVG_STRENGTH = 5.5;

    public function __construct(
        private readonly Randomizer $randomizer,
        private readonly float $baseGoals = 1.4,
        private readonly float $homeBoost = 1.3,
    ) {}

    public function simulate(Team $home, Team $away): MatchOutcome
    {
        $homeLambda = $this->baseGoals * ($this->strengthProxy($home) / self::AVG_STRENGTH) * $this->homeBoost;
        $awayLambda = $this->baseGoals * ($this->strengthProxy($away) / self::AVG_STRENGTH);

        return new MatchOutcome(
            homeScore: $this->poisson($homeLambda),
            awayScore: $this->poisson($awayLambda),
        );
    }

    /**
     * Temporary strength-equivalent derived from `attack` so this simulator keeps
     * working until PR 2 replaces the formula with the Poisson model that consumes
     * attack/defense/midfield/home_advantage directly.
     *
     * @deprecated Removed in PR 2 (feat/poisson-match-simulator).
     */
    private function strengthProxy(Team $team): int
    {
        return (int) round($team->attack / 10);
    }

    /**
     * Knuth's algorithm for sampling a Poisson(lambda) integer.
     */
    private function poisson(float $lambda): int
    {
        $L = exp(-$lambda);
        $k = 0;
        $p = 1.0;

        do {
            $k++;
            $p *= $this->randomizer->getFloat(0.0, 1.0);
        } while ($p > $L);

        return $k - 1;
    }
}
