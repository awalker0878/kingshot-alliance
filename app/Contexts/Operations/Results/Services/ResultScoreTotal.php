<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Services;

use Brick\Math\BigInteger;
use Illuminate\Validation\ValidationException;

/** Exact recorded scores cross JSON as numbers only within JavaScript's safe range. */
final class ResultScoreTotal
{
    public static function fromDatabase(mixed $value): int|string|null
    {
        if ($value === null) {
            return null;
        }
        if ((! is_int($value) && ! is_string($value)) || ! preg_match('/^(0|[1-9][0-9]*)$/D', (string) $value)) {
            throw ValidationException::withMessages(['score' => 'The recorded score total must be a nonnegative integer.']);
        }

        return self::forJson(BigInteger::of($value));
    }

    public static function forJson(BigInteger $value): int|string
    {
        return $value->abs()->isLessThanOrEqualTo('9007199254740991') ? $value->toInt() : (string) $value;
    }
}
