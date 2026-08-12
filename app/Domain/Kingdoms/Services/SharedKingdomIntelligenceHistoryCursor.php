<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use JsonException;

final class SharedKingdomIntelligenceHistoryCursor
{
    private const VERSION = 1;

    /**
     * @return array{
     *   as_of: Carbon,
     *   captured_at: Carbon,
     *   observation_id: string,
     *   seen: int
     * }
     */
    public function decode(string $cursor, string $shareTargetId): array
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode(Crypt::decryptString($cursor), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw $this->invalid();
        }

        if (! is_array($decoded)
            || ($decoded['v'] ?? null) !== self::VERSION
            || ! is_string($decoded['target'] ?? null)
            || ! hash_equals($shareTargetId, $decoded['target'])
            || ! is_string($decoded['as_of'] ?? null)
            || ! is_string($decoded['captured_at'] ?? null)
            || ! is_string($decoded['observation_id'] ?? null)
            || ! is_int($decoded['seen'] ?? null)
            || $decoded['seen'] < 1
            || $decoded['seen'] > 250) {
            throw $this->invalid();
        }

        try {
            $asOf = Carbon::parse($decoded['as_of'])->utc();
            $capturedAt = Carbon::parse($decoded['captured_at'])->utc();
        } catch (\Throwable) {
            throw $this->invalid();
        }

        if ($capturedAt->isAfter($asOf)) {
            throw $this->invalid();
        }

        return [
            'as_of' => $asOf,
            'captured_at' => $capturedAt,
            'observation_id' => $decoded['observation_id'],
            'seen' => $decoded['seen'],
        ];
    }

    public function encode(
        string $shareTargetId,
        Carbon $asOf,
        Carbon $capturedAt,
        string $observationId,
        int $seen,
    ): string {
        return Crypt::encryptString(json_encode([
            'v' => self::VERSION,
            'target' => $shareTargetId,
            'as_of' => $asOf->copy()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'captured_at' => $capturedAt->copy()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'observation_id' => $observationId,
            'seen' => $seen,
        ], JSON_THROW_ON_ERROR));
    }

    private function invalid(): ValidationException
    {
        return ValidationException::withMessages([
            'cursor' => 'The shared-intelligence history cursor is invalid or no longer applicable.',
        ]);
    }
}
