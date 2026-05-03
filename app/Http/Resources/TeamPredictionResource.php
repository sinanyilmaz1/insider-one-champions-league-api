<?php

namespace App\Http\Resources;

use App\Services\Predictions\TeamPrediction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TeamPrediction */
class TeamPredictionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'team' => new TeamResource($this->team),
            'champion_percentage' => round($this->championPercentage, 2),
        ];
    }
}
