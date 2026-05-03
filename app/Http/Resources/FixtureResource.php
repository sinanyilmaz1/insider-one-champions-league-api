<?php

namespace App\Http\Resources;

use App\Models\Fixture;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Fixture */
class FixtureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'week' => $this->week,
            'homeTeam' => new TeamResource($this->whenLoaded('homeTeam')),
            'awayTeam' => new TeamResource($this->whenLoaded('awayTeam')),
            'matchResult' => MatchResultResource::make($this->whenLoaded('matchResult')),
        ];
    }
}
