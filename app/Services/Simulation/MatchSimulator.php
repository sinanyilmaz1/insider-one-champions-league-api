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
        $homeLambda = $this->baseGoals * ($home->strength / self::AVG_STRENGTH) * $this->homeBoost;
        $awayLambda = $this->baseGoals * ($away->strength / self::AVG_STRENGTH);

        return new MatchOutcome(
            homeScore: $this->poisson($homeLambda),
            awayScore: $this->poisson($awayLambda),
        );
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
