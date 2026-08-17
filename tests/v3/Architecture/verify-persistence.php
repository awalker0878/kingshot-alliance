<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$failures = [];

$check = static function (bool $condition, string $message) use (&$failures): void {
    if (! $condition) {
        $failures[] = $message;
    }
};

$read = static function (string $path) use ($root, &$failures): string {
    $full = $root.'/'.$path;
    if (! is_file($full)) {
        $failures[] = "Missing required persistence definition: {$path}";
        return '';
    }

    return file_get_contents($full) ?: '';
};

$memberships = $read('database/migrations/2026_08_07_000120_create_alliance_memberships_table.php');
$check(str_contains($memberships, "foreignUlid('player_id')->constrained('players')"), 'Alliance membership must be Player-scoped.');
$check(! preg_match("/Schema::create\('alliance_memberships'.*?['\"]user_id['\"]/s", $memberships), 'Alliance membership must not contain user_id authority.');
$check(str_contains($memberships, "unique(['alliance_id', 'player_id'])"), 'Alliance membership uniqueness must be Alliance + Player.');

$kingdomAuthorization = $read('database/migrations/2026_08_13_000100_create_kingdom_authorization_tables.php');
$check(str_contains($kingdomAuthorization, "Schema::create('kingdom_role_assignments'"), 'Kingdom role assignments table must exist.');
$check(str_contains($kingdomAuthorization, "foreignUlid('player_id')->constrained('players')"), 'Kingdom role assignments must be Player-scoped.');
$check(! preg_match("/Schema::create\('kingdom_role_assignments'.*?['\"]user_id['\"]/s", $kingdomAuthorization), 'Kingdom role assignments must not contain user_id authority.');

$players = $read('database/migrations/2026_08_07_000115_create_players_table.php');
$check(str_contains($players, "foreignId('user_id')->nullable()->constrained('users')"), 'Player ownership must retain the Accounts user_id ownership reference.');

$delivery = $read('database/migrations/2026_08_16_000000_create_notification_delivery_tables.php');
foreach (['event_reminder_deliveries', 'king_perk_reminder_deliveries', 'migrateLegacy', 'Schema::hasTable'] as $legacy) {
    $check(! str_contains($delivery, $legacy), "Communications delivery migration still contains clean-room compatibility logic: {$legacy}");
}
$check(str_contains($delivery, "Schema::create('notification_deliveries'"), 'Generic notification_deliveries table must exist.');
$check(str_contains($delivery, "Schema::create('notification_preferences'"), 'Generic notification_preferences table must exist.');

$migrationDir = $root.'/database/migrations';
foreach (glob($migrationDir.'/*.php') ?: [] as $migration) {
    $source = file_get_contents($migration) ?: '';
    if (str_contains($source, 'Schema::hasTable') || preg_match('/\bmigrateLegacy\w*\s*\(/', $source) === 1) {
        $failures[] = 'Clean-room migration set contains compatibility/legacy branching: '.basename($migration);
    }
}

if ($failures !== []) {
    fwrite(STDERR, "V3 persistence verification failed (".count($failures)." violations):\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }
    exit(1);
}

fwrite(STDOUT, "V3 persistence verification passed.\n");
