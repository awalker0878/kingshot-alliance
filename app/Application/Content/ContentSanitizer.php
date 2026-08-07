<?php

declare(strict_types=1);

namespace App\Application\Content;

final class ContentSanitizer
{
    public function line(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strip_tags(str_replace("\0", '', $value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    public function body(string $value): string
    {
        $value = strip_tags(str_replace("\0", '', $value));
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/[\t ]+\n/u', "\n", $value) ?? $value;

        return trim($value);
    }
}
