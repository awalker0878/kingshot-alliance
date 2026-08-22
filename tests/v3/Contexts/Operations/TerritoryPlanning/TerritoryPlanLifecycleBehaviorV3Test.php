<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Operations\TerritoryPlanning;

use App\Contexts\Operations\TerritoryPlanning\Actions\ArchiveTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\CloneTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\CreateTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\PublishTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\RestoreTerritoryPlanRevision;
use App\Contexts\Operations\TerritoryPlanning\Actions\SaveTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanStatus;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanRevision;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class TerritoryPlanLifecycleBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    private const DATASET_ID = 'kingshot-community-observed-2026-08-21-v1';

    public function test_alliance_plan_save_publish_restore_clone_and_archive_preserve_history(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 61001);
        $alliance = $scenario->alliance($actor);

        $created = app(CreateTerritoryPlan::class)->handle(
            $actor->playerId,
            TerritoryPlanScope::Alliance,
            $actor->kingdomId,
            $alliance->allianceId,
            'Bear Hive Alpha',
            self::DATASET_ID,
        );

        $saved = app(SaveTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            $created->revision,
            $this->allianceLayers($alliance->allianceId, $alliance->name),
            [],
            $this->objects(100),
            [
                'preferred_bear_radius_tiles' => 40,
                'march_seconds_per_tile' => 2,
            ],
        );

        self::assertSame(2, $saved->revision);
        self::assertSame(TerritoryPlanStatus::Draft->value, $saved->status);

        $published = app(PublishTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            $saved->revision,
        );
        self::assertNotNull($published->publishedRevisionId);
        self::assertSame(TerritoryPlanStatus::Published->value, $published->status);

        $publishedRevision = TerritoryPlanRevision::query()->findOrFail($published->publishedRevisionId);
        $publishedSnapshot = $publishedRevision->snapshot;
        self::assertIsArray($publishedSnapshot);
        self::assertSame(100, $publishedSnapshot['objects'][0]['x'] ?? null);

        $resaved = app(SaveTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            $saved->revision,
            $this->allianceLayers($alliance->allianceId, $alliance->name),
            [],
            $this->objects(150),
            [],
        );
        self::assertSame(3, $resaved->revision);

        $publishedRevision->refresh();
        $immutableSnapshot = $publishedRevision->snapshot;
        self::assertIsArray($immutableSnapshot);
        self::assertSame(100, $immutableSnapshot['objects'][0]['x'] ?? null);

        $restored = app(RestoreTerritoryPlanRevision::class)->handle(
            $actor->playerId,
            $created->planId,
            (string) $publishedRevision->id,
            $resaved->revision,
        );
        self::assertSame(4, $restored->revision);
        self::assertSame(
            100,
            TerritoryPlan::query()->findOrFail($created->planId)->objects()->orderBy('sort_order')->firstOrFail()->coordinate_x,
        );

        $clone = app(CloneTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            'Bear Hive Alpha Copy',
        );
        $clonedPlan = TerritoryPlan::query()->findOrFail($clone->planId);
        self::assertSame(TerritoryPlanScope::Alliance, $clonedPlan->scope);
        self::assertSame($alliance->allianceId, (string) $clonedPlan->owner_alliance_id);
        self::assertSame(4, $clonedPlan->objects()->count());

        $archived = app(ArchiveTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            $restored->revision,
        );
        self::assertSame(TerritoryPlanStatus::Archived->value, $archived->status);
        self::assertSame(
            TerritoryPlanStatus::Archived,
            TerritoryPlan::query()->findOrFail($created->planId)->status,
        );

        self::assertTrue(AuditEvent::query()->where('event', 'territory.plan.created')->exists());
        self::assertTrue(AuditEvent::query()->where('event', 'territory.plan.saved')->exists());
        self::assertTrue(AuditEvent::query()->where('event', 'territory.plan.published')->exists());
        self::assertTrue(AuditEvent::query()->where('event', 'territory.plan.revision_restored')->where('actor_player_id', $actor->playerId)->exists());
        self::assertTrue(AuditEvent::query()->where('event', 'territory.plan.archived')->exists());
    }

    public function test_save_rejects_stale_expected_revision(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 61002);
        $alliance = $scenario->alliance($actor);
        $created = app(CreateTerritoryPlan::class)->handle(
            $actor->playerId,
            TerritoryPlanScope::Alliance,
            $actor->kingdomId,
            $alliance->allianceId,
            'Concurrent Hive',
            self::DATASET_ID,
        );

        app(SaveTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            1,
            $this->allianceLayers($alliance->allianceId, $alliance->name),
            [],
            $this->objects(100),
        );

        $this->expectException(ValidationException::class);
        app(SaveTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            1,
            $this->allianceLayers($alliance->allianceId, $alliance->name),
            [],
            $this->objects(200),
        );
    }

    public function test_non_member_cannot_create_or_manage_an_alliance_plan(): void
    {
        $scenario = new ScenarioFactory;
        $ownerAccount = $scenario->authUser();
        $owner = $scenario->player((int) $ownerAccount->id, 61003);
        $alliance = $scenario->alliance($owner);
        $outsiderAccount = $scenario->authUser();
        $outsider = $scenario->player((int) $outsiderAccount->id, 61003);

        $this->expectException(AuthorizationException::class);
        app(CreateTerritoryPlan::class)->handle(
            $outsider->playerId,
            TerritoryPlanScope::Alliance,
            $outsider->kingdomId,
            $alliance->allianceId,
            'Unauthorized Hive',
            self::DATASET_ID,
        );
    }

    /** @return list<array<string, mixed>> */
    private function allianceLayers(string $allianceId, string $name): array
    {
        return [[
            'key' => 'owner',
            'alliance_id' => $allianceId,
            'external_name' => null,
            'external_tag' => null,
            'display_name' => $name,
            'presentation_color' => '#4da3ff',
            'sort_order' => 0,
            'visible' => true,
            'locked' => false,
        ]];
    }

    /** @return list<array<string, mixed>> */
    private function objects(int $offset): array
    {
        return [
            [
                'key' => 'hq',
                'alliance_key' => 'owner',
                'group_key' => null,
                'type' => 'headquarters',
                'player_id' => null,
                'external_player_name' => null,
                'label' => 'HQ',
                'x' => $offset,
                'y' => $offset,
                'rotation' => 0,
                'sort_order' => 0,
                'metadata' => [],
            ],
            [
                'key' => 'banner',
                'alliance_key' => 'owner',
                'group_key' => null,
                'type' => 'banner',
                'player_id' => null,
                'external_player_name' => null,
                'label' => 'Banner',
                'x' => $offset + 8,
                'y' => $offset,
                'rotation' => 0,
                'sort_order' => 1,
                'metadata' => [],
            ],
            [
                'key' => 'city',
                'alliance_key' => 'owner',
                'group_key' => null,
                'type' => 'governor_city',
                'player_id' => null,
                'external_player_name' => 'External Governor',
                'label' => 'Governor',
                'x' => $offset + 4,
                'y' => $offset + 8,
                'rotation' => 0,
                'sort_order' => 2,
                'metadata' => [],
            ],
            [
                'key' => 'trap',
                'alliance_key' => 'owner',
                'group_key' => null,
                'type' => 'bear_trap',
                'player_id' => null,
                'external_player_name' => null,
                'label' => 'Bear Trap',
                'x' => $offset + 16,
                'y' => $offset + 16,
                'rotation' => 0,
                'sort_order' => 3,
                'metadata' => [],
            ],
        ];
    }
}
