<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Prediction;

use App\Services\Prediction\Exceptions\PredictionServiceException;
use App\Services\Prediction\PredictionServiceClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PredictionServiceClientTest extends TestCase
{
    private function client(): PredictionServiceClient
    {
        return new PredictionServiceClient;
    }

    #[Test]
    public function returns_decoded_body_on_success(): void
    {
        Http::fake([
            '*/api/v1/predictions' => Http::response([
                'predictions' => [['teamId' => 1, 'championshipProbability' => 0.5]],
                'metadata' => ['algorithm' => 'monte_carlo_v1'],
            ], 200),
        ]);

        $result = $this->client()->predict(['teams' => []]);

        self::assertSame('monte_carlo_v1', $result['metadata']['algorithm']);
        self::assertSame(1, $result['predictions'][0]['teamId']);
    }

    #[Test]
    public function sends_x_service_auth_header_from_config(): void
    {
        Http::fake([
            '*/api/v1/predictions' => Http::response(['predictions' => [], 'metadata' => []], 200),
        ]);

        $this->client()->predict(['teams' => []]);

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Service-Auth', config('services.prediction.auth_token'))
                && $request->hasHeader('Content-Type', 'application/json');
        });
    }

    #[Test]
    public function throws_auth_failed_on_401(): void
    {
        Http::fake([
            '*/api/v1/predictions' => Http::response(['message' => 'Invalid'], 401),
        ]);

        $this->expectException(PredictionServiceException::class);
        $this->expectExceptionMessage('rejected authentication');

        $this->client()->predict([]);
    }

    #[Test]
    public function throws_contract_mismatch_on_422(): void
    {
        Http::fake([
            '*/api/v1/predictions' => Http::response([
                'message' => 'Invalid data',
                'errors' => ['teams.0.attack' => ['must be between 1 and 100']],
            ], 422),
        ]);

        try {
            $this->client()->predict([]);
            self::fail('Expected exception not thrown');
        } catch (PredictionServiceException $e) {
            self::assertStringContainsString('contract mismatch', $e->getMessage());
            self::assertStringContainsString('teams.0.attack', $e->getMessage());
        }
    }

    #[Test]
    public function throws_bad_response_on_5xx(): void
    {
        Http::fake([
            '*/api/v1/predictions' => Http::response('Server error', 500),
        ]);

        $this->expectException(PredictionServiceException::class);
        $this->expectExceptionMessage('500');

        $this->client()->predict([]);
    }

    #[Test]
    public function throws_unreachable_on_connection_exception(): void
    {
        Http::fake([
            '*/api/v1/predictions' => fn () => throw new ConnectionException('Connection refused'),
        ]);

        $this->expectException(PredictionServiceException::class);
        $this->expectExceptionMessage('unreachable');

        $this->client()->predict([]);
    }

    #[Test]
    public function posts_payload_to_v1_predictions_endpoint(): void
    {
        Http::fake([
            '*/api/v1/predictions' => Http::response(['predictions' => [], 'metadata' => []], 200),
        ]);

        $this->client()->predict([
            'teams' => [['id' => 1, 'name' => 'Chelsea']],
            'iterations' => 5000,
        ]);

        Http::assertSent(function ($request) {
            return str_ends_with($request->url(), '/api/v1/predictions')
                && $request->method() === 'POST'
                && $request->data()['iterations'] === 5000;
        });
    }
}
