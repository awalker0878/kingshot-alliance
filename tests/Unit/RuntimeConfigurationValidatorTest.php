<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Operations\RuntimeConfigurationValidator;
use Tests\TestCase;

final class RuntimeConfigurationValidatorTest extends TestCase
{
    public function test_staging_rejects_placeholder_release_metadata(): void
    {
        $this->configureRequiredValues();
        config([
            'operations.version' => 'dev',
            'operations.release_sha' => 'local',
        ]);

        $errors = (new RuntimeConfigurationValidator())->errors('staging');

        self::assertContains(
            'Hosted releases must declare a non-placeholder application version.',
            $errors,
        );
        self::assertContains(
            'Hosted releases must declare a 40-character lowercase Git release SHA.',
            $errors,
        );
    }

    public function test_hosted_environments_reject_insecure_architecture_overrides(): void
    {
        $this->configureRequiredValues();
        config([
            'app.key' => 'invalid',
            'database.default' => 'sqlite',
            'cache.default' => 'file',
            'queue.default' => 'sync',
            'session.driver' => 'file',
            'session.encrypt' => false,
            'session.same_site' => 'none',
            'operations.trusted_proxies' => '*',
            'operations.allow_trust_all_proxies' => false,
        ]);

        $errors = (new RuntimeConfigurationValidator())->errors('staging');

        self::assertContains('Hosted releases must use a valid 32-byte AES-256 application key.', $errors);
        self::assertContains('Hosted releases must use PostgreSQL as the default database connection.', $errors);
        self::assertContains('Hosted releases must use Redis as the default cache store.', $errors);
        self::assertContains('Hosted releases must use Redis as the default queue connection.', $errors);
        self::assertContains('Hosted releases must use Redis-backed sessions.', $errors);
        self::assertContains('Hosted session payloads must be encrypted.', $errors);
        self::assertContains('Hosted session cookies must use lax or strict SameSite protection.', $errors);
        self::assertContains(
            'Trusting every proxy requires explicit ALLOW_TRUST_ALL_PROXIES approval.',
            $errors,
        );
    }

    public function test_hosted_environments_accept_an_explicitly_approved_trust_all_proxy_boundary(): void
    {
        $this->configureRequiredValues();
        config([
            'operations.trusted_proxies' => '*',
            'operations.allow_trust_all_proxies' => true,
        ]);

        self::assertSame([], (new RuntimeConfigurationValidator())->errors('staging'));
    }

    public function test_production_rejects_insecure_runtime_configuration(): void
    {
        $this->configureRequiredValues();
        config([
            'app.debug' => true,
            'app.url' => 'http://alliance.example.test',
            'session.secure' => false,
            'database.connections.pgsql.sslmode' => 'prefer',
        ]);

        $errors = (new RuntimeConfigurationValidator())->errors('production');

        self::assertContains('Production debugging must be disabled.', $errors);
        self::assertContains('Production APP_URL must use HTTPS.', $errors);
        self::assertContains('Production session cookies must be secure.', $errors);
        self::assertContains('Production PostgreSQL must require an encrypted connection.', $errors);
    }

    public function test_production_accepts_secure_runtime_configuration(): void
    {
        $this->configureRequiredValues();
        config([
            'app.debug' => false,
            'app.url' => 'https://alliance.example.test',
            'session.secure' => true,
            'database.connections.pgsql.sslmode' => 'verify-full',
        ]);

        self::assertSame([], (new RuntimeConfigurationValidator())->errors('production'));
    }

    private function configureRequiredValues(): void
    {
        config([
            'app.key' => 'base64:Wb1PHh03m1vXbmyRxlI+Y96TqgK7Vyt8H/lkQ8o1SP0=',
            'app.url' => 'https://alliance.example.test',
            'database.default' => 'pgsql',
            'database.connections.pgsql.host' => 'database.example.test',
            'database.connections.pgsql.database' => 'kingshot',
            'database.connections.pgsql.username' => 'kingshot',
            'database.connections.pgsql.password' => 'secret',
            'database.connections.pgsql.sslmode' => 'verify-full',
            'cache.default' => 'redis',
            'queue.default' => 'redis',
            'session.driver' => 'redis',
            'session.encrypt' => true,
            'session.same_site' => 'lax',
            'session.secure' => true,
            'operations.version' => 'v0.1.0',
            'operations.release_sha' => str_repeat('a', 40),
            'operations.trusted_proxies' => '',
            'operations.allow_trust_all_proxies' => false,
        ]);
    }
}
