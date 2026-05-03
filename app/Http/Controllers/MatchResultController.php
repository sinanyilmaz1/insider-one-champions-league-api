<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMatchResultRequest;
use App\Http\Resources\MatchResultResource;
use App\Models\MatchResult;

class MatchResultController extends Controller
{
    public function update(UpdateMatchResultRequest $request, MatchResult $matchResult): MatchResultResource
    {
        $validated = $request->validated();

        $matchResult->update([
            'home_score' => $validated['homeScore'],
            'away_score' => $validated['awayScore'],
        ]);

        return new MatchResultResource($matchResult);
    }
}
