<?php

declare(strict_types=1);

namespace Tests\v3\Fixtures;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\Actions\CreateAlliance;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Rosters\Actions\AssignEventRosterPlayer;
use App\Contexts\Operations\Rosters\Models\EventRoster;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;

final class AllianceAssistantVisualFixture
{
    public static function seed(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-24 16:00:00', 'UTC'));

        $user = User::factory()->create([
            'name' => 'Assistant Visual',
            'email' => 'assistant-visual@example.test',
            'password' => Hash::make('password'),
            'timezone' => 'UTC',
        ]);
        $kingdom = Kingdom::query()->create(['number' => 1423, 'status' => 'active']);
        $player = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'GOV-ASSISTANT-A',
            'current_name' => 'Alliance Scribe',
        ]);
        $allianceId = app(CreateAlliance::class)->handle(
            (string) $player->id,
            'Aurora Vanguard',
            'aurora-vanguard',
            'en',
            'UTC',
        );

        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'swordland-showdown'))
            ->firstOrFail();
        $created = app(CreateEvent::class)->handle(
            actorPlayerId: (string) $player->id,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $allianceId,
            firstLocalStart: CarbonImmutable::parse('2026-08-29 20:00:00', 'UTC'),
            title: 'Swordland',
            durationMinutes: 60,
        );
        if ($created->firstOccurrenceId === null) {
            return;
        }

        $roster = EventRoster::query()
            ->where('occurrence_id', $created->firstOccurrenceId)
            ->where('key', 'combatants')
            ->firstOrFail();
        app(AssignEventRosterPlayer::class)->handle(
            actorPlayerId: (string) $player->id,
            occurrenceId: $created->firstOccurrenceId,
            rosterId: (string) $roster->id,
            playerId: (string) $player->id,
            role: 'Rally Lead',
            slotNumber: 7,
        );
    }
}
