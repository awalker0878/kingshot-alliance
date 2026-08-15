<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Communications\Reminders\Models\EventReminderDelivery;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Actions\CreateEvent;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Models\EventRosterMember;
use App\Contexts\Operations\EventCore\Models\EventType;
use App\Contexts\Operations\EventCore\Queries\EventAttentionQuery;
use App\Contexts\Operations\EventCore\Services\EventTypeRegistry;
use App\Contexts\Operations\Participation\Actions\RespondToEvent;
use App\Contexts\Operations\Participation\Actions\RespondToEventRosterAssignment;
use App\Contexts\Operations\Participation\Enums\EventResponseChoice;
use App\Contexts\Operations\Reminders\Actions\CreateEventReminderRule;
use App\Contexts\Operations\Reminders\Enums\EventReminderAudience;
use App\Contexts\Operations\Rosters\Actions\AssignEventRosterPlayer;
use App\Contexts\Operations\Rosters\Actions\SaveEventRoster;
use App\Contexts\Operations\Rosters\Enums\EventRosterMemberStatus;
use App\Contexts\Operations\Rosters\Enums\EventRosterType;
use App\Domain\Kingdoms\Actions\SaveRosterEntry;
use App\Domain\Notifications\Actions\QueueDueEventReminders;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class EventRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_swordland_materializes_combatants_and_substitutes_with_shared_assignment_group(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8301, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8301-r5',
            'current_name' => 'Swordland R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Swordland Roster', 'swordland-roster');
        $type = EventType::query()->where('slug', 'swordland-showdown')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $ownerPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 60,
        );
        $occurrence = $event->occurrences->firstOrFail();
        $rosters = $occurrence->rosters()->orderBy('sort_order')->get();

        self::assertSame(['combatants', 'substitutes'], $rosters->pluck('key')->all());
        self::assertSame([30, 10], $rosters->pluck('capacity')->all());
        self::assertSame(['battlefield'], $rosters->pluck('assignment_group')->unique()->values()->all());
    }

    public function test_summit_materializes_two_legions_with_combatant_and_substitute_children(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8302, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8302-r5',
            'current_name' => 'Summit R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Summit Roster', 'summit-roster');
        $type = EventType::query()->where('slug', 'swordland-summit-league')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $ownerPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 60,
        );
        $rosters = $event->occurrences->firstOrFail()->rosters()->get()->keyBy('key');

        self::assertCount(6, $rosters);
        self::assertNull($rosters['legion-1']->parent_id);
        self::assertNull($rosters['legion-2']->parent_id);
        self::assertSame((string) $rosters['legion-1']->id, (string) $rosters['legion-1-combatants']->parent_id);
        self::assertSame((string) $rosters['legion-1']->id, (string) $rosters['legion-1-substitutes']->parent_id);
        self::assertSame(30, $rosters['legion-1-combatants']->capacity);
        self::assertSame(20, $rosters['legion-1-substitutes']->capacity);
        self::assertSame('league', $rosters['legion-2-combatants']->assignment_group);
    }

    public function test_assignment_moves_player_between_mutually_exclusive_rosters_and_records_live_warning(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8303, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8303-player',
            'current_name' => 'Move Player',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Roster Move', 'roster-move');
        $this->app->make(SaveRosterEntry::class)->handle($alliance, $ownerPlayer, [
            'name' => 'Move Player',
            'game_player_id' => '8303-player',
        ]);
        $type = EventType::query()->where('slug', 'swordland-showdown')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $ownerPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 60,
        );
        $occurrence = $event->occurrences->firstOrFail();
        $combatants = $occurrence->rosters()->where('key', 'combatants')->sole();
        $substitutes = $occurrence->rosters()->where('key', 'substitutes')->sole();

        $this->app->make(RespondToEvent::class)->handle($ownerPlayer, $occurrence, $ownerPlayer, EventResponseChoice::Unavailable);
        $assign = $this->app->make(AssignEventRosterPlayer::class);
        $firstAssignment = $assign->handle($ownerPlayer, $combatants, $ownerPlayer, slotNumber: 1);
        self::assertContains('unavailable', $firstAssignment->assignment_warnings);

        $secondAssignment = $assign->handle($ownerPlayer, $substitutes, $ownerPlayer, slotNumber: 1);
        self::assertSame(EventRosterMemberStatus::Removed, $firstAssignment->refresh()->status);
        self::assertSame(EventRosterMemberStatus::Assigned, $secondAssignment->status);
        self::assertSame(
            1,
            EventRosterMember::query()
                ->where('player_id', $ownerPlayer->id)
                ->whereIn('status', [EventRosterMemberStatus::Assigned->value, EventRosterMemberStatus::Confirmed->value])
                ->count(),
        );
    }

    public function test_decline_frees_capacity_and_slot_for_another_player(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'roster-capacity@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 8304, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8304-first',
            'current_name' => 'First Player',
        ]);
        $memberPlayer = Player::query()->create([
            'user_id' => $member->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8304-second',
            'current_name' => 'Second Player',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Roster Capacity', 'roster-capacity');
        $saveRoster = $this->app->make(SaveRosterEntry::class);
        $saveRoster->handle($alliance, $ownerPlayer, ['name' => 'First Player', 'game_player_id' => '8304-first']);
        $saveRoster->handle($alliance, $ownerPlayer, ['name' => 'Second Player', 'game_player_id' => '8304-second']);
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $ownerPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 60,
        );
        $occurrence = $event->occurrences->firstOrFail();
        $roster = $this->app->make(SaveEventRoster::class)->handle(
            $ownerPlayer,
            $occurrence,
            'main',
            EventRosterType::Roster,
            'battlefield',
            'Main',
            capacity: 1,
        );
        $assignment = $this->app->make(AssignEventRosterPlayer::class)->handle($ownerPlayer, $roster, $ownerPlayer, slotNumber: 1);

        $this->app->make(RespondToEventRosterAssignment::class)->handle(
            $ownerPlayer,
            $assignment,
            $ownerPlayer,
            EventRosterMemberStatus::Declined,
        );
        self::assertSame(EventRosterMemberStatus::Declined, $assignment->refresh()->status);

        $secondAssignment = $this->app->make(AssignEventRosterPlayer::class)->handle($ownerPlayer, $roster, $memberPlayer, slotNumber: 1);
        self::assertSame(EventRosterMemberStatus::Assigned, $secondAssignment->status);
    }

    public function test_self_confirmation_uses_active_player_and_cannot_impersonate_another_owned_player(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8305, 'status' => 'active']);
        $firstPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8305-first',
            'current_name' => 'Context One',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8305-second',
            'current_name' => 'Context Two',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($firstPlayer, 'Roster Context', 'roster-context');
        $saveRoster = $this->app->make(SaveRosterEntry::class);
        $saveRoster->handle($alliance, $firstPlayer, ['name' => 'Context One', 'game_player_id' => '8305-first']);
        $saveRoster->handle($alliance, $firstPlayer, ['name' => 'Context Two', 'game_player_id' => '8305-second']);
        $type = EventType::query()->where('slug', 'swordland-showdown')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $firstPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 60,
        );
        $occurrence = $event->occurrences->firstOrFail();
        $roster = $occurrence->rosters()->where('key', 'combatants')->sole();
        $assignment = $this->app->make(AssignEventRosterPlayer::class)->handle($firstPlayer, $roster, $secondPlayer);

        $this->actingAs($owner)
            ->withSession([(string) config('game_world.active_player_session_key') => (string) $firstPlayer->id])
            ->put('/events/'.$occurrence->id.'/roster-members/'.$assignment->id.'/response', ['status' => 'confirmed'])
            ->assertForbidden();

        self::assertSame(EventRosterMemberStatus::Assigned, $assignment->refresh()->status);
    }

    public function test_rostered_reminders_and_attention_track_only_active_assignments(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'roster-reminder@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 8306, 'status' => 'active']);
        $firstPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8306-first',
            'current_name' => 'Reminder One',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $member->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8306-second',
            'current_name' => 'Reminder Two',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($firstPlayer, 'Roster Reminder', 'roster-reminder');
        $saveRoster = $this->app->make(SaveRosterEntry::class);
        $saveRoster->handle($alliance, $firstPlayer, ['name' => 'Reminder One', 'game_player_id' => '8306-first']);
        $saveRoster->handle($alliance, $firstPlayer, ['name' => 'Reminder Two', 'game_player_id' => '8306-second']);
        $type = EventType::query()->where('slug', 'swordland-showdown')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $firstPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addMinutes(30),
            durationMinutes: 60,
        );
        $occurrence = $event->occurrences->firstOrFail();
        $roster = $occurrence->rosters()->where('key', 'combatants')->sole();
        $assign = $this->app->make(AssignEventRosterPlayer::class);
        $firstAssignment = $assign->handle($firstPlayer, $roster, $firstPlayer);
        $secondAssignment = $assign->handle($firstPlayer, $roster, $secondPlayer);

        self::assertTrue(collect($this->app->make(EventAttentionQuery::class)->for($firstPlayer))->contains(
            static fn (array $item): bool => $item['action'] === 'roster_confirmation'
                && $item['rosterMemberId'] === (string) $firstAssignment->id,
        ));
        $this->app->make(RespondToEventRosterAssignment::class)->handle(
            $firstPlayer,
            $firstAssignment,
            $firstPlayer,
            EventRosterMemberStatus::Confirmed,
        );
        self::assertFalse(collect($this->app->make(EventAttentionQuery::class)->for($firstPlayer))->contains(
            static fn (array $item): bool => $item['action'] === 'roster_confirmation',
        ));

        $this->app->make(RespondToEventRosterAssignment::class)->handle(
            $secondPlayer,
            $secondAssignment,
            $secondPlayer,
            EventRosterMemberStatus::Declined,
        );
        $rule = $this->app->make(CreateEventReminderRule::class)->handle(
            $firstPlayer,
            $event,
            60,
            EventReminderAudience::Rostered,
        );

        self::assertSame(1, $this->app->make(QueueDueEventReminders::class)->handle());
        self::assertSame(
            [(string) $firstPlayer->id],
            EventReminderDelivery::query()
                ->where('rule_id', $rule->id)
                ->pluck('player_id')
                ->map(static fn ($id): string => (string) $id)
                ->all(),
        );
    }

    public function test_kingdom_roster_can_snapshot_players_from_different_alliances(): void
    {
        $adminUser = User::factory()->create();
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8307, 'status' => 'active']);
        $adminPlayer = Player::query()->create([
            'user_id' => $adminUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8307-admin',
            'current_name' => 'Kingdom Administrator',
        ]);
        $firstPlayer = Player::query()->create([
            'user_id' => $firstOwner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8307-a',
            'current_name' => 'Kingdom A Player',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $secondOwner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8307-b',
            'current_name' => 'Kingdom B Player',
        ]);
        $createAlliance = $this->app->make(CreateAlliance::class);
        $firstAlliance = $createAlliance->handle($firstPlayer, 'Kingdom A', 'kingdom-a');
        $secondAlliance = $createAlliance->handle($secondPlayer, 'Kingdom B', 'kingdom-b');
        $saveRoster = $this->app->make(SaveRosterEntry::class);
        $saveRoster->handle($firstAlliance, $firstPlayer, ['name' => 'Kingdom A Player', 'game_player_id' => '8307-a']);
        $saveRoster->handle($secondAlliance, $secondPlayer, ['name' => 'Kingdom B Player', 'game_player_id' => '8307-b']);
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
        $roster = $this->app->make(SaveEventRoster::class)->handle(
            $adminPlayer,
            $occurrence,
            'kingdom-main',
            EventRosterType::Roster,
            'kingdom',
            'Kingdom Main',
        );
        $assign = $this->app->make(AssignEventRosterPlayer::class);
        $firstAssignment = $assign->handle($adminPlayer, $roster, $firstPlayer);
        $secondAssignment = $assign->handle($adminPlayer, $roster, $secondPlayer);

        self::assertSame((string) $firstAlliance->id, (string) $firstAssignment->alliance_id);
        self::assertSame((string) $secondAlliance->id, (string) $secondAssignment->alliance_id);
    }

    public function test_parent_roster_rejects_direct_player_assignment(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8308, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8308-player',
            'current_name' => 'Parent Player',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Roster Parent', 'roster-parent');
        $this->app->make(SaveRosterEntry::class)->handle($alliance, $ownerPlayer, [
            'name' => 'Parent Player',
            'game_player_id' => '8308-player',
        ]);
        $type = EventType::query()->where('slug', 'swordland-summit-league')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $ownerPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 60,
        );
        $parent = $event->occurrences->firstOrFail()->rosters()->where('key', 'legion-1')->sole();

        $this->expectException(ValidationException::class);
        $this->app->make(AssignEventRosterPlayer::class)->handle($ownerPlayer, $parent, $ownerPlayer);
    }
}
