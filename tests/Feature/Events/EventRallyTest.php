<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\GameWorld\Services\PlayerContext;
use App\Domain\Authorization\Actions\BootstrapKingdomAdministrator;
use App\Domain\Events\Actions\CreateEvent;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\EventType;
use App\Domain\Events\Services\EventTypeRegistry;
use App\Domain\Kingdoms\Actions\SaveRosterEntry;
use App\Domain\Rallies\Actions\AssignRallyPlayer;
use App\Domain\Rallies\Actions\RespondRallyAssignment;
use App\Domain\Rallies\Actions\SavePlayerFormation;
use App\Domain\Rallies\Actions\SaveRallyGroup;
use App\Domain\Rallies\Enums\RallyAssignmentRole;
use App\Domain\Rallies\Enums\RallyAssignmentStatus;
use App\Domain\Rallies\Models\PlayerFormation;
use App\Domain\Rallies\Models\RallyAssignment;
use App\Domain\Rallies\ValueObjects\FormationComposition;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class EventRallyTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_formations_are_isolated_by_active_player_for_multi_player_user(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8401, 'status' => 'active']);
        $first = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8401-a',
            'current_name' => 'Player Alpha',
        ]);
        $second = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8401-b',
            'current_name' => 'Player Beta',
        ]);
        $this->app->make(CreateAlliance::class)->handle($first, 'Formation Identity', 'formation-identity');
        $context = $this->app->make(PlayerContext::class);
        $save = $this->app->make(SavePlayerFormation::class);

        $context->activate($first, $owner);
        $save->handle($first, $first, 'Alpha Formation', new FormationComposition(10, 10, 80), isDefault: true);
        $context->clear();
        $context->activate($second, $owner);
        $save->handle($second, $second, 'Beta Formation', new FormationComposition(20, 10, 70), isDefault: true);
        $context->clear();

        self::assertSame(['Alpha Formation'], PlayerFormation::query()->where('player_id', $first->id)->pluck('name')->all());
        self::assertSame(['Beta Formation'], PlayerFormation::query()->where('player_id', $second->id)->pluck('name')->all());
    }

    public function test_joiner_capacity_ignores_standby_and_lead_is_unique(): void
    {
        $owner = User::factory()->create();
        $one = User::factory()->create();
        $two = User::factory()->create();
        $three = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8402, 'status' => 'active']);
        $actor = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8402-r5',
            'current_name' => 'Rally R5',
        ]);
        $p1 = Player::query()->create([
            'user_id' => $one->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8402-1',
            'current_name' => 'One',
        ]);
        $p2 = Player::query()->create([
            'user_id' => $two->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8402-2',
            'current_name' => 'Two',
        ]);
        $p3 = Player::query()->create([
            'user_id' => $three->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8402-3',
            'current_name' => 'Three',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($actor, 'Rally Capacity', 'rally-capacity');
        $saveRoster = $this->app->make(SaveRosterEntry::class);
        $saveRoster->handle($alliance, $actor, ['name' => 'One', 'game_player_id' => '8402-1']);
        $saveRoster->handle($alliance, $actor, ['name' => 'Two', 'game_player_id' => '8402-2']);
        $saveRoster->handle($alliance, $actor, ['name' => 'Three', 'game_player_id' => '8402-3']);
        $type = EventType::query()->where('slug', 'bear-hunt')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $actor,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 60,
        );
        $occurrence = $event->occurrences->firstOrFail();
        $group = $this->app->make(SaveRallyGroup::class)->handle($actor, $occurrence, (string) $alliance->id, 'Bear 1', maxJoiners: 1);
        $assign = $this->app->make(AssignRallyPlayer::class);

        $assign->handle($actor, $group, $p1, RallyAssignmentRole::Standby);
        $assign->handle($actor, $group, $p2, RallyAssignmentRole::Joiner);

        $this->expectException(ValidationException::class);
        $assign->handle($actor, $group, $p3, RallyAssignmentRole::Joiner);
    }

    public function test_self_confirmation_cannot_act_as_another_owned_player(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8403, 'status' => 'active']);
        $first = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8403-a',
            'current_name' => 'Context A',
        ]);
        $second = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8403-b',
            'current_name' => 'Context B',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($first, 'Rally Context', 'rally-context');
        $saveRoster = $this->app->make(SaveRosterEntry::class);
        $saveRoster->handle($alliance, $first, ['name' => 'Context A', 'game_player_id' => '8403-a']);
        $saveRoster->handle($alliance, $first, ['name' => 'Context B', 'game_player_id' => '8403-b']);
        $type = EventType::query()->where('slug', 'bear-hunt')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $first,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 60,
        );
        $occurrence = $event->occurrences->firstOrFail();
        $group = $this->app->make(SaveRallyGroup::class)->handle($first, $occurrence, (string) $alliance->id, 'Bear 1');
        $assignment = $this->app->make(AssignRallyPlayer::class)->handle($first, $group, $second, RallyAssignmentRole::Joiner);

        $this->actingAs($owner)
            ->withSession([(string) config('game_world.active_player_session_key') => (string) $first->id])
            ->put('/events/'.$occurrence->id.'/rally-assignments/'.$assignment->id.'/response', ['status' => 'confirmed'])
            ->assertForbidden();

        self::assertSame(RallyAssignmentStatus::Assigned, $assignment->refresh()->status);
    }

    public function test_kingdom_event_supports_independent_alliance_rally_groups_and_rejects_cross_alliance_assignment(): void
    {
        $adminUser = User::factory()->create();
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8404, 'status' => 'active']);
        $adminPlayer = Player::query()->create([
            'user_id' => $adminUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8404-admin',
            'current_name' => 'Kingdom Administrator',
        ]);
        $firstPlayer = Player::query()->create([
            'user_id' => $firstOwner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8404-a',
            'current_name' => 'Alliance A Player',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $secondOwner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8404-b',
            'current_name' => 'Alliance B Player',
        ]);
        $createAlliance = $this->app->make(CreateAlliance::class);
        $firstAlliance = $createAlliance->handle($firstPlayer, 'Rally Kingdom A', 'rally-kingdom-a');
        $secondAlliance = $createAlliance->handle($secondPlayer, 'Rally Kingdom B', 'rally-kingdom-b');
        $saveRoster = $this->app->make(SaveRosterEntry::class);
        $saveRoster->handle($firstAlliance, $firstPlayer, ['name' => 'Alliance A Player', 'game_player_id' => '8404-a']);
        $saveRoster->handle($secondAlliance, $secondPlayer, ['name' => 'Alliance B Player', 'game_player_id' => '8404-b']);
        $this->app->make(BootstrapKingdomAdministrator::class)->handle($kingdom, $adminPlayer);
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Kingdom);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $adminPlayer,
            configuration: $configuration,
            target: $kingdom,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 60,
        );
        $occurrence = $event->occurrences->firstOrFail();
        $saveGroup = $this->app->make(SaveRallyGroup::class);
        $firstGroup = $saveGroup->handle($adminPlayer, $occurrence, (string) $firstAlliance->id, 'Alliance A Rally');
        $secondGroup = $saveGroup->handle($adminPlayer, $occurrence, (string) $secondAlliance->id, 'Alliance B Rally');
        $assign = $this->app->make(AssignRallyPlayer::class);

        $assign->handle($adminPlayer, $firstGroup, $firstPlayer, RallyAssignmentRole::Joiner);
        $assign->handle($adminPlayer, $secondGroup, $secondPlayer, RallyAssignmentRole::Joiner);
        self::assertSame(2, RallyAssignment::query()->count());

        $this->expectException(ValidationException::class);
        $assign->handle($adminPlayer, $firstGroup, $secondPlayer, RallyAssignmentRole::Joiner);
    }

    public function test_reconfirm_after_decline_rechecks_group_capacity(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8405, 'status' => 'active']);
        $first = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8405-a',
            'current_name' => 'First',
        ]);
        $second = Player::query()->create([
            'user_id' => $other->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8405-b',
            'current_name' => 'Second',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($first, 'Rally Reconfirm', 'rally-reconfirm');
        $saveRoster = $this->app->make(SaveRosterEntry::class);
        $saveRoster->handle($alliance, $first, ['name' => 'First', 'game_player_id' => '8405-a']);
        $saveRoster->handle($alliance, $first, ['name' => 'Second', 'game_player_id' => '8405-b']);
        $type = EventType::query()->where('slug', 'bear-hunt')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $first,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 60,
        );
        $group = $this->app->make(SaveRallyGroup::class)->handle($first, $event->occurrences->firstOrFail(), (string) $alliance->id, 'Bear', maxJoiners: 1);
        $assign = $this->app->make(AssignRallyPlayer::class);
        $firstAssignment = $assign->handle($first, $group, $first, RallyAssignmentRole::Joiner);
        $this->app->make(RespondRallyAssignment::class)->handle($first, $firstAssignment, $first, RallyAssignmentStatus::Declined);
        $assign->handle($first, $group, $second, RallyAssignmentRole::Joiner);

        $this->expectException(ValidationException::class);
        $this->app->make(RespondRallyAssignment::class)->handle($first, $firstAssignment, $first, RallyAssignmentStatus::Confirmed);
    }
}
