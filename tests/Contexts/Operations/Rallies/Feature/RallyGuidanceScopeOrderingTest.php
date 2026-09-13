<?php

declare(strict_types=1);

namespace Tests\Contexts\Operations\Rallies\Feature;

use App\Contexts\Alliance\Membership\Actions\UpdateAllianceRank;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Kingdoms\Actions\ArchiveKingdom;
use App\Contexts\Operations\Rallies\Actions\SaveRallyGuidanceRule;
use App\Contexts\Operations\Rallies\ValueObjects\FormationComposition;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class RallyGuidanceScopeOrderingTest extends TestCase
{
    use DatabaseTruncation;

    /** @return iterable<string,array{string,bool}> */
    public static function orders(): iterable
    {
        foreach (['rank', 'kingdom'] as $kind) {
            yield $kind.' changes first' => [$kind, true];
            yield $kind.' waits for admitted guidance' => [$kind, false];
        }
    }

    #[DataProvider('orders')]
    public function test_guidance_holds_current_governing_scope_before_actor_in_both_orders(string $kind, bool $withdrawFirst): void
    {
        $factory = app(ScenarioFactory::class);
        $owner = $factory->player((int) $factory->authUser()->id, 61648);
        $alliance = $factory->alliance($owner);
        $actor = $factory->unclaimedPlayer(61648);
        $membership = AllianceMembership::query()->create(['alliance_id' => $alliance->allianceId,
            'player_id' => $actor->playerId, 'rank' => AllianceRank::R4, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $withdraw = static fn () => $kind === 'rank'
            ? app(UpdateAllianceRank::class)->handle($alliance->allianceId, $owner->playerId, (string) $membership->id, AllianceRank::R1)
            : app(ArchiveKingdom::class)->handle($actor->kingdomId, reason: 'Current scope withdrawn.');
        $save = static fn () => app(SaveRallyGuidanceRule::class)->handle($actor->playerId, $alliance->allianceId,
            'Current guidance', new FormationComposition(50, 20, 30));
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.guidance_competitor', [...DB::connection()->getConfig(), 'name' => 'guidance_competitor']);
        $other = DB::connection('guidance_competitor');
        $other->statement("SET lock_timeout = '150ms'");
        $attempted = false;
        $locks = [];
        DB::listen(static function (QueryExecuted $query) use ($primary, $withdrawFirst, $withdraw, &$attempted, &$locks): void {
            if ($query->connectionName !== $primary) {
                return;
            }
            if (str_contains($query->sql, 'for update') || str_contains($query->sql, 'for share')) {
                $locks[] = $query->sql;
            }
            if (! $withdrawFirst && ! $attempted && str_starts_with($query->sql, 'insert into "rally_guidance_rules"')) {
                $attempted = true;
                DB::setDefaultConnection('guidance_competitor');
                try {
                    try {
                        $withdraw();
                        self::fail('The owner change must wait for admitted guidance to commit.');
                    } catch (QueryException $exception) {
                        self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                    }
                } finally {
                    DB::setDefaultConnection($primary);
                }
            }
        });
        try {
            if (! $withdrawFirst) {
                $save();
                self::assertTrue($attempted);
                self::assertSame(1, DB::table('rally_guidance_rules')->count());
                $allianceLock = $this->firstLock($locks, 'alliances');
                $kingdomLock = $this->firstLock($locks, 'kingdoms');
                $playerLock = $this->firstLock($locks, 'players');
                self::assertNotNull($allianceLock);
                self::assertNotNull($kingdomLock);
                self::assertNotNull($playerLock);
                self::assertLessThan($kingdomLock, $allianceLock);
                self::assertLessThan($playerLock, $kingdomLock);
            }
            $withdraw();
            $audit = DB::table('audit_events')->count();
            $outbox = DB::table('outbox_messages')->count();
            try {
                $save();
                self::fail('A withdrawn scope must reject a new guidance command.');
            } catch (AuthorizationException|ModelNotFoundException) {
                self::assertSame($withdrawFirst ? 0 : 1, DB::table('rally_guidance_rules')->count());
            }
            self::assertSame($audit, DB::table('audit_events')->count());
            self::assertSame($outbox, DB::table('outbox_messages')->count());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('guidance_competitor');
        }
    }

    /** @param list<string> $queries */
    private function firstLock(array $queries, string $table): ?int
    {
        foreach ($queries as $index => $sql) {
            if (str_contains($sql, 'from "'.$table.'"')) {
                return $index;
            }
        }

        return null;
    }
}
