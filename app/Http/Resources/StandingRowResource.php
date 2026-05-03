<?php

namespace App\Http\Resources;

use App\Services\Standings\StandingRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StandingRow */
class StandingRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'team' => new TeamResource($this->team),
            'played' => $this->played,
            'won' => $this->won,
            'drawn' => $this->drawn,
            'lost' => $this->lost,
            'goalsFor' => $this->goalsFor,
            'goalsAgainst' => $this->goalsAgainst,
            'goalDifference' => $this->goalDifference,
            'points' => $this->points,
        ];
    }
}
