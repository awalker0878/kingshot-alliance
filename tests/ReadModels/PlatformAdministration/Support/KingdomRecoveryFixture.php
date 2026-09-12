<?php

declare(strict_types=1);

namespace Tests\ReadModels\PlatformAdministration\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class KingdomRecoveryFixture
{
    /** @return list<string> */
    public static function kingdoms(int $count = 261, int $start = 63000): array
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = ['id' => strtolower((string) Str::ulid()), 'number' => $start + $i,
                'status' => 'active', 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('kingdoms')->insert($rows);

        return array_column($rows, 'id');
    }

    /** @return list<string> */
    public static function players(string $kingdom, int $count = 1001): array
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = ['id' => strtolower((string) Str::ulid()), 'current_kingdom_id' => $kingdom,
                'current_name' => sprintf('Recovery governor %04d', $i), 'game_player_id' => null,
                'created_at' => now(), 'updated_at' => now()];
        }
        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('players')->insert($chunk);
        }

        return array_column($rows, 'id');
    }
}
