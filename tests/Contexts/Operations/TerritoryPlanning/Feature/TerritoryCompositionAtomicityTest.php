<?php

declare(strict_types=1);

namespace Tests\Contexts\Operations\TerritoryPlanning\Feature;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\TerritoryPlanning\Actions\ArchiveTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\CloneTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\CreateTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\ImportTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\PublishTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\RestoreTerritoryPlanRevision;
use App\Contexts\Operations\TerritoryPlanning\Actions\SaveTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanRevision;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanSnapshotBuilder;
use App\Contexts\Operations\TerritoryPlanning\ValueObjects\TerritoryPlanMutationReceipt;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class TerritoryCompositionAtomicityTest extends TestCase
{
    use DatabaseTruncation;

    /** @return iterable<string,array{string}> */
    public static function operations(): iterable
    {
        foreach (['clone', 'import', 'restore'] as $operation) {
            yield $operation => [$operation];
        }
    }

    #[DataProvider('operations')]
    public function test_late_audit_failure_holds_source_authority_rolls_back_every_effect_and_allows_retry(string $operation): void
    {
        [$actor, $plan, $revision, $document] = $this->scenario();
        $run = fn () => $this->runOperation($operation, $actor, $plan, $revision, $document);
        $before = $this->persistedState();
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.composition_competitor', [...DB::connection()->getConfig(), 'name' => 'composition_competitor']);
        $other = DB::connection('composition_competitor');
        $other->statement("SET lock_timeout = '150ms'");
        $failed = false;
        $audit = match ($operation) {
            'clone' => 'territory.plan.saved', 'import' => 'territory.plan.imported', default => 'territory.plan.revision_restored',
        };
        DB::listen(static function (QueryExecuted $query) use ($primary, $actor, $plan, $operation, $audit, &$failed): void {
            if ($failed || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'insert into "audit_events"')
                || ! in_array($audit, $query->bindings, true)) {
                return;
            }
            $failed = true;
            DB::setDefaultConnection('composition_competitor');
            try {
                try {
                    app(ArchiveTerritoryPlan::class)->handle($actor->playerId, (string) $plan->id, $operation === 'clone' ? 3 : 4);
                    self::fail('Source authority must remain protected through the final audit.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
            } finally {
                DB::setDefaultConnection($primary);
            }
            throw new RuntimeException('Injected final composition audit failure.');
        });
        try {
            try {
                $run();
                self::fail('The final audit failure must escape the complete owner composition.');
            } catch (RuntimeException $exception) {
                self::assertSame('Injected final composition audit failure.', $exception->getMessage());
            }
            self::assertTrue($failed);
            self::assertSame($before, $this->persistedState());
            self::assertSame(0, DB::transactionLevel());
            $receipt = $run();
            self::assertSame($operation === 'clone' ? 2 : 4, $receipt->revision);
            $updated = TerritoryPlan::query()->findOrFail($receipt->planId);
            self::assertSame($operation === 'clone' ? 150 : 100, $updated->objects()->firstOrFail()->coordinate_x);
            self::assertSame(1, $updated->objects()->count());
            self::assertSame($operation === 'clone' ? 2 : 1, TerritoryPlan::query()->count());
            self::assertSame(1, TerritoryPlanRevision::query()->count());
            self::assertSame(100, $revision->refresh()->snapshot['objects'][0]['x'] ?? null);
            self::assertSame($operation === 'clone' ? 3 : 4, $plan->refresh()->revision);
            app(ArchiveTerritoryPlan::class)->handle($actor->playerId, (string) $plan->id, $plan->revision);
            self::assertSame('archived', $plan->refresh()->status->value);
        } finally {
            DB::setDefaultConnection($primary);
            while ($other->transactionLevel() > 0) {
                $other->rollBack();
            }
            DB::purge('composition_competitor');
        }
    }

    /** @return iterable<string,array{string}> */
    public static function revisionedOperations(): iterable
    {
        yield 'import' => ['import'];
        yield 'restore' => ['restore'];
    }

    #[DataProvider('revisionedOperations')]
    public function test_revision_admission_is_current_after_a_competing_save(string $operation): void
    {
        [$actor, $plan, $revision, $document] = $this->scenario();
        $stalePlan = clone $plan;
        $snapshot = app(TerritoryPlanSnapshotBuilder::class)->build($plan);
        $receipt = app(SaveTerritoryPlan::class)->handle($actor->playerId, (string) $plan->id, 3,
            $snapshot['alliances'], $snapshot['groups'], $snapshot['objects']);
        self::assertSame(4, $receipt->revision);
        $before = $this->persistedState();
        try {
            $this->runOperation($operation, $actor, $stalePlan, $revision, $document);
            self::fail('A stale expected revision must reject the complete composition.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('revision', $exception->errors());
        }
        self::assertSame($before, $this->persistedState());
        self::assertSame(5, $this->runOperation($operation, $actor, $plan->refresh(), $revision, $document)->revision);
    }

    private function runOperation(string $operation, PlayerReference $actor, TerritoryPlan $plan, TerritoryPlanRevision $revision, string $document): TerritoryPlanMutationReceipt
    {
        return match ($operation) {
            'clone' => app(CloneTerritoryPlan::class)->handle($actor->playerId, (string) $plan->id, 'Atomic clone'),
            'import' => app(ImportTerritoryPlan::class)->handle($actor->playerId, (string) $plan->id, $plan->revision, $document, hash('sha256', $document)),
            'restore' => app(RestoreTerritoryPlanRevision::class)->handle($actor->playerId, (string) $plan->id, (string) $revision->id, $plan->revision),
            default => throw new \LogicException,
        };
    }

    /** @return array{PlayerReference,TerritoryPlan,TerritoryPlanRevision,string} */
    private function scenario(): array
    {
        $factory = app(ScenarioFactory::class);
        $actor = $factory->player((int) $factory->authUser()->id, 61638);
        $alliance = $factory->alliance($actor);
        $created = app(CreateTerritoryPlan::class)->handle($actor->playerId, TerritoryPlanScope::Alliance, $actor->kingdomId,
            $alliance->allianceId, 'Atomic source', 'kingshot-evidence-backed-2026-09-06-v2');
        $layers = [['key' => 'owner', 'alliance_id' => $alliance->allianceId, 'display_name' => $alliance->name]];
        $objects = [['key' => 'city', 'alliance_key' => 'owner', 'type' => 'governor_city', 'x' => 100, 'y' => 100]];
        $saved = app(SaveTerritoryPlan::class)->handle($actor->playerId, $created->planId, 1, $layers, [], $objects);
        $published = app(PublishTerritoryPlan::class)->handle($actor->playerId, $created->planId, 2, (string) $saved->layoutChecksum);
        self::assertNotNull($published->publishedRevisionId);
        $revision = TerritoryPlanRevision::query()->findOrFail($published->publishedRevisionId);
        $document = json_encode($revision->snapshot, JSON_THROW_ON_ERROR);
        $objects[0]['x'] = 150;
        app(SaveTerritoryPlan::class)->handle($actor->playerId, $created->planId, 2, $layers, [], $objects);

        return [$actor, TerritoryPlan::query()->findOrFail($created->planId), $revision, $document];
    }

    /** @return array<string,string> */
    private function persistedState(): array
    {
        $state = [];
        foreach (['territory_plans', 'territory_plan_alliances', 'territory_plan_groups', 'territory_plan_objects', 'territory_plan_revisions', 'audit_events', 'outbox_messages'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->toJson(JSON_THROW_ON_ERROR);
        }

        return $state;
    }
}
