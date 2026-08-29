<?php

declare(strict_types=1);

namespace Tests\v3\Fixtures;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Actions\UpsertRosterEntry;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Intelligence\Observations\Actions\RecordKingdomAllianceObservation;
use App\Contexts\Intelligence\Observations\Actions\StartTrackingKingdomAlliance;
use App\Contexts\Intelligence\Roster\Models\PlayerSnapshot;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use Illuminate\Support\Facades\Hash;

final class CapabilityAcceptanceVisualFixture
{
    public static function seed(): void
    {
        $user = User::factory()->create([
            'name' => 'Capability Acceptance Visual',
            'email' => 'capability-acceptance-visual@example.test',
            'password' => Hash::make('password'),
            'timezone' => 'UTC',
        ]);
        $kingdom = Kingdom::query()->create(['number' => 1888, 'status' => 'active']);
        $player = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'GOV-CAPABILITY-ACCEPTANCE',
            'current_name' => 'Acceptance Marshal',
        ]);
        $allianceId = app(CreateAlliance::class)->handle(
            (string) $player->id,
            'Acceptance Vanguard',
            'acceptance-vanguard',
            'en',
            'UTC',
        );
        $roster = app(UpsertRosterEntry::class)->handle(
            actorPlayerId: (string) $player->id,
            allianceId: $allianceId,
            attributes: [
                'name' => (string) $player->current_name,
                'game_player_id' => (string) $player->game_player_id,
                'state' => RosterState::Active,
            ],
            expectedPlayerId: (string) $player->id,
        );
        foreach ([35, 1] as $daysAgo) {
            PlayerSnapshot::query()->create([
                'alliance_id' => $allianceId,
                'roster_entry_id' => $roster->rosterEntryId,
                'player_id' => (string) $player->id,
                'actor_player_id' => (string) $player->id,
                'observed_name' => (string) $player->current_name,
                'power' => $daysAgo === 35 ? 120000000 : 145000000,
                'progression_level' => $daysAgo === 35 ? 'TC2' : 'TC3',
                'captured_at' => now('UTC')->subDays($daysAgo),
                'source' => 'manual',
                'idempotency_key' => 'capability-acceptance-snapshot-'.$daysAgo,
            ]);
        }

        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))
            ->firstOrFail();
        app(CreateEvent::class)->handle(
            actorPlayerId: (string) $player->id,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $allianceId,
            firstLocalStart: now('UTC')->addDays(2)->startOfHour()->toImmutable(),
            title: 'Capability Acceptance Bear Hunt',
            durationMinutes: 60,
        );

        RecruitmentCandidate::query()->create([
            'alliance_id' => $allianceId,
            'player_id' => (string) $player->id,
            'full_name' => 'Acceptance Candidate',
            'email' => 'acceptance-visual-candidate@example.test',
            'source' => 'referral',
            'stage' => RecruitmentStage::Screening,
            'submitted_at' => now('UTC')->subDay(),
        ]);

        $trackingId = app(StartTrackingKingdomAlliance::class)->handle(
            $allianceId,
            (string) $player->id,
            [
                'current_name' => 'Timeline Watch',
                'current_tag' => 'TIME',
                'game_alliance_id' => 'capability-timeline-watch',
            ],
        );
        $record = app(RecordKingdomAllianceObservation::class);
        $record->handle($allianceId, (string) $player->id, $trackingId, [
            'observed_name' => 'Timeline Watch',
            'observed_tag' => 'TIME',
            'power' => '100000000',
            'member_count' => 50,
            'captured_at' => now('UTC')->subHours(2)->toIso8601String(),
        ]);
        $record->handle($allianceId, (string) $player->id, $trackingId, [
            'observed_name' => 'Timeline Watch',
            'observed_tag' => 'TIME',
            'power' => '125000000',
            'member_count' => 54,
            'captured_at' => now('UTC')->subHour()->toIso8601String(),
        ]);
    }
}
