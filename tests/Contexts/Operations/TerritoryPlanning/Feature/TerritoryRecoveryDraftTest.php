<?php

declare(strict_types=1);

namespace Tests\Contexts\Operations\TerritoryPlanning\Feature;

use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\TerritoryPlanning\Actions\CreateTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\SaveTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryRecoveryDraft;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryRecoveryDrafts;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class TerritoryRecoveryDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_working_document_is_recoverable_without_mutating_validated_head(): void
    {
        [$actor, $plan] = $this->scenario();
        $model = TerritoryPlan::query()->findOrFail($plan);
        $document = $this->document([['key' => 'broken', 'type' => 'governor_city', 'x' => -999999]]);

        $stored = app(TerritoryRecoveryDrafts::class)->store(
            $actor->playerId,
            $plan,
            1,
            $model->map_dataset_id,
            $model->map_dataset_checksum,
            $document,
        );

        self::assertSame($document, $stored['document']);
        self::assertFalse($stored['stale']);
        self::assertSame(1, TerritoryPlan::query()->findOrFail($plan)->revision);
        self::assertSame(0, TerritoryPlan::query()->findOrFail($plan)->objects()->count());
        self::assertSame(1, TerritoryRecoveryDraft::query()->count());
    }

    public function test_recovery_reports_stale_after_the_canonical_head_advances(): void
    {
        [$actor, $plan, $layers] = $this->scenario();
        $model = TerritoryPlan::query()->findOrFail($plan);
        app(TerritoryRecoveryDrafts::class)->store(
            $actor->playerId,
            $plan,
            1,
            $model->map_dataset_id,
            $model->map_dataset_checksum,
            $this->document(),
        );
        app(SaveTerritoryPlan::class)->handle(
            $actor->playerId,
            $plan,
            1,
            (string) Str::uuid(),
            $layers,
            [],
            [['key' => 'city', 'alliance_key' => 'owner', 'type' => 'governor_city', 'x' => 100, 'y' => 100]],
        );

        $recovery = app(TerritoryRecoveryDrafts::class)->read($actor->playerId, $plan);
        self::assertNotNull($recovery);
        self::assertTrue($recovery['stale']);
        self::assertSame(1, $recovery['base_revision']);
        self::assertSame(2, $recovery['current_revision']);
    }

    public function test_recovery_discard_is_explicit_and_idempotent(): void
    {
        [$actor, $plan] = $this->scenario();
        $model = TerritoryPlan::query()->findOrFail($plan);
        app(TerritoryRecoveryDrafts::class)->store(
            $actor->playerId,
            $plan,
            1,
            $model->map_dataset_id,
            $model->map_dataset_checksum,
            $this->document(),
        );

        self::assertTrue(app(TerritoryRecoveryDrafts::class)->delete($actor->playerId, $plan));
        self::assertFalse(app(TerritoryRecoveryDrafts::class)->delete($actor->playerId, $plan));
        self::assertNull(app(TerritoryRecoveryDrafts::class)->read($actor->playerId, $plan));
    }

    public function test_revoked_management_blocks_recovery_read_write_and_delete(): void
    {
        [$actor, $plan] = $this->scenario();
        $model = TerritoryPlan::query()->findOrFail($plan);
        app(TerritoryRecoveryDrafts::class)->store(
            $actor->playerId,
            $plan,
            1,
            $model->map_dataset_id,
            $model->map_dataset_checksum,
            $this->document(),
        );
        AllianceMembership::query()->where('player_id', $actor->playerId)->update(['rank' => AllianceRank::R1->value]);

        foreach (['read', 'delete'] as $method) {
            try {
                app(TerritoryRecoveryDrafts::class)->{$method}($actor->playerId, $plan);
                self::fail("Revoked actor retained recovery {$method} authority.");
            } catch (AuthorizationException) {
                self::assertTrue(true);
            }
        }
        $this->expectException(AuthorizationException::class);
        app(TerritoryRecoveryDrafts::class)->store(
            $actor->playerId,
            $plan,
            1,
            $model->map_dataset_id,
            $model->map_dataset_checksum,
            $this->document(),
        );
    }

    public function test_recovery_rejects_wrong_map_pin(): void
    {
        [$actor, $plan] = $this->scenario();
        $model = TerritoryPlan::query()->findOrFail($plan);
        $this->expectException(ValidationException::class);
        app(TerritoryRecoveryDrafts::class)->store(
            $actor->playerId,
            $plan,
            1,
            $model->map_dataset_id,
            str_repeat('0', 64),
            $this->document(),
        );
    }

    /** @return array{PlayerReference,string,list<array<string,mixed>>} */
    private function scenario(): array
    {
        $factory = app(ScenarioFactory::class);
        $actor = $factory->player((int) $factory->authUser()->id, 61641);
        $alliance = $factory->alliance($actor);
        $created = app(CreateTerritoryPlan::class)->handle(
            $actor->playerId,
            TerritoryPlanScope::Alliance,
            $actor->kingdomId,
            $alliance->allianceId,
            'Recovery',
            'kingshot-evidence-backed-2026-09-06-v2',
        );

        return [$actor, $created->planId, [[
            'key' => 'owner',
            'alliance_id' => $alliance->allianceId,
            'display_name' => $alliance->name,
        ]]];
    }

    /** @param list<array<string,mixed>> $objects */
    private function document(array $objects = []): array
    {
        return [
            'alliances' => [],
            'groups' => [],
            'objects' => $objects,
            'preferences' => [],
        ];
    }
}
