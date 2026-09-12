<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Services;

use Illuminate\Validation\ValidationException;

final class RecruitmentTextInput
{
    public const NOTE_MAX_LENGTH = 10000;

    public const REASON_MAX_LENGTH = 5000;

    public static function note(string $body): string
    {
        $body = trim($body);
        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Recruitment note text is required.']);
        }
        if (mb_strlen($body) > self::NOTE_MAX_LENGTH) {
            throw ValidationException::withMessages(['body' => 'Recruitment notes must not exceed '.self::NOTE_MAX_LENGTH.' characters.']);
        }

        return $body;
    }

    public static function reason(?string $reason): ?string
    {
        $reason = $reason === null ? null : trim($reason);
        if ($reason === null || $reason === '') {
            return null;
        }
        if (mb_strlen($reason) > self::REASON_MAX_LENGTH) {
            throw ValidationException::withMessages(['reason' => 'Recruitment reasons must not exceed '.self::REASON_MAX_LENGTH.' characters.']);
        }

        return $reason;
    }
}
