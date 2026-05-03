<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMatchResultRequest;
use App\Http\Resources\MutationStateResource;
use App\Models\MatchResult;
use App\Services\State\MutationStateAssembler;

class MatchResultController extends Controller
{
    public function update(
        UpdateMatchResultRequest $request,
        MatchResult $matchResult,
        MutationStateAssembler $assembler,
    ): MutationStateResource {
        $validated = $request->validated();

        $matchResult->update([
            'home_score' => $validated['homeScore'],
            'away_score' => $validated['awayScore'],
        ]);

        return new MutationStateResource($assembler->assemble());
    }
}
