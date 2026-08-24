<?php

declare(strict_types=1);

namespace Tests\v3\Fixtures;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Actions\UpsertRosterEntry;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventOccurrenceStatus;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Participation\Reminders\Enums\EventReminderAudience;
use App\Contexts\Operations\Participation\Reminders\Enums\EventReminderTrigger;
use App\Contexts\Operations\Participation\Reminders\Models\EventReminderRule;
use Illuminate\Support\Facades\Hash;

final class EventCommandVisualFixture
{
    public static function seed(): void
    {
        $user = User::factory()->create([
            'name' => 'Event Command Visual',
            'email' => 'event-command-visual@example.test',
            'password' => Hash::make('password'),
            'timezone' => 'UTC',
        ]);
        $kingdom = Kingdom::query()->create(['number' => 1777, 'status' => 'active']);
        $player = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'GOV-EVENT-COMMAND',
            'current_name' => 'Command Marshal',
        ]);
        $allianceId = app(CreateAlliance::class)->handle(
            (string) $player->id,
            'Event Vanguard',
            'event-vanguard',
            'en',
            'UTC',
        );
        app(UpsertRosterEntry::class)->handle(
            actorPlayerId: (string) $player->id,
            allianceId: $allianceId,
            attributes: [
                'name' => (string) $player->current_name,
                'game_player_id' => (string) $player->game_player_id,
                'state' => RosterState::Active,
            ],
            expectedPlayerId: (string) $player->id,
        );

        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas(
                'eventType',
                static fn ($query) => $query->where('slug', 'alliance-mobilization'),
            )
            ->firstOrFail();
        $created = app(CreateEvent::class)->handle(
            actorPlayerId: (string) $player->id,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $allianceId,
            firstLocalStart: now('UTC')->subHours(2)->startOfMinute()->toImmutable(),
            title: 'Event Command Visual',
            durationMinutes: 60,
        );
        if ($created->firstOccurrenceId === null) {
            return;
        }

        EventOccurrence::query()->whereKey($created->firstOccurrenceId)->update([
            'status' => EventOccurrenceStatus::Completed->value,
        ]);
        EventOccurrence::query()->create([
            'event_id' => $created->eventId,
            'starts_at' => now('UTC')->addDays(2)->startOfHour(),
            'ends_at' => now('UTC')->addDays(2)->startOfHour()->addHour(),
            'status' => EventOccurrenceStatus::Scheduled,
            'settings' => [],
        ]);
        EventReminderRule::query()->create([
            'event_id' => $created->eventId,
            'trigger_type' => EventReminderTrigger::BeforeStart,
            'minutes_before' => 60,
            'audience' => EventReminderAudience::AllScopePlayers,
            'channel' => 'database',
            'is_enabled' => true,
            'created_by_player_id' => (string) $player->id,
            'updated_by_player_id' => (string) $player->id,
        ]);
    }
}
