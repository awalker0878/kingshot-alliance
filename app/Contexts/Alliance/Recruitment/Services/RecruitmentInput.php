<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Services;

use App\Contexts\Alliance\Recruitment\Enums\RecruitmentQuestionType;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RecruitmentInput
{
    public const LIMITS = [
        'title' => 160,
        'introduction' => 5000,
        'prompt' => 240,
        'helpText' => 2000,
        'options' => 30,
        'option' => 160,
        'position' => 65535,
        'retentionDays' => 3650,
        'templateName' => 120,
        'subject' => 200,
        'body' => 10000,
        'onboardingName' => 160,
        'description' => 5000,
        'fullName' => 160,
        'email' => 320,
        'contactHandle' => 160,
        'source' => 120,
        'shortAnswer' => 240,
        'longAnswer' => 10000,
        'inviteHours' => 720,
    ];

    public static function requiredText(string $value, string $field, int $limit): string
    {
        $clean = self::optionalText($value, $field, $limit);
        if ($clean === null) {
            throw ValidationException::withMessages([$field => 'This field is required.']);
        }

        return $clean;
    }

    public static function optionalText(?string $value, string $field, int $limit): ?string
    {
        $clean = $value === null ? null : trim($value);
        if ($clean === null || $clean === '') {
            return null;
        }
        if (mb_strlen($clean) > $limit) {
            throw ValidationException::withMessages([$field => 'Use no more than '.$limit.' characters.']);
        }

        return $clean;
    }

    public static function email(string $value): string
    {
        $clean = Str::lower(self::requiredText($value, 'email', self::LIMITS['email']));
        if (! filter_var($clean, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'A valid email address is required.']);
        }

        return $clean;
    }

    public static function position(int $position): void
    {
        if ($position < 0 || $position > self::LIMITS['position']) {
            throw ValidationException::withMessages(['position' => 'Choose a position from 0 to '.self::LIMITS['position'].'.']);
        }
    }

    /**
     * @param  array<array-key,mixed>  $options
     * @return list<string>
     */
    public static function options(array $options, RecruitmentQuestionType $type): array
    {
        if (! array_is_list($options) || count($options) > self::LIMITS['options']) {
            throw ValidationException::withMessages(['options' => 'Supply a list of up to '.self::LIMITS['options'].' options.']);
        }
        $clean = [];
        foreach ($options as $index => $option) {
            if (! is_string($option)) {
                throw ValidationException::withMessages(['options.'.$index => 'Each option must be text.']);
            }
            $value = self::optionalText($option, 'options.'.$index, self::LIMITS['option']);
            if ($value !== null && ! in_array($value, $clean, true)) {
                $clean[] = $value;
            }
        }
        if (in_array($type, [RecruitmentQuestionType::Select, RecruitmentQuestionType::MultiSelect], true) && $clean === []) {
            throw ValidationException::withMessages(['options' => 'Select recruitment questions require at least one option.']);
        }

        return $clean;
    }
}
