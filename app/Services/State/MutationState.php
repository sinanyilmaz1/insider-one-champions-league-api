<?php

namespace App\Services\State;

use App\Models\Fixture;
use App\Services\Predictions\TeamPrediction;
use App\Services\Standings\StandingRow;
use Illuminate\Support\Collection;

final readonly class MutationState
{
    /**
     * @param  list<StandingRow>  $standings
     * @param  list<TeamPrediction>  $predictions
     * @param  Collection<int, Fixture>  $fixtures  with homeTeam, awayTeam, matchResult eager-loaded
     */
    public function __construct(
        public array $standings,
        public array $predictions,
        public Collection $fixtures,
        public ?int $lastPlayedWeek,
        public ?int $nextWeek,
    ) {}
}
