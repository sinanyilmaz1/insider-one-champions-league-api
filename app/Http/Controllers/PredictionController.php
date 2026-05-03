<?php

namespace App\Http\Controllers;

use App\Http\Resources\TeamPredictionResource;
use App\Services\State\MutationStateAssembler;
use Illuminate\Http\JsonResponse;

class PredictionController extends Controller
{
    public function index(MutationStateAssembler $assembler): JsonResponse
    {
        $state = $assembler->assemble();

        return response()->json([
            'lastPlayedWeek' => $state->lastPlayedWeek,
            'nextWeek' => $state->nextWeek,
            'predictions' => TeamPredictionResource::collection($state->predictions)->resolve(),
        ]);
    }
}
