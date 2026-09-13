<?php

declare(strict_types=1);

namespace Tests\Contexts\Operations\TerritoryPlanning\Feature;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\TerritoryPlanning\Actions\ArchiveTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\AttachTerritoryPlanRevisionToEvent;
use App\Contexts\Operations\TerritoryPlanning\Actions\CreateTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\DetachTerritoryPlanRevisionFromEvent;
use App\Contexts\Operations\TerritoryPlanning\Actions\PublishTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\SaveTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\UpdateTerritoryPlanAlliances;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\ValueObjects\TerritoryPlanMutationReceipt;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class TerritoryScopeOrderingTest extends TestCase
{
    use DatabaseTruncation;

    private const DATASET = 'kingshot-evidence-backed-2026-09-06-v2';

    /** @return iterable<string,array{string}> */
    public static function references(): iterable
    {
        foreach (['update-alliance', 'save-alliance', 'save-player'] as $operation) {
            yield $operation => [$operation];
        }
    }

    #[DataProvider('references')]
    public function test_contended_linked_reference_rolls_back_without_waiting_and_retry_preserves_revision(string $operation): void
    {
        [$actor, $linkedPlayer, $alliance, $created] = $this->scenario();
        $layers = $this->layers($alliance);
        $run = static fn () => $operation === 'update-alliance'
            ? app(UpdateTerritoryPlanAlliances::class)->handle($actor->playerId, $created->planId, 1, $layers)
            : app(SaveTerritoryPlan::class)->handle($actor->playerId, $created->planId, 1, $layers, [],
                $operation === 'save-player' ? [['key' => 'city', 'type' => 'governor_city', 'alliance_key' => 'linked', 'x' => 100, 'y' => 100, 'player_id' => $linkedPlayer->playerId]] : []);
        config()->set('database.connections.territory_competitor', [...DB::connection()->getConfig(), 'name' => 'territory_competitor']);
        $other = DB::connection('territory_competitor');
        $other->beginTransaction();
        $other->table($operation === 'save-player' ? 'players' : 'alliances')
            ->where('id', $operation === 'save-player' ? $linkedPlayer->playerId : $alliance->allianceId)->lockForUpdate()->first();
        $before = DB::table('audit_events')->count();
        try {
            try {
                $run();
                self::fail('A contended lower reference must reject the whole plan update.');
            } catch (ValidationException $exception) {
                $field = $operation === 'save-player' ? 'objects' : 'alliances';
                self::assertArrayHasKey($field, $exception->errors());
                self::assertStringContainsString('Retry', $exception->errors()[$field][0]);
            }
            self::assertSame(1, DB::table('territory_plans')->where('id', $created->planId)->value('revision'));
            self::assertSame(0, DB::table('territory_plan_alliances')->where('territory_plan_id', $created->planId)->count());
            self::assertSame(0, DB::table('territory_plan_objects')->where('territory_plan_id', $created->planId)->count());
            self::assertSame($before, DB::table('audit_events')->count());
            $other->commit();
            self::assertSame(2, $run()->revision);
            self::assertSame(1, DB::table('territory_plan_alliances')->where('territory_plan_id', $created->planId)->count());
            self::assertSame($operation === 'save-player' ? 1 : 0, DB::table('territory_plan_objects')->where('territory_plan_id', $created->planId)->count());
        } finally {
            while ($other->transactionLevel() > 0) {
                $other->rollBack();
            }
            DB::purge('territory_competitor');
        }
    }

    public function test_changed_plan_routing_is_rejected_after_reacquiring_the_original_scope(): void
    {
        [$actor, , $alliance, $created] = $this->scenario();
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.territory_competitor', [...DB::connection()->getConfig(), 'name' => 'territory_competitor']);
        $other = DB::connection('territory_competitor');
        $changed = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $other, $created, $alliance, &$changed): void {
            if (! $changed && $query->connectionName === $primary && str_starts_with($query->sql, 'select "id", "scope", "kingdom_id", "owner_alliance_id" from "territory_plans"')) {
                $changed = true;
                $other->table('territory_plans')->where('id', $created->planId)->update(['scope' => 'alliance', 'owner_alliance_id' => $alliance->allianceId]);
            }
        });
        try {
            try {
                app(ArchiveTerritoryPlan::class)->handle($actor->playerId, $created->planId, 1);
                self::fail('Changed scope must not inherit the original admission.');
            } catch (AuthorizationException) {
                self::assertTrue($changed);
            }
            self::assertSame('draft', DB::table('territory_plans')->where('id', $created->planId)->value('status'));
            self::assertSame($alliance->allianceId, DB::table('territory_plans')->where('id', $created->planId)->value('owner_alliance_id'));
        } finally {
            DB::purge('territory_competitor');
        }
    }

    public function test_attachment_and_detachment_lock_event_scope_before_occurrence_and_reject_foreign_plan_scope(): void
    {
        [$actor, $linkedPlayer, $alliance, $created] = $this->scenario();
        $scope = EventTypeScope::query()->where('scope', EventScope::Kingdom->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'kingdom-of-power'))->firstOrFail();
        $event = app(CreateEvent::class)->handle($actor->playerId, (string) $scope->id, EventScope::Kingdom,
            $actor->kingdomId, CarbonImmutable::now('UTC')->addDay(), durationMinutes: 60, settings: ['preparation_phase_minutes' => 1440]);
        self::assertNotNull($event->firstOccurrenceId);
        $saved = app(SaveTerritoryPlan::class)->handle($actor->playerId, $created->planId, 1, $this->layers($alliance), [], $this->city());
        $published = app(PublishTerritoryPlan::class)->handle($actor->playerId, $created->planId, $saved->revision, (string) $saved->layoutChecksum);
        self::assertNotNull($published->publishedRevisionId);
        foreach ([true, false] as $attach) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            if ($attach) {
                app(AttachTerritoryPlanRevisionToEvent::class)->handle($actor->playerId, $event->firstOccurrenceId, $published->publishedRevisionId);
            } else {
                self::assertTrue(app(DetachTerritoryPlanRevisionFromEvent::class)->handle($actor->playerId, $event->firstOccurrenceId));
            }
            $sql = array_column(DB::getQueryLog(), 'query');
            DB::disableQueryLog();
            $eventLock = $occurrenceLock = null;
            foreach ($sql as $index => $query) {
                if (str_contains($query, 'from "events"') && str_ends_with($query, 'for share')) {
                    $eventLock ??= $index;
                }
                if (str_contains($query, 'from "event_occurrences"') && str_ends_with($query, 'for update')) {
                    $occurrenceLock ??= $index;
                }
            }
            self::assertNotNull($eventLock);
            self::assertNotNull($occurrenceLock);
            self::assertLessThan($occurrenceLock, $eventLock);
        }
        $foreign = app(CreateTerritoryPlan::class)->handle($linkedPlayer->playerId, TerritoryPlanScope::Alliance, $actor->kingdomId, $alliance->allianceId, 'Other owner scope', self::DATASET);
        $saved = app(SaveTerritoryPlan::class)->handle($linkedPlayer->playerId, $foreign->planId, 1, $this->layers($alliance), [], $this->city());
        $published = app(PublishTerritoryPlan::class)->handle($linkedPlayer->playerId, $foreign->planId, $saved->revision, (string) $saved->layoutChecksum);
        self::assertNotNull($published->publishedRevisionId);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            app(AttachTerritoryPlanRevisionToEvent::class)->handle($actor->playerId, $event->firstOccurrenceId, $published->publishedRevisionId);
            self::fail('An unrelated Alliance scope cannot be acquired behind a Kingdom Event.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('territory_plan_revision_id', $exception->errors());
        }
        foreach (DB::getQueryLog() as $query) {
            self::assertFalse(str_contains($query['query'], 'from "alliances"') && str_contains($query['query'], 'for update'));
        }
        DB::disableQueryLog();
    }

    /** @return array{PlayerReference,PlayerReference,AllianceReference,TerritoryPlanMutationReceipt} */
    private function scenario(): array
    {
        $factory = app(ScenarioFactory::class);
        $actor = $factory->unclaimedPlayer(61637);
        $linked = $factory->player($factory->account()->userId, 61637);
        $alliance = $factory->alliance($linked);
        app(BootstrapKingdomAdministrator::class)->handle($actor->kingdomId, $actor->playerId);
        $created = app(CreateTerritoryPlan::class)->handle($actor->playerId, TerritoryPlanScope::Kingdom, $actor->kingdomId, null, 'Reference contention', self::DATASET);

        return [$actor, $linked, $alliance, $created];
    }

    /** @return list<array<string,mixed>> */
    private function layers(AllianceReference $alliance): array
    {
        return [['key' => 'linked', 'alliance_id' => $alliance->allianceId, 'display_name' => $alliance->name]];
    }

    /** @return list<array<string,mixed>> */
    private function city(): array
    {
        return [['key' => 'city', 'type' => 'governor_city', 'alliance_key' => 'linked', 'x' => 100, 'y' => 100]];
    }
}
