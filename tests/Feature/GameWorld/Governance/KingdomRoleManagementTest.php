<?php

declare(strict_types=1);

namespace Tests\Feature\GameWorld\Governance;

use App\Contexts\GameWorld\Governance\Actions\AssignKingdomRole;
use App\Contexts\GameWorld\Governance\Actions\BootstrapKingdomAdministrator;
use App\Contexts\GameWorld\Governance\Actions\RemoveKingdomRole;
use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\V2\ScenarioFactory;
use Tests\TestCase;

final class KingdomRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_establishes_exactly_one_initial_administrator_and_emits_durable_events(): void
    {
        $scenario = (new ScenarioFactory)->claimedPlayer(4220, 'Initial Admin', 'game-4220-admin');
        $assignment = app(BootstrapKingdomAdministrator::class)->handle($scenario['kingdom'], $scenario['player']);

        self::assertSame($scenario['player']->id, $assignment->player_id);
        self::assertSame(DefaultKingdomRole::Administrator->value, $assignment->role->key);
        self::assertSame(1, DB::table('audit_events')->where('event', 'kingdom.role_bootstrapped')->count());
        self::assertSame(1, DB::table('outbox_messages')->where('event_type', 'kingdom.role_bootstrapped')->count());

        $this->expectException(ValidationException::class);
        app(BootstrapKingdomAdministrator::class)->handle($scenario['kingdom'], $scenario['player']);
    }

    public function test_authorized_administrator_assigns_roles_idempotently_only_inside_the_current_kingdom(): void
    {
        $factory = new ScenarioFactory;
        $admin = $factory->claimedPlayer(4221, 'Admin', 'game-4221-admin');
        $target = $factory->claimedPlayer(4221, 'Coordinator', 'game-4221-coordinator');
        $outsider = $factory->claimedPlayer(4222, 'Outsider', 'game-4222-outsider');
        app(BootstrapKingdomAdministrator::class)->handle($admin['kingdom'], $admin['player']);

        $first = app(AssignKingdomRole::class)->handle(
            $admin['player'],
            $admin['kingdom'],
            $target['player'],
            DefaultKingdomRole::EventCoordinator,
        );
        $second = app(AssignKingdomRole::class)->handle(
            $admin['player'],
            $admin['kingdom'],
            $target['player'],
            DefaultKingdomRole::EventCoordinator,
        );

        self::assertTrue($first->is($second));
        self::assertSame(1, KingdomRoleAssignment::query()->whereKey($first->id)->count());
        self::assertSame(1, DB::table('audit_events')->where('event', 'kingdom.role_assigned')->count());

        try {
            app(AssignKingdomRole::class)->handle(
                $admin['player'],
                $admin['kingdom'],
                $outsider['player'],
                DefaultKingdomRole::Viewer,
            );
            self::fail('Cross-Kingdom role assignment must fail.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('player_id', $exception->errors());
        }
    }

    public function test_role_removal_emits_events_and_cannot_remove_the_last_administrator(): void
    {
        $factory = new ScenarioFactory;
        $admin = $factory->claimedPlayer(4223, 'Admin', 'game-4223-admin');
        $target = $factory->claimedPlayer(4223, 'Viewer', 'game-4223-viewer');
        $adminAssignment = app(BootstrapKingdomAdministrator::class)->handle($admin['kingdom'], $admin['player']);
        $viewerAssignment = app(AssignKingdomRole::class)->handle(
            $admin['player'],
            $admin['kingdom'],
            $target['player'],
            DefaultKingdomRole::Viewer,
        );

        app(RemoveKingdomRole::class)->handle($admin['player'], $admin['kingdom'], $viewerAssignment);

        self::assertNull(KingdomRoleAssignment::query()->find($viewerAssignment->id));
        self::assertSame(1, DB::table('audit_events')->where('event', 'kingdom.role_removed')->count());
        self::assertSame(1, DB::table('outbox_messages')->where('event_type', 'kingdom.role_removed')->count());

        $this->expectException(ValidationException::class);
        app(RemoveKingdomRole::class)->handle($admin['player'], $admin['kingdom'], $adminAssignment);
    }
}
