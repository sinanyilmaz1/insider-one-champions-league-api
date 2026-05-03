<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Support\Facades\Http;

trait FakesPredictionService
{
    /**
     * Fake the prediction microservice with a deterministic response.
     * Defaults to Chelsea (id=1) at 100%, others at 0% — satisfies both
     * "sum=100" and "champion=100" assertions in existing tests.
     *
     * @param  array<int, array{teamId:int,teamName:string,championshipProbability:float}>|null  $predictions
     */
    protected function fakePredictionService(?array $predictions = null): void
    {
        $predictions ??= [
            ['teamId' => 1, 'teamName' => 'Chelsea',         'championshipProbability' => 1.0],
            ['teamId' => 2, 'teamName' => 'Arsenal',         'championshipProbability' => 0.0],
            ['teamId' => 3, 'teamName' => 'Manchester City', 'championshipProbability' => 0.0],
            ['teamId' => 4, 'teamName' => 'Liverpool',       'championshipProbability' => 0.0],
        ];

        $entries = array_map(fn (array $p): array => array_merge($p, [
            'expectedPoints' => 0.0,
            'expectedGoalDifference' => 0.0,
            'positionDistribution' => [],
        ]), $predictions);

        Http::fake([
            '*/api/v1/predictions' => Http::response([
                'predictions' => $entries,
                'metadata' => [
                    'iterations' => 10000,
                    'algorithm' => 'monte_carlo_v1',
                    'computedAt' => '2026-05-03T00:00:00Z',
                    'computeMs' => 1.0,
                ],
            ], 200),
        ]);
    }
}
