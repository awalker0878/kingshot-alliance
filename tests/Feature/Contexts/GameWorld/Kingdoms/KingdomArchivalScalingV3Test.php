<?php

declare(strict_types=1);

namespace Tests\Feature\Contexts\GameWorld\Kingdoms;

use App\Contexts\GameWorld\Kingdoms\Actions\ArchiveKingdom;
use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceStatus;
use App\Contexts\GameWorld\Kingdoms\Enums\KingdomStatus;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Kingdoms\Models\KingdomAlliance;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class KingdomArchivalScalingV3Test extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string,array{int}> */
    public static function sizes(): iterable
    {
        yield 'empty' => [0];
        yield 'one' => [1];
        yield 'three pages' => [405];
    }

    #[DataProvider('sizes')]
    public function test_archival_uses_bounded_ordered_pages_and_exact_scoped_audit_counts(int $count): void
    {
        $factory = app(ScenarioFactory::class);
        $kingdom = $factory->kingdom(59267);
        $other = $factory->kingdom(59268);
        $ids = $this->children($kingdom->kingdomId, $count);
        $this->children($kingdom->kingdomId, 10, KingdomAllianceStatus::Archived);
        $this->children($other->kingdomId, 15);
        $retrieved = [];
        KingdomAlliance::retrieved(static function (KingdomAlliance $alliance) use (&$retrieved): void {
            $retrieved[] = (string) $alliance->id;
        });
        DB::enableQueryLog();
        DB::flushQueryLog();
        app(ArchiveKingdom::class)->handle($kingdom->kingdomId, reason: 'Lifecycle cleanup');
        $reads = array_values(array_filter(DB::getQueryLog(), static fn (array $query): bool => str_starts_with($query['query'], 'select * from "kingdom_alliances"')));
        DB::disableQueryLog();
        self::assertCount(max(1, (int) ceil($count / 200)), $reads);
        foreach ($reads as $query) {
            self::assertStringContainsString('order by "id" asc limit 200 for update', $query['query']);
        }
        sort($ids, SORT_STRING);
        self::assertSame($ids, $retrieved);
        self::assertSame(0, KingdomAlliance::query()->where('kingdom_id', $kingdom->kingdomId)->where('status', 'active')->count());
        self::assertSame(15, KingdomAlliance::query()->where('kingdom_id', $other->kingdomId)->where('status', 'active')->count());
        self::assertSame($count, AuditEvent::query()->where('event', 'kingdoms.alliance_archived')->count());
        $event = AuditEvent::query()->where('event', 'kingdoms.kingdom_archived')->sole();
        self::assertSame($count, $event->metadata['archived_alliance_count']);
        self::assertSame('Lifecycle cleanup', $event->metadata['reason']);
        $before = $this->state();
        app(ArchiveKingdom::class)->handle($kingdom->kingdomId);
        self::assertSame($before, $this->state());
    }

    public function test_failure_in_a_later_page_rolls_back_the_entire_cascade(): void
    {
        $kingdom = app(ScenarioFactory::class)->kingdom(59267);
        $this->children($kingdom->kingdomId, 405);
        $before = $this->state();
        $archived = 0;
        DB::listen(static function (QueryExecuted $query) use (&$archived): void {
            if (str_starts_with($query->sql, 'insert into "audit_events"') && in_array('kingdoms.alliance_archived', $query->bindings, true) && ++$archived === 201) {
                throw new RuntimeException('Injected second-page audit failure.');
            }
        });
        try {
            app(ArchiveKingdom::class)->handle($kingdom->kingdomId);
            self::fail('Earlier pages must roll back with the failing page.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected second-page audit failure.', $exception->getMessage());
        }
        self::assertSame(201, $archived);
        self::assertSame(KingdomStatus::Active, Kingdom::query()->findOrFail($kingdom->kingdomId)->status);
        self::assertSame($before, $this->state());
    }

    /** @return list<string> */
    private function children(string $kingdomId, int $count, KingdomAllianceStatus $status = KingdomAllianceStatus::Active): array
    {
        $rows = [];
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $id = (string) Str::ulid();
            $ids[] = $id;
            $rows[] = ['id' => $id, 'kingdom_id' => $kingdomId, 'current_name' => 'Tracked Alliance '.$i, 'status' => $status->value, 'created_at' => now(), 'updated_at' => now()];
        }
        if ($rows !== []) {
            DB::table('kingdom_alliances')->insert($rows);
        }

        return $ids;
    }

    /** @return array<string,string> */
    private function state(): array
    {
        return [
            'kingdoms' => DB::table('kingdoms')->orderBy('id')->get()->toJson(),
            'alliances' => DB::table('kingdom_alliances')->orderBy('id')->get()->toJson(),
            'audit' => DB::table('audit_events')->orderBy('id')->get()->toJson(),
        ];
    }
}
