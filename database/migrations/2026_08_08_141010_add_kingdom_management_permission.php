<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Permission provisioning is centralized in AllianceRoleProvisioner.
        // Alliance R4/R5 grants are evaluated by AllianceRankPermissions.
    }

    public function down(): void
    {
        // No schema or persisted role grant is owned by this migration.
    }
};
