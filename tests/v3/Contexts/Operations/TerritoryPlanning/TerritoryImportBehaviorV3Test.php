<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Operations\TerritoryPlanning;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\TerritoryPlanning\Actions\CreateTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\ImportTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanObject;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanImport;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use JsonException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class TerritoryImportBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    private const DATASET_ID = 'kingshot-community-observed-2026-08-21-v1';

    public function test_import_preview_rejects_invalid_json(): void
    {
        $this->expectException(ValidationException::class);

        app(TerritoryPlanImport::class)->preview('{not-json');
    }

    /** @throws JsonException */
    public function test_import_preview_rejects_malformed_rows_before_commit(): void
    {
        $document = json_encode([
            'schema_version' => 1,
            'plan' => ['map_dataset_id' => self::DATASET_ID],
            'alliances' => [[
                'key' => 'external',
                'alliance_id' => null,
                'external_name' => 'External Alliance',
                'display_name' => 'External Alliance',
                'presentation_color' => '#4da3ff',
            ]],
            'groups' => [],
            'objects' => ['not-an-object'],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(ValidationException::class);

        app(TerritoryPlanImport::class)->preview($document);
    }

    /** @throws JsonException */
    public function test_import_preview_reports_blocking_geometry_and_commit_refuses_it(): void
    {
        [$actor, $alliance, $plan] = $this->scenario(64001);
        $document = $this->document(
            $plan,
            $alliance->allianceId,
            $alliance->name,
            x: 1199,
            y: 1199,
        );

        $preview = app(TerritoryPlanImport::class)->preview($document);
        self::assertFalse($preview['can_commit']);
        self::assertNotEmpty($preview['validation']['violations']);

        try {
            app(ImportTerritoryPlan::class)->handle(
                $actor->playerId,
                (string) $plan->id,
                1,
                $document,
            );
            self::fail('Expected a blocking imported layout to be rejected.');
        } catch (ValidationException) {
            self::assertSame(1, $plan->refresh()->revision);
            self::assertSame(0, $plan->objects()->count());
        }
    }

    /** @throws JsonException */
    public function test_import_commit_is_atomic_revisioned_and_audited(): void
    {
        [$actor, $alliance, $plan] = $this->scenario(64002);
        $document = $this->document(
            $plan,
            $alliance->allianceId,
            $alliance->name,
            x: 100,
            y: 100,
        );

        $receipt = app(ImportTerritoryPlan::class)->handle(
            $actor->playerId,
            (string) $plan->id,
            1,
            $document,
        );

        self::assertSame(2, $receipt->revision);
        self::assertSame(2, $plan->refresh()->revision);
        self::assertSame(1, TerritoryPlanObject::query()->where('territory_plan_id', $plan->id)->count());
        self::assertTrue(
            AuditEvent::query()
                ->where('event', 'territory.plan.imported')
                ->where('actor_player_id', $actor->playerId)
                ->exists(),
        );
    }

    /** @throws JsonException */
    public function test_import_commit_rejects_stale_revision_without_mutating_plan(): void
    {
        [$actor, $alliance, $plan] = $this->scenario(64003);
        $document = $this->document(
            $plan,
            $alliance->allianceId,
            $alliance->name,
            x: 100,
            y: 100,
        );
        $plan->forceFill(['revision' => 2])->save();

        $this->expectException(ValidationException::class);
        try {
            app(ImportTerritoryPlan::class)->handle(
                $actor->playerId,
                (string) $plan->id,
                1,
                $document,
            );
        } finally {
            self::assertSame(2, $plan->refresh()->revision);
            self::assertSame(0, $plan->objects()->count());
        }
    }

    /** @throws JsonException */
    public function test_import_commit_rejects_map_profile_mismatch_without_mutating_plan(): void
    {
        [$actor, $alliance, $plan] = $this->scenario(64004);
        $document = $this->document(
            $plan,
            $alliance->allianceId,
            $alliance->name,
            x: 100,
            y: 100,
        );
        $plan->forceFill(['map_dataset_checksum' => str_repeat('0', 64)])->save();

        $this->expectException(ValidationException::class);
        try {
            app(ImportTerritoryPlan::class)->handle(
                $actor->playerId,
                (string) $plan->id,
                1,
                $document,
            );
        } finally {
            self::assertSame(1, $plan->refresh()->revision);
            self::assertSame(0, $plan->objects()->count());
        }
    }

    /**
     * @return array{0: PlayerReference, 1: AllianceReference, 2: TerritoryPlan}
     */
    private function scenario(int $kingdomNumber): array
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, $kingdomNumber);
        $alliance = $scenario->alliance($actor);
        $created = app(CreateTerritoryPlan::class)->handle(
            $actor->playerId,
            TerritoryPlanScope::Alliance,
            $actor->kingdomId,
            $alliance->allianceId,
            'Imported Hive',
            self::DATASET_ID,
        );

        return [$actor, $alliance, TerritoryPlan::query()->findOrFail($created->planId)];
    }

    /** @throws JsonException */
    private function document(
        TerritoryPlan $plan,
        string $allianceId,
        string $allianceName,
        int $x,
        int $y,
    ): string {
        return json_encode([
            'schema_version' => 1,
            'plan' => [
                'map_dataset_id' => (string) $plan->map_dataset_id,
                'map_dataset_checksum' => (string) $plan->map_dataset_checksum,
                'planning_preferences' => ['march_seconds_per_tile' => 2],
            ],
            'alliances' => [[
                'key' => 'owner',
                'alliance_id' => $allianceId,
                'external_name' => null,
                'external_tag' => null,
                'display_name' => $allianceName,
                'presentation_color' => '#4da3ff',
                'sort_order' => 0,
                'visible' => true,
                'locked' => false,
            ]],
            'groups' => [],
            'objects' => [[
                'key' => 'import-city',
                'alliance_key' => 'owner',
                'group_key' => null,
                'type' => 'governor_city',
                'player_id' => null,
                'external_player_name' => 'Imported Governor',
                'label' => 'Imported Governor',
                'x' => $x,
                'y' => $y,
                'rotation' => 0,
                'sort_order' => 0,
                'metadata' => [],
            ]],
        ], JSON_THROW_ON_ERROR);
    }
}
