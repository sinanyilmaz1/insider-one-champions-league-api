<?php

declare(strict_types=1);

namespace App\Services\Prediction;

use App\Services\Prediction\Exceptions\PredictionServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class PredictionServiceClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function predict(array $payload): array
    {
        $url = rtrim((string) config('services.prediction.url'), '/').'/api/v1/predictions';
        $token = (string) config('services.prediction.auth_token');
        $timeout = (int) config('services.prediction.timeout');

        try {
            $response = Http::withHeaders(['X-Service-Auth' => $token])
                ->timeout($timeout)
                ->retry(1, 200, throw: false)
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            Log::error('Prediction service unreachable', ['error' => $e->getMessage()]);
            throw PredictionServiceException::unreachable($e);
        }

        if ($response->status() === 401) {
            Log::error('Prediction service rejected authentication');
            throw PredictionServiceException::authFailed();
        }

        if ($response->status() === 422) {
            /** @var array<string, mixed> $errors */
            $errors = $response->json('errors') ?? [];
            Log::error('Prediction service rejected payload (contract drift?)', ['errors' => $errors]);
            throw PredictionServiceException::contractMismatch($errors);
        }

        if (! $response->successful()) {
            Log::error('Prediction service returned non-2xx', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw PredictionServiceException::badResponse($response->status(), $response->body());
        }

        /** @var array<string, mixed> $body */
        $body = $response->json();

        return $body;
    }
}
