<?php

declare(strict_types=1);

namespace App\Application\Operations;

final class RuntimeConfigurationValidator
{
    /**
     * @return list<string>
     */
    public function errors(string $environment): array
    {
        $required = config('operations.required_configuration', []);

        if (! is_array($required)) {
            return ['The required runtime configuration list is invalid.'];
        }

        $errors = [];

        foreach ($required as $key) {
            if (is_string($key) && blank(config($key))) {
                $errors[] = "Missing required runtime configuration: {$key}.";
            }
        }

        if (! in_array($environment, ['staging', 'production'], true)) {
            return $errors;
        }

        $version = trim((string) config('operations.version'));
        $releaseSha = trim((string) config('operations.release_sha'));

        if ($version === '' || $version === 'dev') {
            $errors[] = 'Hosted releases must declare a non-placeholder application version.';
        }

        if (preg_match('/^[a-f0-9]{40}$/', $releaseSha) !== 1) {
            $errors[] = 'Hosted releases must declare a 40-character lowercase Git release SHA.';
        }

        if (! $this->hasValidApplicationKey((string) config('app.key'))) {
            $errors[] = 'Hosted releases must use a valid 32-byte AES-256 application key.';
        }

        if (config('database.default') !== 'pgsql') {
            $errors[] = 'Hosted releases must use PostgreSQL as the default database connection.';
        }

        if (config('cache.default') !== 'redis') {
            $errors[] = 'Hosted releases must use Redis as the default cache store.';
        }

        if (config('queue.default') !== 'redis') {
            $errors[] = 'Hosted releases must use Redis as the default queue connection.';
        }

        if (config('session.driver') !== 'redis') {
            $errors[] = 'Hosted releases must use Redis-backed sessions.';
        }

        if (config('session.encrypt') !== true) {
            $errors[] = 'Hosted session payloads must be encrypted.';
        }

        $sameSite = strtolower((string) config('session.same_site'));
        if (! in_array($sameSite, ['lax', 'strict'], true)) {
            $errors[] = 'Hosted session cookies must use lax or strict SameSite protection.';
        }

        $trustedProxies = trim((string) config('operations.trusted_proxies'));
        if ($trustedProxies === '*' && config('operations.allow_trust_all_proxies') !== true) {
            $errors[] = 'Trusting every proxy requires explicit ALLOW_TRUST_ALL_PROXIES approval.';
        }

        if ($environment !== 'production') {
            return $errors;
        }

        if ((bool) config('app.debug')) {
            $errors[] = 'Production debugging must be disabled.';
        }

        $appUrl = (string) config('app.url');
        if (strtolower((string) parse_url($appUrl, PHP_URL_SCHEME)) !== 'https') {
            $errors[] = 'Production APP_URL must use HTTPS.';
        }

        if (config('session.secure') !== true) {
            $errors[] = 'Production session cookies must be secure.';
        }

        $sslMode = strtolower((string) config('database.connections.pgsql.sslmode'));
        if (! in_array($sslMode, ['require', 'verify-ca', 'verify-full'], true)) {
            $errors[] = 'Production PostgreSQL must require an encrypted connection.';
        }

        return $errors;
    }

    private function hasValidApplicationKey(string $key): bool
    {
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            return is_string($decoded) && strlen($decoded) === 32;
        }

        return strlen($key) === 32;
    }
}
