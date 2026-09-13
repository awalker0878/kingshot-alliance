<?php

declare(strict_types=1);

namespace Tests\Contexts\Operations\TerritoryPlanning\Feature;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\TerritoryPlanning\Actions\ArchiveTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\CreateTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\PublishTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\RestoreTerritoryPlanRevision;
use App\Contexts\Operations\TerritoryPlanning\Actions\SaveTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Exceptions\TerritoryRevisionConflict;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanRevision;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanSnapshotBuilder;
use App\Contexts\Operations\TerritoryPlanning\ValueObjects\TerritoryPlanMutationReceipt;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class TerritoryPublicationConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_replays_the_same_saved_snapshot_once(): void
    {
        [$actor, $saved] = $this->scenario();
        self::assertSame(2, $saved->snapshot['schema_version']);
        $plan = TerritoryPlan::query()->findOrFail($saved->planId);
        $snapshots = app(TerritoryPlanSnapshotBuilder::class);
        self::assertSame($saved->layoutChecksum, $snapshots->checksum($snapshots->build($plan)));
        $publish = app(PublishTerritoryPlan::class);
        $first = $publish->handle($actor->playerId, $saved->planId, $saved->revision, (string) $saved->layoutChecksum);
        $retry = $publish->handle($actor->playerId, $saved->planId, $saved->revision, (string) $saved->layoutChecksum);
        self::assertSame($first->publishedRevisionId, $retry->publishedRevisionId);
        self::assertSame(1, TerritoryPlanRevision::query()->where('territory_plan_id', $saved->planId)->count());
        self::assertSame(1, AuditEvent::query()->where('event', 'territory.plan.published')->count());
    }

    public function test_dirty_or_unreviewed_layout_checksum_cannot_publish_saved_state(): void
    {
        [$actor, $saved] = $this->scenario();
        try {
            app(PublishTerritoryPlan::class)->handle($actor->playerId, $saved->planId, $saved->revision, str_repeat('0', 64));
            self::fail('Unreviewed saved layout was published.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('layout_checksum', $exception->errors());
            self::assertSame(0, TerritoryPlanRevision::query()->count());
        }
    }

    public function test_publication_revalidates_geometry_even_with_a_current_checksum(): void
    {
        [$actor, $saved] = $this->scenario();
        $plan = TerritoryPlan::query()->findOrFail($saved->planId);
        $plan->objects()->update(['coordinate_x' => 1199]);
        $snapshots = app(TerritoryPlanSnapshotBuilder::class);
        $checksum = $snapshots->checksum($snapshots->build($plan));
        try {
            app(PublishTerritoryPlan::class)->handle($actor->playerId, $saved->planId, $saved->revision, $checksum);
            self::fail('Invalid persisted geometry was published.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('layout', $exception->errors());
            self::assertSame(0, TerritoryPlanRevision::query()->count());
        }
    }

    public function test_archive_cannot_be_reopened_by_a_stale_publish_retry(): void
    {
        [$actor, $saved] = $this->scenario();
        app(PublishTerritoryPlan::class)->handle($actor->playerId, $saved->planId, $saved->revision, (string) $saved->layoutChecksum);
        app(ArchiveTerritoryPlan::class)->handle($actor->playerId, $saved->planId, $saved->revision);
        $this->expectException(ValidationException::class);
        app(PublishTerritoryPlan::class)->handle($actor->playerId, $saved->planId, $saved->revision, (string) $saved->layoutChecksum);
    }

    public function test_revision_conflict_returns_current_revision_without_replacing_it(): void
    {
        [$actor, $saved] = $this->scenario();
        try {
            app(PublishTerritoryPlan::class)->handle($actor->playerId, $saved->planId, 1, (string) $saved->layoutChecksum);
            self::fail('A stale publication was accepted.');
        } catch (TerritoryRevisionConflict $exception) {
            self::assertSame(2, $exception->currentRevision);
            self::assertSame(409, $exception->render()->getStatusCode());
            self::assertSame('territory_revision_conflict', $exception->render()->getData(true)['code']);
            self::assertSame(0, TerritoryPlanRevision::query()->count());
        }
    }

    public function test_restore_rejects_modified_snapshot_bytes_without_mutating_the_head(): void
    {
        [$actor, $saved] = $this->scenario();
        $published = app(PublishTerritoryPlan::class)->handle($actor->playerId, $saved->planId, $saved->revision, (string) $saved->layoutChecksum);
        $revision = TerritoryPlanRevision::query()->findOrFail($published->publishedRevisionId);
        $modified = $revision->snapshot;
        $modified['objects'][0]['x'] = 200;
        $revision->forceFill(['snapshot' => $modified])->save();
        try {
            app(RestoreTerritoryPlanRevision::class)->handle($actor->playerId, $saved->planId, (string) $revision->id, $saved->revision);
            self::fail('A corrupted immutable snapshot was restored.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('revision', $exception->errors());
            $plan = TerritoryPlan::query()->findOrFail($saved->planId);
            self::assertSame($saved->revision, $plan->revision);
            self::assertSame(100, $plan->objects()->firstOrFail()->coordinate_x);
        }
    }

    /** @return array{PlayerReference,TerritoryPlanMutationReceipt} */
    private function scenario(): array
    {
        $factory = app(ScenarioFactory::class);
        $actor = $factory->player((int) $factory->authUser()->id, 61640);
        $alliance = $factory->alliance($actor);
        $created = app(CreateTerritoryPlan::class)->handle($actor->playerId, TerritoryPlanScope::Alliance,
            $actor->kingdomId, $alliance->allianceId, 'Publication consistency', 'kingshot-evidence-backed-2026-09-06-v2');
        $saved = app(SaveTerritoryPlan::class)->handle($actor->playerId, $created->planId, 1,
            [['key' => 'owner', 'alliance_id' => $alliance->allianceId, 'display_name' => $alliance->name]], [],
            [['key' => 'city', 'alliance_key' => 'owner', 'type' => 'governor_city', 'x' => 100, 'y' => 100]]);

        return [$actor, $saved];
    }
}
