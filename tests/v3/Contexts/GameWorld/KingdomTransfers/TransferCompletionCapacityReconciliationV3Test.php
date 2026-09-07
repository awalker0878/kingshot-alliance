<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\KingdomTransfers;

use App\Contexts\GameWorld\KingdomTransfers\Actions\CompleteTransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\LockTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\OpenTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Actions\TransitionTransferReadiness;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferCapacityBucket;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferCapacityReservationState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferInvitationAllocationState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferInvitationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferReadinessState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferCapacityReservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferInvitationAllocation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class TransferCompletionCapacityReconciliationV3Test extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = CarbonImmutable::parse('2026-09-07T18:00:00Z');
        CarbonImmutable::setTestNow($this->now);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_completion_finalizes_consuming_capacity_and_invitation_commitments(): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $actor = $factory->player($account->userId, 7391, 'TRANSFER-COMMITMENT-ACTOR');
        $alliance = $factory->alliance($actor);
        $factory->roster($actor, $alliance, $actor);
        $incoming = $factory->unclaimedPlayer(7392, 'TRANSFER-COMMITMENT-INCOMING');

        $windowId = app(SaveTransferWindow::class)->handle(
            $alliance->allianceId,
            $actor->playerId,
            [
                'label' => 'Commitment reconciliation transfer',
                'pre_transfer_starts_at' => $this->now->subDays(3)->toIso8601String(),
                'invitational_starts_at' => $this->now->subDays(2)->toIso8601String(),
                'transfer_opens_at' => $this->now->subDay()->toIso8601String(),
                'ends_at' => $this->now->addDay()->toIso8601String(),
                'source_type' => TransferSourceType::OfficialPublication,
                'source_reference' => 'Century Games Kingdom Transfer publication',
                'observed_at' => $this->now->subDays(4)->toIso8601String(),
            ],
        );

        app(CreateTransferPlan::class)->handle(
            $alliance->allianceId,
            $actor->playerId,
            ['label' => 'Commitment reconciliation plan', 'transfer_window_id' => $windowId],
        );

        $plan = TransferPlan::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('transfer_window_id', $windowId)
            ->firstOrFail();

        $participant = TransferParticipant::query()->create([
            'alliance_id' => $alliance->allianceId,
            'transfer_plan_id' => $plan->id,
            'direction' => TransferDirection::Incoming,
            'readiness_state' => TransferReadinessState::NotStarted,
            'player_id' => $incoming->playerId,
            'observed_name' => $incoming->currentName,
            'game_player_id' => $incoming->gamePlayerId,
            'source_kingdom_id' => $incoming->kingdomId,
            'destination_kingdom_id' => $plan->home_kingdom_id,
        ]);

        $reservation = TransferCapacityReservation::query()->create([
            'alliance_id' => $alliance->allianceId,
            'transfer_window_id' => $windowId,
            'transfer_plan_id' => $plan->id,
            'transfer_participant_id' => $participant->id,
            'target_kingdom_id' => $plan->home_kingdom_id,
            'bucket' => TransferCapacityBucket::TransferOpen,
            'state' => TransferCapacityReservationState::Reserved,
            'reserved_at' => now(),
            'created_by_player_id' => $actor->playerId,
        ]);

        $allocation = TransferInvitationAllocation::query()->create([
            'alliance_id' => $alliance->allianceId,
            'transfer_window_id' => $windowId,
            'transfer_plan_id' => $plan->id,
            'transfer_participant_id' => $participant->id,
            'target_kingdom_id' => $plan->home_kingdom_id,
            'kind' => TransferInvitationKind::Ordinary,
            'state' => TransferInvitationAllocationState::Reserved,
            'created_by_player_id' => $actor->playerId,
        ]);

        $readiness = app(TransitionTransferReadiness::class);
        foreach ([TransferReadinessState::Preparing, TransferReadinessState::Ready, TransferReadinessState::Confirmed] as $state) {
            $readiness->handle(
                $alliance->allianceId,
                $actor->playerId,
                (string) $plan->id,
                (string) $participant->id,
                $state,
            );
        }

        app(OpenTransferPlan::class)->handle($alliance->allianceId, $actor->playerId, (string) $plan->id);
        app(LockTransferPlan::class)->handle($alliance->allianceId, $actor->playerId, (string) $plan->id);
        app(CompleteTransferParticipant::class)->handle(
            $alliance->allianceId,
            $actor->playerId,
            (string) $plan->id,
            (string) $participant->id,
        );

        self::assertSame(TransferCapacityReservationState::Confirmed, $reservation->fresh()?->state);
        self::assertNull($reservation->fresh()?->released_at);
        self::assertSame(TransferInvitationAllocationState::Accepted, $allocation->fresh()?->state);
        self::assertDatabaseHas('transfer_completions', [
            'transfer_plan_id' => (string) $plan->id,
            'transfer_participant_id' => (string) $participant->id,
        ]);
    }
}
