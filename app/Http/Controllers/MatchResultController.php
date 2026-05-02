<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMatchResultRequest;
use App\Http\Resources\MatchResultResource;
use App\Models\MatchResult;

class MatchResultController extends Controller
{
    public function update(UpdateMatchResultRequest $request, MatchResult $matchResult): MatchResultResource
    {
        $matchResult->update($request->validated());

        return new MatchResultResource($matchResult);
    }
}
