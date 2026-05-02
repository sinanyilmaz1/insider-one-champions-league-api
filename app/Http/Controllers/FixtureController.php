<?php

namespace App\Http\Controllers;

use App\Http\Resources\FixtureResource;
use App\Models\Fixture;
use App\Models\MatchResult;
use App\Models\Team;
use App\Services\Fixtures\FixtureGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FixtureController extends Controller
{
    public function generate(FixtureGenerator $generator): JsonResponse
    {
        if (MatchResult::query()->exists()) {
            return response()->json([
                'message' => 'Cannot regenerate fixtures while match results exist. Reset the simulation first.',
            ], 409);
        }

        $teams = Team::query()->orderBy('id')->get()->all();
        $pairs = $generator->generate($teams);

        DB::transaction(function () use ($pairs): void {
            Fixture::query()->delete();

            foreach ($pairs as $pair) {
                Fixture::create([
                    'week' => $pair->week,
                    'home_team_id' => $pair->home->id,
                    'away_team_id' => $pair->away->id,
                ]);
            }
        });

        $fixtures = Fixture::with(['homeTeam', 'awayTeam'])
            ->orderBy('week')
            ->orderBy('id')
            ->get();

        return FixtureResource::collection($fixtures)
            ->response()
            ->setStatusCode(201);
    }
}
