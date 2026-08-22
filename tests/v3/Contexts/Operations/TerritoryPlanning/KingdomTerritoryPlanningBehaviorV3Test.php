<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Operations\TerritoryPlanning;

use App\Contexts\Operations\TerritoryPlanning\Actions\CreateTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\SaveTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\UpdateTerritoryPlanAlliances;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanAlliance;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanObject;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class KingdomTerritoryPlanningBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    private const DATASET_ID = 'kingshot-community-observed-2026-08-21-v1';

    public function test_kingdom_manager_can_plan_multiple_linked_and_external_alliances(): void
    {
        $scenario = new ScenarioFactory;
        $actorAccount = $scenario->authUser();
        $actor = $scenario->player((int) $actorAccount->id, 63001);
        $actorAlliance = $scenario->alliance($actor);
        $otherAccount = $scenario->authUser();
        $otherPlayer = $scenario->player((int) $otherAccount->id, 63001);
        $otherAlliance = $scenario->alliance($otherPlayer);
        app(BootstrapKingdomAdministrator::class)->handle($actor->kingdomId, $actor->playerId);

        $created = app(CreateTerritoryPlan::class)->handle(
            $actor->playerId,
            TerritoryPlanScope::Kingdom,
            $actor->kingdomId,
            null,
            'Kingdom 63001 Territory Board',
            self::DATASET_ID,
        );

        $layers = [
            $this->linkedLayer('alpha', $actorAlliance->allianceId, $actorAlliance->name, '#4da3ff', 0),
            $this->linkedLayer('beta', $otherAlliance->allianceId, $otherAlliance->name, '#50c878', 1),
            $this->externalLayer('external', 'NAP Partner', '#c4874e', 2, visible: false, locked: true),
        ];

        $saved = app(SaveTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            $created->revision,
            $layers,
            [],
            [
                $this->city('alpha-city', 'alpha', 100, 100),
                $this->city('beta-city', 'beta', 160, 160),
                $this->city('external-city', 'external', 220, 220),
            ],
        );

        self::assertSame(2, $saved->revision);
        $plan = TerritoryPlan::query()->findOrFail($created->planId);
        self::assertSame(TerritoryPlanScope::Kingdom, $plan->scope);
        self::assertNull($plan->owner_alliance_id);
        self::assertSame(3, TerritoryPlanAlliance::query()->where('territory_plan_id', $plan->id)->count());
        self::assertSame(3, TerritoryPlanObject::query()->where('territory_plan_id', $plan->id)->count());

        $external = TerritoryPlanAlliance::query()
            ->where('territory_plan_id', $plan->id)
            ->where('external_name', 'NAP Partner')
            ->firstOrFail();
        self::assertNull($external->alliance_id);
        self::assertFalse($external->visible);
        self::assertTrue($external->locked);
        self::assertSame('#c4874e', $external->presentation_color);
    }

    public function test_kingdom_manager_can_add_and_edit_empty_participant_layers_without_rewriting_layout(): void
    {
        $scenario = new ScenarioFactory;
        $actorAccount = $scenario->authUser();
        $actor = $scenario->player((int) $actorAccount->id, 63005);
        $actorAlliance = $scenario->alliance($actor);
        app(BootstrapKingdomAdministrator::class)->handle($actor->kingdomId, $actor->playerId);

        $created = app(CreateTerritoryPlan::class)->handle(
            $actor->playerId,
            TerritoryPlanScope::Kingdom,
            $actor->kingdomId,
            null,
            'Kingdom Participant Board',
            self::DATASET_ID,
        );
        $saved = app(SaveTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            1,
            [$this->linkedLayer('alpha', $actorAlliance->allianceId, $actorAlliance->name, '#4da3ff', 0)],
            [],
            [$this->city('alpha-city', 'alpha', 100, 100)],
        );

        $updated = app(UpdateTerritoryPlanAlliances::class)->handle(
            $actor->playerId,
            $created->planId,
            $saved->revision,
            [
                $this->linkedLayer('alpha', $actorAlliance->allianceId, 'Alpha Command', '#4da3ff', 0),
                $this->externalLayer('partner', 'NAP Partner', '#c4874e', 1, visible: false, locked: true),
            ],
        );

        self::assertSame(3, $updated->revision);
        self::assertSame(1, TerritoryPlanObject::query()->where('territory_plan_id', $created->planId)->count());
        $partner = TerritoryPlanAlliance::query()
            ->where('territory_plan_id', $created->planId)
            ->where('plan_key', 'partner')
            ->firstOrFail();
        self::assertSame('NAP Partner', $partner->display_name);
        self::assertFalse($partner->visible);
        self::assertTrue($partner->locked);
        self::assertTrue(
            AuditEvent::query()
                ->where('event', 'territory.plan.alliances_updated')
                ->where('actor_player_id', $actor->playerId)
                ->exists(),
        );
    }

    public function test_kingdom_manager_cannot_remove_layer_that_still_owns_planned_objects(): void
    {
        $scenario = new ScenarioFactory;
        $actorAccount = $scenario->authUser();
        $actor = $scenario->player((int) $actorAccount->id, 63007);
        $actorAlliance = $scenario->alliance($actor);
        app(BootstrapKingdomAdministrator::class)->handle($actor->kingdomId, $actor->playerId);

        $created = app(CreateTerritoryPlan::class)->handle(
            $actor->playerId,
            TerritoryPlanScope::Kingdom,
            $actor->kingdomId,
            null,
            'Layer Removal Guard',
            self::DATASET_ID,
        );
        $saved = app(SaveTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            1,
            [
                $this->linkedLayer('alpha', $actorAlliance->allianceId, $actorAlliance->name, '#4da3ff', 0),
                $this->externalLayer('partner', 'NAP Partner', '#c4874e', 1),
            ],
            [],
            [$this->city('partner-city', 'partner', 200, 200)],
        );

        try {
            app(UpdateTerritoryPlanAlliances::class)->handle(
                $actor->playerId,
                $created->planId,
                $saved->revision,
                [$this->linkedLayer('alpha', $actorAlliance->allianceId, $actorAlliance->name, '#4da3ff', 0)],
            );
            self::fail('Expected an Alliance layer with planned objects to be protected from removal.');
        } catch (ValidationException) {
            self::assertSame($saved->revision, TerritoryPlan::query()->findOrFail($created->planId)->revision);
            self::assertSame(2, TerritoryPlanAlliance::query()->where('territory_plan_id', $created->planId)->count());
            self::assertSame(1, TerritoryPlanObject::query()->where('territory_plan_id', $created->planId)->count());
        }
    }

    public function test_alliance_scoped_plan_rejects_independent_participant_layer_management(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 63009);
        $alliance = $scenario->alliance($actor);
        $created = app(CreateTerritoryPlan::class)->handle(
            $actor->playerId,
            TerritoryPlanScope::Alliance,
            $actor->kingdomId,
            $alliance->allianceId,
            'Alliance Hive',
            self::DATASET_ID,
        );

        $this->expectException(ValidationException::class);
        app(UpdateTerritoryPlanAlliances::class)->handle(
            $actor->playerId,
            $created->planId,
            $created->revision,
            [$this->linkedLayer('owner', $alliance->allianceId, $alliance->name, '#4da3ff', 0)],
        );
    }

    public function test_kingdom_plan_rejects_linked_alliance_from_another_kingdom(): void
    {
        $scenario = new ScenarioFactory;
        $actorAccount = $scenario->authUser();
        $actor = $scenario->player((int) $actorAccount->id, 63011);
        $actorAlliance = $scenario->alliance($actor);
        $foreignAccount = $scenario->authUser();
        $foreignPlayer = $scenario->player((int) $foreignAccount->id, 63012);
        $foreignAlliance = $scenario->alliance($foreignPlayer);
        app(BootstrapKingdomAdministrator::class)->handle($actor->kingdomId, $actor->playerId);

        $created = app(CreateTerritoryPlan::class)->handle(
            $actor->playerId,
            TerritoryPlanScope::Kingdom,
            $actor->kingdomId,
            null,
            'Kingdom Boundary Test',
            self::DATASET_ID,
        );

        $this->expectException(ValidationException::class);
        app(SaveTerritoryPlan::class)->handle(
            $actor->playerId,
            $created->planId,
            $created->revision,
            [
                $this->linkedLayer('alpha', $actorAlliance->allianceId, $actorAlliance->name, '#4da3ff', 0),
                $this->linkedLayer('foreign', $foreignAlliance->allianceId, $foreignAlliance->name, '#c4874e', 1),
            ],
            [],
            [$this->city('alpha-city', 'alpha', 100, 100)],
        );
    }

    /** @return array<string, mixed> */
    private function linkedLayer(
        string $key,
        string $allianceId,
        string $name,
        string $color,
        int $sortOrder,
    ): array {
        return [
            'key' => $key,
            'alliance_id' => $allianceId,
            'external_name' => null,
            'external_tag' => null,
            'display_name' => $name,
            'presentation_color' => $color,
            'sort_order' => $sortOrder,
            'visible' => true,
            'locked' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function externalLayer(
        string $key,
        string $name,
        string $color,
        int $sortOrder,
        bool $visible = true,
        bool $locked = false,
    ): array {
        return [
            'key' => $key,
            'alliance_id' => null,
            'external_name' => $name,
            'external_tag' => null,
            'display_name' => $name,
            'presentation_color' => $color,
            'sort_order' => $sortOrder,
            'visible' => $visible,
            'locked' => $locked,
        ];
    }

    /** @return array<string, mixed> */
    private function city(string $key, string $allianceKey, int $x, int $y): array
    {
        return [
            'key' => $key,
            'alliance_key' => $allianceKey,
            'group_key' => null,
            'type' => 'governor_city',
            'player_id' => null,
            'external_player_name' => $key,
            'label' => $key,
            'x' => $x,
            'y' => $y,
            'rotation' => 0,
            'sort_order' => 0,
            'metadata' => [],
        ];
    }
}
