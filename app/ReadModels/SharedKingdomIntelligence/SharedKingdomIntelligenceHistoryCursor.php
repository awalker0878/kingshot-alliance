<?php

declare(strict_types=1);

namespace App\ReadModels\SharedKingdomIntelligence;

use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final readonly class SharedKingdomIntelligenceHistoryCursor
{
    public function __construct(private ScopedCursorCodec $cursors) {}

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
        $decoded = $this->cursors->decode($cursor, $this->scope($shareTargetId));

        if (! is_string($decoded['as_of'] ?? null)
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
        return $this->cursors->encode($this->scope($shareTargetId), [
            'as_of' => $asOf->copy()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'captured_at' => $capturedAt->copy()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'observation_id' => $observationId,
            'seen' => $seen,
        ]);
    }

    private function scope(string $shareTargetId): string
    {
        return 'shared-kingdom-intelligence-history:'.$shareTargetId;
    }

    private function invalid(): ValidationException
    {
        return ValidationException::withMessages([
            'cursor' => 'The shared-intelligence history cursor is invalid or no longer applicable.',
        ]);
    }
}
