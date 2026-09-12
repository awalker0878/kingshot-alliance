<?php

declare(strict_types=1);

namespace Tests\Contexts\Platform\AllianceAdministration\Feature;

use App\Contexts\Platform\AllianceAdministration\Services\PlatformUsageService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class PlatformUsageProgressTest extends TestCase
{
    use DatabaseTruncation;

    public function test_bounded_batches_visit_later_alliances_and_wrap_after_the_frontier(): void
    {
        $ids = $this->alliances(5);
        $usage = app(PlatformUsageService::class);
        self::assertSame(2, $usage->captureAll(2));
        self::assertSame(2, $usage->captureAll(2));
        self::assertSame(1, $usage->captureAll(2));
        self::assertSame($ids, DB::table('alliance_usage_snapshots')->orderBy('alliance_id')->pluck('alliance_id')->all());
        // Retention must not reset traversal; a frontier is not a snapshot FK.
        DB::table('alliance_usage_snapshots')->delete();
        self::assertSame(2, $usage->captureAll(2));
        self::assertSame(array_slice($ids, 0, 2), DB::table('alliance_usage_snapshots')->orderBy('alliance_id')->pluck('alliance_id')->all());
        self::assertSame([1, 1], DB::table('alliance_usage_snapshots')->pluck('active_members')->all());
    }

    public function test_failed_batch_rolls_back_snapshots_and_frontier_before_retry(): void
    {
        $ids = $this->alliances(3);
        $usage = app(PlatformUsageService::class);
        $usage->captureAll(1);
        $inserts = 0;
        DB::listen(static function (QueryExecuted $query) use (&$inserts): void {
            if (str_starts_with($query->sql, 'insert into "alliance_usage_snapshots"') && ++$inserts === 2) {
                throw new RuntimeException('Injected capture failure.');
            }
        });
        try {
            $usage->captureAll(2);
            self::fail('The second snapshot must fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected capture failure.', $exception->getMessage());
        }
        self::assertSame(1, DB::table('alliance_usage_snapshots')->count());
        self::assertSame($ids[0], DB::table('alliance_usage_capture_state')->value('last_alliance_id'));
        self::assertSame(2, $usage->captureAll(2));
        self::assertSame($ids, DB::table('alliance_usage_snapshots')->orderBy('alliance_id')->pluck('alliance_id')->all());
    }

    public function test_overlapping_capture_skips_the_locked_batch(): void
    {
        $this->alliances(2);
        DB::table('alliance_usage_capture_state')->insert(['id' => 'scheduled']);
        config()->set('database.connections.usage_competitor', DB::connection()->getConfig());
        $other = DB::connection('usage_competitor');
        try {
            $other->beginTransaction();
            $other->table('alliance_usage_capture_state')->where('id', 'scheduled')->lockForUpdate()->first();
            self::assertSame(0, app(PlatformUsageService::class)->captureAll(2));
            self::assertSame(0, DB::table('alliance_usage_snapshots')->count());
            $other->rollBack();
            self::assertSame(2, app(PlatformUsageService::class)->captureAll(2));
        } finally {
            if ($other->transactionLevel() > 0) {
                $other->rollBack();
            }
            DB::purge('usage_competitor');
        }
    }

    /** @return list<string> */
    private function alliances(int $count): array
    {
        $factory = app(ScenarioFactory::class);
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = $factory->alliance($factory->player($factory->account()->userId))->allianceId;
        }
        sort($ids);

        return $ids;
    }
}
