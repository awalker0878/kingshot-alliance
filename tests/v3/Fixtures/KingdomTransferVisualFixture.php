<?php

declare(strict_types=1);

namespace Tests\v3\Fixtures;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Actions\UpsertRosterEntry;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\KingdomTransfers\Actions\AssignTransferParticipantCohort;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferBlocker;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\RecordTransferKingdomCondition;
use App\Contexts\GameWorld\KingdomTransfers\Actions\RecordTransferObservation;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferCohort;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Actions\TransitionTransferReadiness;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferKingdomClassification;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferObservationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferReadinessState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferCohort;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\Players\Models\Player;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;

final class KingdomTransferVisualFixture
{
    public static function seed(): void
    {
        $now = CarbonImmutable::parse('2026-08-23 16:00:00', 'UTC');
        $currentThrough = CarbonImmutable::parse('2099-08-24 16:00:00', 'UTC');
        CarbonImmutable::setTestNow($now);

        $user = User::factory()->create([
            'name' => 'Kingdom Transfer Visual',
            'email' => 'transfer-visual@example.test',
            'password' => Hash::make('password'),
            'timezone' => 'UTC',
        ]);
        $home = Kingdom::query()->create(['number' => 1523, 'status' => 'active']);
        $target = Kingdom::query()->create(['number' => 1524, 'status' => 'active']);
        $actor = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $home->id,
            'game_player_id' => 'TRANSFER-VISUAL-A',
            'current_name' => 'Northstar Marshal',
        ]);
        $blocked = Player::query()->create([
            'current_kingdom_id' => $home->id,
            'game_player_id' => 'TRANSFER-VISUAL-B',
            'current_name' => 'Ember Vanguard',
        ]);
        $verify = Player::query()->create([
            'current_kingdom_id' => $home->id,
            'game_player_id' => 'TRANSFER-VISUAL-C',
            'current_name' => 'Frost Envoy',
        ]);

        $allianceId = app(CreateAlliance::class)->handle(
            (string) $actor->id,
            'Northern Crown',
            'northern-crown',
            'en',
            'UTC',
        );
        $rosters = [];
        foreach ([$actor, $blocked, $verify] as $player) {
            $rosters[(string) $player->id] = app(UpsertRosterEntry::class)->handle(
                actorPlayerId: (string) $actor->id,
                allianceId: $allianceId,
                attributes: [
                    'name' => (string) $player->current_name,
                    'game_player_id' => (string) $player->game_player_id,
                    'state' => RosterState::Active,
                ],
                expectedPlayerId: (string) $player->id,
            );
        }

        $windowId = app(SaveTransferWindow::class)->handle(
            $allianceId,
            (string) $actor->id,
            [
                'label' => 'August Kingdom Transfer',
                'pre_transfer_starts_at' => $now->subDays(3)->toIso8601String(),
                'invitational_starts_at' => $now->subDays(2)->toIso8601String(),
                'transfer_opens_at' => $now->subDay()->toIso8601String(),
                'ends_at' => $currentThrough->toIso8601String(),
                'source_type' => TransferSourceType::OfficialPublication,
                'source_reference' => 'Century Games Kingdom Transfer event notice',
                'observed_at' => $now->subDays(4)->toIso8601String(),
            ],
        );
        app(SaveTransferGroup::class)->handle(
            $allianceId,
            (string) $actor->id,
            $windowId,
            [
                'official_label' => 'Transfer Group 7',
                'kingdom_numbers' => [1523, 1524],
                'source_type' => TransferSourceType::InGame,
                'source_reference' => 'KingShot Transfer Group screen',
                'observed_at' => $now->subMinutes(45)->toIso8601String(),
            ],
        );
        app(RecordTransferKingdomCondition::class)->handle(
            $allianceId,
            (string) $actor->id,
            $windowId,
            1524,
            125_000_000,
            TransferKingdomClassification::Ordinary,
            TransferSourceType::InGame,
            'KingShot Kingdom 1524 transfer screen',
            $now->subMinutes(30)->toIso8601String(),
        );
        app(CreateTransferPlan::class)->handle(
            $allianceId,
            (string) $actor->id,
            ['label' => 'Northern Crown transfer board', 'transfer_window_id' => $windowId],
        );
        $plan = TransferPlan::query()->where('alliance_id', $allianceId)->firstOrFail();

        app(SaveTransferCohort::class)->handle(
            $allianceId,
            (string) $actor->id,
            (string) $plan->id,
            [
                'name' => 'K1524 Vanguard',
                'direction' => TransferDirection::Outgoing,
                'destination_kingdom' => 1524,
                'coordinator_player_id' => (string) $actor->id,
                'manager_notes' => 'Coordinate departures after final Bear Hunt.',
            ],
        );
        $cohort = TransferCohort::query()->where('transfer_plan_id', $plan->id)->firstOrFail();

        foreach ([$actor, $blocked, $verify] as $player) {
            $roster = $rosters[(string) $player->id];
            app(SaveTransferParticipant::class)->handle(
                $allianceId,
                (string) $actor->id,
                (string) $plan->id,
                [
                    'direction' => TransferDirection::Outgoing,
                    'roster_entry_id' => $roster->rosterEntryId,
                    'destination_kingdom' => 1524,
                ],
            );
            $participant = TransferParticipant::query()
                ->where('transfer_plan_id', $plan->id)
                ->where('player_id', $player->id)
                ->firstOrFail();
            app(AssignTransferParticipantCohort::class)->handle(
                $allianceId,
                (string) $actor->id,
                (string) $plan->id,
                (string) $participant->id,
                (string) $cohort->id,
            );
        }

        $eligible = TransferParticipant::query()->where('player_id', $actor->id)->firstOrFail();
        $gameBlocked = TransferParticipant::query()->where('player_id', $blocked->id)->firstOrFail();
        $needsVerification = TransferParticipant::query()->where('player_id', $verify->id)->firstOrFail();

        self::recordFacts($allianceId, (string) $actor->id, $plan, $eligible, $now, $currentThrough, true, false);
        self::recordFacts($allianceId, (string) $actor->id, $plan, $gameBlocked, $now, $currentThrough, false, false);
        self::recordFacts($allianceId, (string) $actor->id, $plan, $needsVerification, $now, $currentThrough, true, true);

        app(CreateTransferBlocker::class)->handle(
            $allianceId,
            (string) $actor->id,
            (string) $plan->id,
            (string) $eligible->id,
            'Confirm alliance hand-off time',
            'Planning remains open even though the Governor is eligible in-game.',
        );
        app(TransitionTransferReadiness::class)->handle(
            $allianceId,
            (string) $actor->id,
            (string) $plan->id,
            (string) $eligible->id,
            TransferReadinessState::Blocked,
        );

        app(TransitionTransferReadiness::class)->handle(
            $allianceId,
            (string) $actor->id,
            (string) $plan->id,
            (string) $gameBlocked->id,
            TransferReadinessState::Preparing,
        );
        app(TransitionTransferReadiness::class)->handle(
            $allianceId,
            (string) $actor->id,
            (string) $plan->id,
            (string) $gameBlocked->id,
            TransferReadinessState::Ready,
        );
        app(TransitionTransferReadiness::class)->handle(
            $allianceId,
            (string) $actor->id,
            (string) $plan->id,
            (string) $needsVerification->id,
            TransferReadinessState::Preparing,
        );
    }

    private static function recordFacts(
        string $allianceId,
        string $actorPlayerId,
        TransferPlan $plan,
        TransferParticipant $participant,
        CarbonImmutable $now,
        CarbonImmutable $currentThrough,
        bool $rulesVerified,
        bool $stalePower,
    ): void {
        $record = app(RecordTransferObservation::class);
        $validUntil = $currentThrough->toIso8601String();
        $record->handle(
            $allianceId,
            $actorPlayerId,
            (string) $plan->id,
            (string) $participant->id,
            TransferObservationKind::GovernorPower,
            118_000_000,
            TransferSourceType::InGame,
            'KingShot Governor transfer screen',
            $now->subMinutes(20)->toIso8601String(),
            ($stalePower ? $now->subMinute() : $currentThrough)->toIso8601String(),
        );
        $record->handle(
            $allianceId,
            $actorPlayerId,
            (string) $plan->id,
            (string) $participant->id,
            TransferObservationKind::TransferScore,
            $participant->observed_name === 'Northstar Marshal' ? 880 : 910,
            TransferSourceType::InGame,
            'KingShot Transfer Score screen',
            $now->subMinutes(18)->toIso8601String(),
            $validUntil,
        );
        $record->handle(
            $allianceId,
            $actorPlayerId,
            (string) $plan->id,
            (string) $participant->id,
            TransferObservationKind::TransferPassesAvailable,
            9,
            TransferSourceType::InGame,
            'KingShot Transfer Pass inventory',
            $now->subMinutes(15)->toIso8601String(),
            $validUntil,
        );
        $record->handle(
            $allianceId,
            $actorPlayerId,
            (string) $plan->id,
            (string) $participant->id,
            TransferObservationKind::TransferPassesRequired,
            9,
            TransferSourceType::InGame,
            'KingShot Kingdom 1524 transfer requirements',
            $now->subMinutes(15)->toIso8601String(),
            $validUntil,
        );
        $record->handle(
            $allianceId,
            $actorPlayerId,
            (string) $plan->id,
            (string) $participant->id,
            TransferObservationKind::InGameRulesVerified,
            $rulesVerified,
            TransferSourceType::InGame,
            'KingShot transfer eligibility screen',
            $now->subMinutes(10)->toIso8601String(),
            $validUntil,
            $rulesVerified ? null : 'KingShot reports an unresolved transfer requirement.',
        );
    }
}
