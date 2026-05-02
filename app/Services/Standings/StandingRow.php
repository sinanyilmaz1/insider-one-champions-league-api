<?php

namespace App\Services\Standings;

use App\Models\Team;

final readonly class StandingRow
{
    public function __construct(
        public Team $team,
        public int $played,
        public int $won,
        public int $drawn,
        public int $lost,
        public int $goalsFor,
        public int $goalsAgainst,
        public int $goalDifference,
        public int $points,
    ) {}
}
