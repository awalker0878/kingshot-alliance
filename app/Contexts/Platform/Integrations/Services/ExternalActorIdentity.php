<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Services;

use App\Contexts\Platform\Integrations\Enums\ExternalActorProvider;
use Illuminate\Validation\ValidationException;

final class ExternalActorIdentity
{
    public static function subjectHash(ExternalActorProvider $provider, string $subject): string
    {
        $normalized = self::normalizeSubject($subject);

        return hash_hmac('sha256', $provider->value.':'.$normalized, self::hashKey());
    }

    public static function subjectHint(string $subject): string
    {
        $normalized = self::normalizeSubject($subject);

        return '…'.substr($normalized, -4);
    }

    public static function pairingCodeHash(string $code): string
    {
        return hash_hmac('sha256', 'pairing:'.self::normalizePairingCode($code), self::hashKey());
    }

    public static function normalizePairingCode(string $code): string
    {
        $normalized = strtoupper(str_replace(['-', ' '], '', trim($code)));
        if (! preg_match('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{12}$/', $normalized)) {
            throw ValidationException::withMessages([
                'code' => 'The pairing code is invalid or incomplete.',
            ]);
        }

        return $normalized;
    }

    public static function formatPairingCode(string $code): string
    {
        $normalized = self::normalizePairingCode($code);

        return implode('-', str_split($normalized, 4));
    }

    private static function normalizeSubject(string $subject): string
    {
        $normalized = trim($subject);
        if (! preg_match('/^[1-9][0-9]{4,24}$/', $normalized)) {
            throw ValidationException::withMessages([
                'external_subject' => 'A stable numeric provider user ID is required.',
            ]);
        }

        return $normalized;
    }

    private static function hashKey(): string
    {
        $key = config('app.key');
        if (! is_string($key) || $key === '') {
            throw new \LogicException('APP_KEY is required for external actor identity hashing.');
        }

        return $key;
    }
}
