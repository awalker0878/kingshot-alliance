<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Adapters\Concerns;

use App\Contexts\GameWorld\GiftCodes\Exceptions\GiftCodeSourceAcquisitionException;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeSourceRateLimit;
use Illuminate\Http\Client\Response;

trait HandlesGiftCodeProviderResponses
{
    private function assertGiftCodeProviderSuccess(Response $response, string $operation): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $failureCode = match (true) {
            $status === 401 => 'authentication_failed',
            $status === 403 => 'permission_revoked',
            $status === 404 => 'source_identity_unavailable',
            $status === 408 => 'provider_timeout',
            $status === 409 => 'provider_conflict',
            $status === 429 => 'rate_limited',
            $status >= 500 => 'provider_unavailable',
            default => 'source_retrieval_failed',
        };

        throw new GiftCodeSourceAcquisitionException(
            failureCode: $failureCode,
            message: sprintf('%s returned HTTP %d.', $operation, $status),
            httpStatus: $status,
            retryAfterSeconds: $this->giftCodeRetryAfterSeconds($response),
            providerRequestId: $this->giftCodeProviderRequestId($response),
        );
    }

    private function giftCodeProviderRequestId(Response $response): ?string
    {
        foreach ([
            'X-Request-Id',
            'X-Request-ID',
            'X-FB-Trace-ID',
            'X-Goog-Request-ID',
            'X-Amzn-RequestId',
            'CF-Ray',
        ] as $header) {
            $value = trim((string) $response->header($header));
            if ($value !== '') {
                return mb_substr($value, 0, 255);
            }
        }

        return null;
    }

    private function giftCodeRateLimit(Response $response): ?GiftCodeSourceRateLimit
    {
        $limit = $this->giftCodeIntegerHeader($response, ['X-RateLimit-Limit', 'X-Ratelimit-Limit']);
        $remaining = $this->giftCodeIntegerHeader($response, ['X-RateLimit-Remaining', 'X-Ratelimit-Remaining']);
        $resetAt = $this->giftCodeIntegerHeader($response, ['X-RateLimit-Reset', 'X-Ratelimit-Reset']);
        $retryAfter = $this->giftCodeRetryAfterSeconds($response);
        $quotaRemaining = $this->giftCodeIntegerHeader($response, [
            'X-Quota-Remaining',
            'X-RateLimit-Resource-Remaining',
        ]);

        if ($limit === null && $remaining === null && $resetAt === null && $retryAfter === null && $quotaRemaining === null) {
            return null;
        }

        return new GiftCodeSourceRateLimit(
            limit: $limit,
            remaining: $remaining,
            resetAtUnix: $resetAt,
            retryAfterSeconds: $retryAfter,
            quotaRemaining: $quotaRemaining,
        );
    }

    private function giftCodeRetryAfterSeconds(Response $response): ?int
    {
        $raw = trim((string) $response->header('Retry-After'));
        if ($raw === '') {
            return null;
        }
        if (ctype_digit($raw)) {
            return max(0, min(86_400, (int) $raw));
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return null;
        }

        return max(0, min(86_400, $timestamp - time()));
    }

    /** @param list<string> $headers */
    private function giftCodeIntegerHeader(Response $response, array $headers): ?int
    {
        foreach ($headers as $header) {
            $raw = trim((string) $response->header($header));
            if ($raw !== '' && preg_match('/^-?[0-9]+$/D', $raw) === 1) {
                return max(0, (int) $raw);
            }
        }

        return null;
    }

    private function giftCodeRetrievalVersion(Response $response): string
    {
        foreach (['ETag', 'Last-Modified'] as $header) {
            $value = trim((string) $response->header($header));
            if ($value !== '') {
                return mb_substr($header.':'.$value, 0, 120);
            }
        }

        return 'sha256:'.hash('sha256', $response->body());
    }
}
