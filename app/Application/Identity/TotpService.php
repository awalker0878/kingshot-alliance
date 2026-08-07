<?php

declare(strict_types=1);

namespace App\Application\Identity;

use InvalidArgumentException;

final class TotpService
{
    private const string ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $bytes = 20): string
    {
        if ($bytes < 16) {
            throw new InvalidArgumentException('TOTP secrets must contain at least 128 bits of entropy.');
        }

        return $this->base32Encode(random_bytes($bytes));
    }

    public function provisioningUri(string $account, string $secret, string $issuer): string
    {
        $label = rawurlencode($issuer.':'.$account);

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            $label,
            rawurlencode($secret),
            rawurlencode($issuer),
        );
    }

    public function verify(string $secret, string $code, ?int $timestamp = null, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $counter = intdiv($timestamp ?? time(), 30);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->codeForCounter($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    public function codeForCounter(string $secret, int $counter): string
    {
        if ($counter < 0) {
            throw new InvalidArgumentException('TOTP counter must not be negative.');
        }

        $key = $this->base32Decode($secret);
        $binaryCounter = pack('N2', intdiv($counter, 0x100000000), $counter & 0xFFFFFFFF);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $binary): string
    {
        $buffer = 0;
        $bits = 0;
        $encoded = '';

        foreach (unpack('C*', $binary) ?: [] as $byte) {
            $buffer = ($buffer << 8) | $byte;
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $encoded .= self::ALPHABET[($buffer >> $bits) & 31];
            }
        }

        if ($bits > 0) {
            $encoded .= self::ALPHABET[($buffer << (5 - $bits)) & 31];
        }

        return $encoded;
    }

    private function base32Decode(string $encoded): string
    {
        $encoded = strtoupper(str_replace([' ', '-', '='], '', $encoded));
        $buffer = 0;
        $bits = 0;
        $binary = '';

        foreach (str_split($encoded) as $character) {
            $value = strpos(self::ALPHABET, $character);

            if ($value === false) {
                throw new InvalidArgumentException('Invalid base32 TOTP secret.');
            }

            $buffer = ($buffer << 5) | $value;
            $bits += 5;

            if ($bits >= 8) {
                $bits -= 8;
                $binary .= chr(($buffer >> $bits) & 0xFF);
            }
        }

        return $binary;
    }
}
