<?php

declare(strict_types=1);

namespace Tests\Contexts\Operations\Events\Feature;

use App\Contexts\GameWorld\Governance\Actions\AssignKingdomRole;
use App\Contexts\GameWorld\Governance\Actions\RemoveKingdomRole;
use App\Contexts\Operations\Events\Actions\CancelEvent;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\KingPerks\Services\KingPerkScheduler;
use App\Contexts\Operations\TerritoryPlanning\Actions\ArchiveTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\CreateTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class KingdomScopeOrderingTest extends TestCase
{
    use DatabaseTruncation;

    /** @return iterable<string,array{string,bool}> */
    public static function orders(): iterable
    {
        foreach (['event-create', 'event-cancel', 'territory-create', 'territory-archive', 'king-perk-create'] as $operation) {
            yield $operation.' admitted first' => [$operation, false];
            yield $operation.' after revocation' => [$operation, true];
        }
    }

    #[DataProvider('orders')]
    public function test_scope_precedes_actor_and_operation_rows_and_revocation_is_serialized(string $operation, bool $withdrawFirst): void
    {
        $factory = app(ScenarioFactory::class);
        $actor = $factory->unclaimedPlayer(61635);
        $otherAdmin = $factory->unclaimedPlayer(61635);
        $roles = app(BootstrapKingdomAdministrator::class)->handle($actor->kingdomId, $actor->playerId);
        app(AssignKingdomRole::class)->handle($actor->playerId, $actor->kingdomId, $otherAdmin->playerId, $roles->administratorRoleId);
        $configuration = EventTypeScope::query()->where('scope', EventScope::Kingdom->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'kingdom-of-power'))->firstOrFail();
        $createEvent = static fn () => app(CreateEvent::class)->handle(
            actorPlayerId: $actor->playerId, configurationId: (string) $configuration->id, scope: EventScope::Kingdom,
            targetId: $actor->kingdomId, firstLocalStart: CarbonImmutable::now('UTC')->addDay(), durationMinutes: 60,
            settings: ['preparation_phase_minutes' => 1440],
        );
        $createPlan = static fn () => app(CreateTerritoryPlan::class)->handle($actor->playerId, TerritoryPlanScope::Kingdom,
            $actor->kingdomId, null, 'Scope ordering', 'kingshot-evidence-backed-2026-09-06-v2');
        $createdEvent = in_array($operation, ['event-cancel', 'king-perk-create'], true) ? $createEvent() : null;
        $eventId = $createdEvent?->eventId;
        $planId = $operation === 'territory-archive' ? $createPlan()->planId : null;
        $run = static function () use ($operation, $createEvent, $createPlan, $actor, $eventId, $planId, $createdEvent): void {
            match ($operation) {
                'event-create' => $createEvent(),
                'king-perk-create' => app(KingPerkScheduler::class)->createPlan($actor->playerId, $eventId ?? throw new \LogicException, $createdEvent->firstOccurrenceId ?? throw new \LogicException),
                'event-cancel' => app(CancelEvent::class)->handle($actor->playerId, $eventId ?? throw new \LogicException),
                'territory-create' => $createPlan(),
                'territory-archive' => app(ArchiveTerritoryPlan::class)->handle($actor->playerId, $planId ?? throw new \LogicException, 1),
                default => throw new \LogicException,
            };
        };
        $withdraw = static fn () => app(RemoveKingdomRole::class)->handle($otherAdmin->playerId, $actor->kingdomId, $roles->assignmentId, 'Current authority withdrawn.');
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.scope_competitor', [...DB::connection()->getConfig(), 'name' => 'scope_competitor']);
        $other = DB::connection('scope_competitor');
        $other->statement("SET lock_timeout = '150ms'");
        DB::connection($primary)->statement("SET lock_timeout = '150ms'");
        $queries = [];
        $attempted = false;
        $prefix = match ($operation) {
            'event-create' => 'insert into "events"', 'event-cancel' => 'update "events"',
            'king-perk-create' => 'insert into "king_perk_plans"',
            'territory-create' => 'insert into "territory_plans"', default => 'update "territory_plans"',
        };
        DB::listen(static function (QueryExecuted $query) use ($primary, $withdrawFirst, $withdraw, $prefix, &$queries, &$attempted): void {
            if ($query->connectionName !== $primary) {
                return;
            }
            $queries[] = $query->sql;
            if (! $withdrawFirst && ! $attempted && str_starts_with($query->sql, $prefix)) {
                $attempted = true;
                DB::setDefaultConnection('scope_competitor');
                try {
                    try {
                        $withdraw();
                        self::fail('Role withdrawal must wait for the admitted Operations mutation.');
                    } catch (QueryException $exception) {
                        self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                    }
                } finally {
                    DB::setDefaultConnection($primary);
                }
            }
        });
        try {
            if ($withdrawFirst) {
                $other->beginTransaction();
                DB::setDefaultConnection('scope_competitor');
                $withdraw();
                DB::setDefaultConnection($primary);
                try {
                    $run();
                    self::fail('Pending withdrawal must block admission at the Kingdom scope.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
                self::assertNull($this->firstLock($queries, 'players'), 'No actor row may be held while waiting for Kingdom authority.');
                $other->commit();
            } else {
                $run();
                self::assertTrue($attempted);
                $kingdom = $this->firstLock($queries, 'kingdoms');
                $player = $this->firstLock($queries, 'players');
                self::assertNotNull($kingdom);
                self::assertNotNull($player);
                self::assertLessThan($player, $kingdom);
                $operationLock = $this->firstLock($queries, str_starts_with($operation, 'event-') || $operation === 'king-perk-create' ? 'events' : 'territory_plans');
                if ($operationLock !== null) {
                    self::assertLessThan($operationLock, $player);
                }
                $withdraw();
            }
            $this->expectException(AuthorizationException::class);
            $run();
        } finally {
            DB::setDefaultConnection($primary);
            while ($other->transactionLevel() > 0) {
                $other->rollBack();
            }
            DB::connection($primary)->statement("SET lock_timeout = '0'");
            DB::purge('scope_competitor');
        }
    }

    /** @param list<string> $queries */
    private function firstLock(array $queries, string $table): ?int
    {
        foreach ($queries as $index => $query) {
            if (str_starts_with($query, 'select ') && str_contains($query, 'from "'.$table.'"')
                && (str_ends_with($query, 'for share') || str_ends_with($query, 'for update'))) {
                return $index;
            }
        }

        return null;
    }
}
