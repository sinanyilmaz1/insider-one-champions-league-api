<?php

declare(strict_types=1);

namespace App\Services\Prediction\Exceptions;

use RuntimeException;
use Throwable;

class PredictionServiceException extends RuntimeException
{
    public static function unreachable(?Throwable $prev = null): self
    {
        return new self('Prediction service is unreachable.', 0, $prev);
    }

    public static function authFailed(): self
    {
        return new self('Prediction service rejected authentication.');
    }

    public static function rateLimited(?int $retryAfter = null): self
    {
        $detail = $retryAfter !== null ? " (retry after {$retryAfter}s)" : '';

        return new self('Prediction service rate-limited the request.'.$detail);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    public static function contractMismatch(array $errors): self
    {
        $detail = json_encode($errors, JSON_UNESCAPED_SLASHES);

        return new self('Prediction service rejected payload (contract mismatch): '.$detail);
    }

    public static function badResponse(int $status, string $body): self
    {
        return new self("Prediction service returned {$status}: {$body}");
    }
}
