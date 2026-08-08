<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kingdoms', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->integer('number')->unique();
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
        });

        Schema::table('alliances', function (Blueprint $table): void {
            $table->foreignUlid('kingdom_id')
                ->nullable()
                ->constrained('kingdoms')
                ->restrictOnDelete();
        });

        /** @var array<int, string> $kingdomIds */
        $kingdomIds = [];
        $alliances = DB::table('alliances')
            ->select(['id', 'kingdom'])
            ->orderBy('id')
            ->get();

        foreach ($alliances as $alliance) {
            $number = $this->normalizeLegacyKingdom($alliance->kingdom ?? null, (string) $alliance->id);

            if ($number === null) {
                continue;
            }

            if (!isset($kingdomIds[$number])) {
                $existing = DB::table('kingdoms')->where('number', $number)->value('id');

                if (is_string($existing) && $existing !== '') {
                    $kingdomIds[$number] = $existing;
                } else {
                    $kingdomId = (string) Str::ulid();
                    $now = now();

                    DB::table('kingdoms')->insert([
                        'id' => $kingdomId,
                        'number' => $number,
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $kingdomIds[$number] = $kingdomId;
                }
            }

            DB::table('alliances')
                ->where('id', $alliance->id)
                ->update(['kingdom_id' => $kingdomIds[$number]]);
        }

        Schema::table('alliances', function (Blueprint $table): void {
            $table->dropColumn('kingdom');
        });
    }

    public function down(): void
    {
        Schema::table('alliances', function (Blueprint $table): void {
            $table->string('kingdom', 64)->nullable();
        });

        $alliances = DB::table('alliances')
            ->leftJoin('kingdoms', 'kingdoms.id', '=', 'alliances.kingdom_id')
            ->select(['alliances.id', 'kingdoms.number'])
            ->orderBy('alliances.id')
            ->get();

        foreach ($alliances as $alliance) {
            DB::table('alliances')
                ->where('id', $alliance->id)
                ->update([
                    'kingdom' => $alliance->number === null ? null : (string) $alliance->number,
                ]);
        }

        Schema::table('alliances', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('kingdom_id');
        });

        Schema::dropIfExists('kingdoms');
    }

    private function normalizeLegacyKingdom(mixed $value, string $allianceId): ?int
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        if (!preg_match('/^(?:(?:kingdom|k)\s*#?\s*)?([0-9]+)$/i', $raw, $matches)) {
            throw new \RuntimeException(
                "Alliance {$allianceId} has a legacy kingdom value that cannot be normalized safely.",
            );
        }

        $digits = ltrim($matches[1], '0');
        $digits = $digits === '' ? '0' : $digits;

        if (strlen($digits) > 10) {
            throw new \RuntimeException(
                "Alliance {$allianceId} has a legacy kingdom number outside the supported range.",
            );
        }

        $number = (int) $digits;

        if ($number < 1 || $number > 2_147_483_647) {
            throw new \RuntimeException(
                "Alliance {$allianceId} has a legacy kingdom number outside the supported range.",
            );
        }

        return $number;
    }
};
