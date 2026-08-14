<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Permission provisioning is centralized in AllianceRoleProvisioner.
        // Rank-based grants are evaluated by AllianceRankPermissions.
    }

    public function down(): void
    {
        // No schema or persisted role grant is owned by this migration.
    }
};
