<?php

namespace App\Services\Predictions;

use App\Models\Team;

final readonly class TeamPrediction
{
    public function __construct(
        public Team $team,
        public float $championPercentage,
    ) {}
}
