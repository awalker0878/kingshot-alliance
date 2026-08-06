<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Operations\RuntimeConfigurationValidator;
use Tests\TestCase;

final class RuntimeConfigurationValidatorTest extends TestCase
{
    public function test_production_rejects_insecure_runtime_configuration(): void
    {
        $this->configureRequiredValues();
        config([
            'app.debug' => true,
            'app.url' => 'http://alliance.example.test',
            'session.secure' => false,
            'database.default' => 'pgsql',
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
            'database.default' => 'pgsql',
            'database.connections.pgsql.sslmode' => 'verify-full',
        ]);

        self::assertSame([], (new RuntimeConfigurationValidator())->errors('production'));
    }

    private function configureRequiredValues(): void
    {
        config([
            'app.key' => 'base64:Wb1PHh03m1vXbmyRxlI+Y96TqgK7Vyt8H/lkQ8o1SP0=',
            'app.url' => 'https://alliance.example.test',
            'database.connections.pgsql.host' => 'database.example.test',
            'database.connections.pgsql.database' => 'kingshot',
            'database.connections.pgsql.username' => 'kingshot',
            'database.connections.pgsql.password' => 'secret',
        ]);
    }
}
