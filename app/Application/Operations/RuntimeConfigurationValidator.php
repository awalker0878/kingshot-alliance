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

        if (in_array($environment, ['staging', 'production'], true)) {
            $version = trim((string) config('operations.version'));
            $releaseSha = trim((string) config('operations.release_sha'));

            if ($version === '' || $version === 'dev') {
                $errors[] = 'Hosted releases must declare a non-placeholder application version.';
            }

            if (preg_match('/^[a-f0-9]{40}$/', $releaseSha) !== 1) {
                $errors[] = 'Hosted releases must declare a 40-character lowercase Git release SHA.';
            }
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

        if (config('database.default') === 'pgsql') {
            $sslMode = strtolower((string) config('database.connections.pgsql.sslmode'));

            if (! in_array($sslMode, ['require', 'verify-ca', 'verify-full'], true)) {
                $errors[] = 'Production PostgreSQL must require an encrypted connection.';
            }
        }

        return $errors;
    }
}
