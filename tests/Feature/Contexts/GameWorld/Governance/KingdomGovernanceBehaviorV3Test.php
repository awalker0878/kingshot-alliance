<?php

declare(strict_types=1);

namespace Tests\Feature\Contexts\GameWorld\Governance;

use App\Contexts\GameWorld\Governance\Actions\AssignKingdomRole;
use App\Contexts\GameWorld\Governance\Actions\ExpireKingdomRoleAssignments;
use App\Contexts\GameWorld\Governance\Actions\HandoffKingdomAdministrator;
use App\Contexts\GameWorld\Governance\Actions\RemoveKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class KingdomGovernanceBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_bootstrap_is_scoped_idempotent_and_provisions_effective_authority(): void
    {
        $factory = new ScenarioFactory;
        $owner = $factory->account();
        $player = $factory->player($owner->userId, 13001);
        $kingdom = $factory->kingdom(13001);

        $assignment = app(BootstrapKingdomAdministrator::class)->handle($kingdom->kingdomId, $player->playerId);
        $again = app(BootstrapKingdomAdministrator::class)->handle($kingdom->kingdomId, $player->playerId);

        self::assertSame($assignment->assignmentId, $again->assignmentId);
        self::assertTrue(app(KingdomAuthorization::class)->allows($player->playerId, $kingdom->kingdomId, KingdomPermission::RoleManage));
        self::assertTrue(OutboxMessage::query()->where('event_type', 'kingdom.role_bootstrapped')->where('aggregate_id', $assignment->assignmentId)->exists());
    }

    public function test_bootstrap_rejects_player_from_another_kingdom_and_cannot_replace_historical_administrator(): void
    {
        $factory = new ScenarioFactory;
        $owner = $factory->account();
        $kingdom = $factory->kingdom(13002);
        $administrator = $factory->player($owner->userId, 13002);
        $otherKingdomPlayer = $factory->player($owner->userId, 13003);
        app(BootstrapKingdomAdministrator::class)->handle($kingdom->kingdomId, $administrator->playerId);

        try {
            app(BootstrapKingdomAdministrator::class)->handle($kingdom->kingdomId, $otherKingdomPlayer->playerId);
            self::fail('Expected cross-Kingdom bootstrap validation.');
        } catch (ValidationException) {
            self::assertTrue(true);
        }

        $replacement = $factory->player($owner->userId, 13002);
        $this->expectException(ValidationException::class);
        app(BootstrapKingdomAdministrator::class)->handle($kingdom->kingdomId, $replacement->playerId);
    }

    public function test_assignment_is_idempotent_cross_kingdom_safe_and_final_administrator_cannot_be_revoked(): void
    {
        $factory = new ScenarioFactory;
        $owner = $factory->account();
        $administrator = $factory->player($owner->userId, 13010);
        $target = $factory->player($owner->userId, 13010);
        $outsider = $factory->player($owner->userId, 13011);
        $kingdom = $factory->kingdom(13010);
        $bootstrap = app(BootstrapKingdomAdministrator::class)->handle($kingdom->kingdomId, $administrator->playerId);
        $viewer = KingdomRole::query()->where('kingdom_id', $kingdom->kingdomId)->where('key', DefaultKingdomRole::Viewer->value)->firstOrFail();

        $first = app(AssignKingdomRole::class)->handle($administrator->playerId, $kingdom->kingdomId, $target->playerId, (string) $viewer->id);
        $second = app(AssignKingdomRole::class)->handle($administrator->playerId, $kingdom->kingdomId, $target->playerId, (string) $viewer->id);
        self::assertSame($first, $second);

        try {
            app(AssignKingdomRole::class)->handle($administrator->playerId, $kingdom->kingdomId, $outsider->playerId, (string) $viewer->id);
            self::fail('Expected cross-Kingdom assignment validation.');
        } catch (ValidationException) {
            self::assertTrue(true);
        }

        $this->expectException(ValidationException::class);
        app(RemoveKingdomRole::class)->handle($administrator->playerId, $kingdom->kingdomId, $bootstrap->assignmentId, 'unsafe final-admin removal');
    }

    public function test_administrator_handoff_can_replace_actor_without_zero_admin_window(): void
    {
        $factory = new ScenarioFactory;
        $owner = $factory->account();
        $administrator = $factory->player($owner->userId, 13020);
        $replacement = $factory->player($owner->userId, 13020);
        $kingdom = $factory->kingdom(13020);
        app(BootstrapKingdomAdministrator::class)->handle($kingdom->kingdomId, $administrator->playerId);

        app(HandoffKingdomAdministrator::class)->handle($administrator->playerId, $kingdom->kingdomId, $replacement->playerId, true, 'planned handoff');

        $authorization = app(KingdomAuthorization::class);
        self::assertFalse($authorization->allows($administrator->playerId, $kingdom->kingdomId, KingdomPermission::RoleManage));
        self::assertTrue($authorization->allows($replacement->playerId, $kingdom->kingdomId, KingdomPermission::RoleManage));
        self::assertSame(1, KingdomRoleAssignment::query()->effective()->where('kingdom_id', $kingdom->kingdomId)->whereHas('role', static fn ($query) => $query->where('key', DefaultKingdomRole::Administrator->value))->count());
    }

    public function test_future_and_expired_delegations_never_authorize_and_expiry_side_effect_is_idempotent(): void
    {
        $now = Carbon::parse('2026-09-06T18:00:00-04:00');
        Carbon::setTestNow($now);
        try {
            $factory = new ScenarioFactory;
            $owner = $factory->account();
            $administrator = $factory->player($owner->userId, 13030);
            $target = $factory->player($owner->userId, 13030);
            $kingdom = $factory->kingdom(13030);
            app(BootstrapKingdomAdministrator::class)->handle($kingdom->kingdomId, $administrator->playerId);
            $administratorRole = KingdomRole::query()->where('kingdom_id', $kingdom->kingdomId)->where('key', DefaultKingdomRole::Administrator->value)->firstOrFail();

            $assignmentId = app(AssignKingdomRole::class)->handle(
                $administrator->playerId,
                $kingdom->kingdomId,
                $target->playerId,
                (string) $administratorRole->id,
                $now->copy()->addHour()->toIso8601String(),
                $now->copy()->addHours(2)->toIso8601String(),
                'temporary backup administrator',
            );
            self::assertFalse(app(KingdomAuthorization::class)->allows($target->playerId, $kingdom->kingdomId, KingdomPermission::RoleManage));

            Carbon::setTestNow($now->copy()->addMinutes(90));
            self::assertTrue(app(KingdomAuthorization::class)->allows($target->playerId, $kingdom->kingdomId, KingdomPermission::RoleManage));

            Carbon::setTestNow($now->copy()->addHours(3));
            self::assertFalse(app(KingdomAuthorization::class)->allows($target->playerId, $kingdom->kingdomId, KingdomPermission::RoleManage));
            self::assertSame(1, app(ExpireKingdomRoleAssignments::class)->handle(100));
            self::assertSame(0, app(ExpireKingdomRoleAssignments::class)->handle(100));
            self::assertNotNull(KingdomRoleAssignment::query()->findOrFail($assignmentId)->revoked_at);
        } finally {
            Carbon::setTestNow();
        }
    }
}
