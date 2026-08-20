<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Pagination;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use JsonException;

final class ScopedCursorCodec
{
    private const VERSION = 1;

    /** @param non-empty-array<string, int|string|bool|null> $position */
    public function encode(string $scope, array $position): string
    {
        return Crypt::encryptString(json_encode([
            'v' => self::VERSION,
            'scope' => $scope,
            'position' => $position,
        ], JSON_THROW_ON_ERROR));
    }

    /** @return non-empty-array<string, int|string|bool|null> */
    public function decode(string $cursor, string $scope): array
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode(Crypt::decryptString($cursor), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw $this->invalid();
        }

        if (! is_array($decoded)
            || ($decoded['v'] ?? null) !== self::VERSION
            || ! is_string($decoded['scope'] ?? null)
            || ! hash_equals($scope, $decoded['scope'])
            || ! is_array($decoded['position'] ?? null)
            || $decoded['position'] === []) {
            throw $this->invalid();
        }

        $position = [];
        foreach ($decoded['position'] as $key => $value) {
            if (! is_string($key)
                || $key === ''
                || (! is_string($value) && ! is_int($value) && ! is_bool($value) && $value !== null)) {
                throw $this->invalid();
            }

            $position[$key] = $value;
        }

        /** @var non-empty-array<string, int|string|bool|null> $position */
        return $position;
    }

    private function invalid(): ValidationException
    {
        return ValidationException::withMessages([
            'cursor' => 'The pagination cursor is invalid or no longer applies to this view.',
        ]);
    }
}
