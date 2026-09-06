<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

final class GiftCodeSourceIdentityPolicy
{
    public function hostMatches(?string $canonicalDomain, string $url): bool
    {
        if ($canonicalDomain === null || trim($canonicalDomain) === '') {
            return false;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host)) {
            return false;
        }
        $host = mb_strtolower(rtrim($host, '.'));
        $canonical = mb_strtolower(rtrim(trim($canonicalDomain), '.'));

        return $host === $canonical || str_ends_with($host, '.'.$canonical);
    }
}
