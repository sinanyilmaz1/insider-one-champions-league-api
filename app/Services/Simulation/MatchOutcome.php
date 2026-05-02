<?php

namespace App\Services\Simulation;

final readonly class MatchOutcome
{
    public function __construct(
        public int $homeScore,
        public int $awayScore,
    ) {}
}
