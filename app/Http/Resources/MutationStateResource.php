<?php

namespace App\Http\Resources;

use App\Services\State\MutationState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MutationState */
class MutationStateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'standings' => StandingRowResource::collection($this->standings),
            'predictions' => TeamPredictionResource::collection($this->predictions),
            'fixtures' => FixtureResource::collection($this->fixtures),
            'lastPlayedWeek' => $this->lastPlayedWeek,
            'nextWeek' => $this->nextWeek,
        ];
    }
}
