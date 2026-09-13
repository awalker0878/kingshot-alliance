<?php

declare(strict_types=1);

namespace Tests\Contexts\Operations\TerritoryPlanning\Feature;

use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\TerritoryPlanning\Actions\CreateTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\SaveTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Exceptions\TerritoryRevisionConflict;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryActivity;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritorySaveReceipt;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class TerritorySaveReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_lost_response_replays_exact_receipt_without_duplicate_revision_audit_or_assignment(): void
    {
        [$actor, $plan, $layers] = $this->scenario();
        $id = (string) Str::uuid();
        $objects = [$this->city($actor->playerId)];
        $save = app(SaveTerritoryPlan::class);
        $first = $save->handle($actor->playerId, $plan, 1, $id, $layers, [], $objects);
        $retry = $save->handle($actor->playerId, $plan, 1, $id, $layers, [], $objects);
        self::assertEquals($first, $retry);
        self::assertSame($id, $retry->mutationId);
        self::assertSame(2, TerritoryPlan::query()->findOrFail($plan)->revision);
        self::assertSame(1, AuditEvent::query()->where('event', 'territory.plan.saved')->count());
        self::assertSame(1, TerritoryActivity::query()->where('kind', 'assigned')->count());
        self::assertSame(1, TerritorySaveReceipt::query()->count());
    }

    public function test_retry_does_not_overwrite_a_newer_saved_head(): void
    {
        [$actor, $plan, $layers] = $this->scenario();
        $id = (string) Str::uuid();
        $save = app(SaveTerritoryPlan::class);
        $first = $save->handle($actor->playerId, $plan, 1, $id, $layers, [], [$this->city()]);
        $save->handle($actor->playerId, $plan, 2, (string) Str::uuid(), $layers, [], [$this->city(x: 110)]);
        $retry = $save->handle($actor->playerId, $plan, 1, $id, $layers, [], [$this->city()]);
        self::assertEquals($first, $retry);
        self::assertSame(3, TerritoryPlan::query()->findOrFail($plan)->revision);
        self::assertSame(110, TerritoryPlan::query()->findOrFail($plan)->objects()->firstOrFail()->coordinate_x);
    }

    public function test_reusing_a_mutation_id_for_different_content_is_rejected(): void
    {
        [$actor, $plan, $layers] = $this->scenario();
        $id = (string) Str::uuid();
        $save = app(SaveTerritoryPlan::class);
        $save->handle($actor->playerId, $plan, 1, $id, $layers, [], [$this->city()]);
        try {
            $save->handle($actor->playerId, $plan, 1, $id, $layers, [], [$this->city(x: 110)]);
            self::fail('A mutation UUID was rebound to different content.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('mutation_id', $exception->errors());
        }
        self::assertSame(2, TerritoryPlan::query()->findOrFail($plan)->revision);
    }

    public function test_revoked_management_is_rechecked_before_receipt_replay(): void
    {
        [$actor, $plan, $layers] = $this->scenario();
        $id = (string) Str::uuid();
        app(SaveTerritoryPlan::class)->handle($actor->playerId, $plan, 1, $id, $layers, [], [$this->city()]);
        AllianceMembership::query()->where('player_id', $actor->playerId)->update(['rank' => AllianceRank::R1->value]);
        $this->expectException(AuthorizationException::class);
        app(SaveTerritoryPlan::class)->handle($actor->playerId, $plan, 1, $id, $layers, [], [$this->city()]);
    }

    public function test_owner_boundary_rejects_malformed_mutation_ids(): void
    {
        [$actor, $plan, $layers] = $this->scenario();
        $this->expectException(ValidationException::class);
        app(SaveTerritoryPlan::class)->handle($actor->playerId, $plan, 1, 'not-a-uuid', $layers, [], [$this->city()]);
    }

    public function test_expired_retry_fails_as_a_conflict_without_creating_another_revision(): void
    {
        [$actor, $plan, $layers] = $this->scenario();
        $id = (string) Str::uuid();
        app(SaveTerritoryPlan::class)->handle($actor->playerId, $plan, 1, $id, $layers, [], [$this->city()]);
        $this->travel(25)->hours();
        try {
            app(SaveTerritoryPlan::class)->handle($actor->playerId, $plan, 1, $id, $layers, [], [$this->city()]);
            self::fail('Expired replay wrote a new head.');
        } catch (TerritoryRevisionConflict $exception) {
            self::assertSame(2, $exception->currentRevision);
        }
        self::assertSame(2, TerritoryPlan::query()->findOrFail($plan)->revision);
    }

    public function test_receipt_payload_retention_is_bounded_to_twenty_per_actor_and_plan(): void
    {
        [$actor, $plan, $layers] = $this->scenario();
        for ($revision = 1; $revision <= 23; $revision++) {
            app(SaveTerritoryPlan::class)->handle($actor->playerId, $plan, $revision, (string) Str::uuid(), $layers, [], [$this->city()]);
        }
        self::assertSame(20, TerritorySaveReceipt::query()->count());
        self::assertSame(24, TerritoryPlan::query()->findOrFail($plan)->revision);
    }

    public function test_late_receipt_failure_rolls_back_layout_revision_audit_and_notification(): void
    {
        [$actor, $plan, $layers] = $this->scenario();
        $fail = true;
        DB::listen(static function (QueryExecuted $query) use (&$fail): void {
            if ($fail && str_starts_with($query->sql, 'insert into "territory_save_receipts"')) {
                $fail = false;
                throw new RuntimeException('Receipt storage failure.');
            }
        });
        try {
            app(SaveTerritoryPlan::class)->handle($actor->playerId, $plan, 1, (string) Str::uuid(), $layers, [], [$this->city($actor->playerId)]);
            self::fail('The receipt failure was not atomic.');
        } catch (RuntimeException $exception) {
            self::assertSame('Receipt storage failure.', $exception->getMessage());
        }
        self::assertSame(1, TerritoryPlan::query()->findOrFail($plan)->revision);
        self::assertSame(0, TerritoryPlan::query()->findOrFail($plan)->objects()->count());
        self::assertSame(0, AuditEvent::query()->where('event', 'territory.plan.saved')->count());
        self::assertSame(0, TerritoryActivity::query()->count());
        self::assertSame(0, TerritorySaveReceipt::query()->count());
    }

    /** @return array{PlayerReference,string,list<array<string,mixed>>} */
    private function scenario(): array
    {
        $factory = app(ScenarioFactory::class);
        $actor = $factory->player((int) $factory->authUser()->id, 61641);
        $alliance = $factory->alliance($actor);
        $created = app(CreateTerritoryPlan::class)->handle($actor->playerId, TerritoryPlanScope::Alliance,
            $actor->kingdomId, $alliance->allianceId, 'Save receipts', 'kingshot-evidence-backed-2026-09-06-v2');

        return [$actor, $created->planId, [['key' => 'owner', 'alliance_id' => $alliance->allianceId, 'display_name' => $alliance->name]]];
    }

    /** @return array<string,mixed> */
    private function city(?string $player = null, int $x = 100): array
    {
        return ['key' => 'city', 'alliance_key' => 'owner', 'type' => 'governor_city', 'x' => $x, 'y' => 100, 'player_id' => $player];
    }
}
