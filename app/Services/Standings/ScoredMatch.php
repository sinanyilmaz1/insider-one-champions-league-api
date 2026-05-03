<?php

namespace App\Services\Standings;

final readonly class ScoredMatch
{
    public function __construct(
        public int $homeTeamId,
        public int $awayTeamId,
        public int $homeScore,
        public int $awayScore,
    ) {}
}
