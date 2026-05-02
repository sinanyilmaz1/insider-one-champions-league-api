<?php

namespace App\Http\Resources;

use App\Models\MatchResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MatchResult */
class MatchResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'home_score' => $this->home_score,
            'away_score' => $this->away_score,
        ];
    }
}
