<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')
            ->where('key', 'kingdoms.manage')
            ->value('id');

        if ($permissionId === null) {
            $permissionId = (string) Str::ulid();
            DB::table('permissions')->insert([
                'id' => $permissionId,
                'key' => 'kingdoms.manage',
                'description' => 'Manage the alliance game roster, membership links, and roster observations.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $rows = [];
        foreach (DB::table('roles')->whereIn('key', ['owner', 'leader', 'officer'])->pluck('id') as $roleId) {
            $rows[] = [
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ];
        }

        if ($rows !== []) {
            DB::table('role_permissions')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('key', 'kingdoms.manage')
            ->value('id');

        if ($permissionId === null) {
            return;
        }

        DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
