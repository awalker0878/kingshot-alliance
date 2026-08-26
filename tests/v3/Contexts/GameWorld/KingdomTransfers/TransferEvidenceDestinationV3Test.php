<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\KingdomTransfers;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\ValueObjects\RosterEntryReference;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\RecordGovernorStatusEvidence;
use App\Contexts\GameWorld\KingdomTransfers\Actions\RecordOfficialTransferGroupEvidence;
use App\Contexts\GameWorld\KingdomTransfers\Actions\RecordTransferInvitationEvidence;
use App\Contexts\GameWorld\KingdomTransfers\Actions\RecordTransferKingdomRulesEvidence;
use App\Contexts\GameWorld\KingdomTransfers\Actions\RecordTransferScorePassEvidence;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferInvitationStatus;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferKingdomClassification;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferObservationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferEvidenceReceipt;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomConditionObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Evidence\Contracts\EvidenceReferenceLookup;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class TransferEvidenceDestinationV3Test extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = CarbonImmutable::parse('2026-08-26T04:00:00Z');
        CarbonImmutable::setTestNow($this->now);
        $this->app->instance(EvidenceReferenceLookup::class, new class implements EvidenceReferenceLookup
        {
            public function belongsToAlliance(string $evidenceId, string $allianceId): bool
            {
                return str_starts_with($evidenceId, 'approved-evidence-');
            }

            public function isApprovedForAlliance(string $evidenceId, string $allianceId): bool
            {
                return str_starts_with($evidenceId, 'approved-evidence-');
            }
        });
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_score_pass_commit_is_atomic_and_destination_idempotency_is_separate_from_semantic_duplicates(): void
    {
        $scenario = $this->outgoingScenario(7601, 7602);
        $action = app(RecordTransferScorePassEvidence::class);
        $common = [
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            (string) $scenario['plan']->id,
            (string) $scenario['participant']->id,
            (string) $scenario['plan']->transfer_window_id,
            (string) $scenario['participant']->destination_kingdom_id,
            'approved-evidence-score',
            'review-score-1',
            'transfer-score-passes/1',
            hash('sha256', 'score-pass-review-1'),
        ];

        try {
            $action->handle(
                ...$common,
                transferScore: 8_765_432,
                passesAvailable: 9,
                passesRequired: -1,
                observedAt: $this->now->subMinutes(5)->toIso8601String(),
                validUntil: $this->now->addHours(2)->toIso8601String(),
            );
            self::fail('The invalid third fact must reject the atomic score/pass handoff.');
        } catch (ValidationException) {
            self::assertSame(0, TransferObservation::query()->count());
            self::assertSame(0, TransferEvidenceReceipt::query()->count());
        }

        $first = $action->handle(
            ...$common,
            transferScore: 8_765_432,
            passesAvailable: 9,
            passesRequired: 12,
            observedAt: $this->now->subMinutes(5)->toIso8601String(),
            validUntil: $this->now->addHours(2)->toIso8601String(),
        );
        $repeat = $action->handle(
            ...$common,
            transferScore: 8_765_432,
            passesAvailable: 9,
            passesRequired: 12,
            observedAt: $this->now->subMinutes(5)->toIso8601String(),
            validUntil: $this->now->addHours(2)->toIso8601String(),
        );

        self::assertFalse($first->idempotentReplay);
        self::assertTrue($repeat->idempotentReplay);
        self::assertSame($first->receiptId, $repeat->receiptId);
        self::assertSame(3, TransferObservation::query()->count());
        self::assertSame(1, TransferEvidenceReceipt::query()->count());
        $expectedKinds = [
            TransferObservationKind::TransferScore->value,
            TransferObservationKind::TransferPassesAvailable->value,
            TransferObservationKind::TransferPassesRequired->value,
        ];
        sort($expectedKinds);
        self::assertSame(
            $expectedKinds,
            TransferObservation::query()->pluck('kind')->map(
                static fn ($kind): string => is_object($kind) && property_exists($kind, 'value') ? (string) $kind->value : (string) $kind,
            )->sort()->values()->all(),
        );
    }

    public function test_material_target_scope_change_after_review_is_rejected_before_destination_write(): void
    {
        $scenario = $this->outgoingScenario(7611, 7612);
        $oldTargetId = (string) $scenario['participant']->destination_kingdom_id;
        $newTarget = app(ScenarioFactory::class)->kingdom(7613);
        $scenario['participant']->forceFill(['destination_kingdom_id' => $newTarget->kingdomId])->save();

        $this->expectException(ValidationException::class);
        try {
            app(RecordTransferInvitationEvidence::class)->handle(
                allianceId: $scenario['alliance']->allianceId,
                actorPlayerId: $scenario['actor']->playerId,
                planId: (string) $scenario['plan']->id,
                participantId: (string) $scenario['participant']->id,
                expectedWindowId: (string) $scenario['plan']->transfer_window_id,
                expectedTargetKingdomId: $oldTargetId,
                evidenceId: 'approved-evidence-invite',
                reviewId: 'review-invite-1',
                schemaVersion: 'transfer-invitation/1',
                idempotencyKey: hash('sha256', 'invite-review-1'),
                status: TransferInvitationStatus::SpecialApproved,
                observedAt: $this->now->subMinutes(5)->toIso8601String(),
                validUntil: $this->now->addHour()->toIso8601String(),
            );
        } finally {
            self::assertSame(0, TransferObservation::query()->count());
            self::assertSame(0, TransferEvidenceReceipt::query()->count());
        }
    }

    public function test_each_schema_uses_a_dedicated_owner_action_and_none_creates_in_game_rules_verified(): void
    {
        $scenario = $this->outgoingScenario(7621, 7622);
        $allianceId = $scenario['alliance']->allianceId;
        $actorId = $scenario['actor']->playerId;
        $planId = (string) $scenario['plan']->id;
        $participantId = (string) $scenario['participant']->id;
        $windowId = (string) $scenario['plan']->transfer_window_id;
        $targetId = (string) $scenario['participant']->destination_kingdom_id;

        app(RecordGovernorStatusEvidence::class)->handle(
            $allianceId,
            $actorId,
            $planId,
            $participantId,
            $windowId,
            'approved-evidence-governor',
            'review-governor',
            'transfer-governor-status/1',
            hash('sha256', 'governor-review'),
            70_000_000,
            $this->now->subMinutes(10)->toIso8601String(),
            $this->now->addHours(2)->toIso8601String(),
        );
        app(RecordTransferInvitationEvidence::class)->handle(
            $allianceId,
            $actorId,
            $planId,
            $participantId,
            $windowId,
            $targetId,
            'approved-evidence-invitation',
            'review-invitation',
            'transfer-invitation/1',
            hash('sha256', 'invitation-review'),
            TransferInvitationStatus::OrdinaryReceived,
            $this->now->subMinutes(9)->toIso8601String(),
            $this->now->addHours(2)->toIso8601String(),
        );
        app(RecordTransferKingdomRulesEvidence::class)->handle(
            $allianceId,
            $actorId,
            $planId,
            $participantId,
            $windowId,
            $targetId,
            'approved-evidence-rules',
            'review-rules',
            'transfer-target-kingdom-rules/1',
            hash('sha256', 'rules-review'),
            80_000_000,
            TransferKingdomClassification::Ordinary,
            $this->now->subMinutes(8)->toIso8601String(),
        );
        app(RecordOfficialTransferGroupEvidence::class)->handle(
            $allianceId,
            $actorId,
            $planId,
            $participantId,
            $windowId,
            'approved-evidence-group',
            'review-group',
            'transfer-official-group/1',
            hash('sha256', 'group-review'),
            'Group 7',
            [$scenario['homeNumber'], $scenario['targetNumber']],
            $this->now->subMinutes(7)->toIso8601String(),
        );

        self::assertSame(4, TransferEvidenceReceipt::query()->count());
        self::assertSame(2, TransferObservation::query()->count());
        self::assertSame(1, TransferKingdomConditionObservation::query()->count());
        self::assertSame(1, TransferGroup::query()->whereNull('superseded_at')->count());
        self::assertFalse(TransferObservation::query()->where('kind', TransferObservationKind::InGameRulesVerified->value)->exists());
    }

    /**
     * @return array{
     *   actor:PlayerReference,
     *   alliance:AllianceReference,
     *   roster:RosterEntryReference,
     *   plan:TransferPlan,
     *   participant:TransferParticipant,
     *   homeNumber:int,
     *   targetNumber:int
     * }
     */
    private function outgoingScenario(int $homeNumber, int $targetNumber): array
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $actor = $scenarios->player($account->userId, $homeNumber, 'TRANSFER-EVIDENCE-'.$homeNumber);
        $alliance = $scenarios->alliance($actor);
        $roster = $scenarios->roster($actor, $alliance, $actor);
        $scenarios->kingdom($targetNumber);

        $windowId = app(SaveTransferWindow::class)->handle(
            $alliance->allianceId,
            $actor->playerId,
            [
                'label' => 'Evidence transfer window',
                'pre_transfer_starts_at' => $this->now->subDays(3)->toIso8601String(),
                'invitational_starts_at' => $this->now->subDays(2)->toIso8601String(),
                'transfer_opens_at' => $this->now->subDay()->toIso8601String(),
                'ends_at' => $this->now->addDay()->toIso8601String(),
                'source_type' => TransferSourceType::OfficialPublication,
                'source_reference' => 'Synthetic official Transfer Window fixture',
                'observed_at' => $this->now->subDays(4)->toIso8601String(),
            ],
        );
        app(CreateTransferPlan::class)->handle(
            $alliance->allianceId,
            $actor->playerId,
            ['label' => 'Evidence transfer plan', 'transfer_window_id' => $windowId],
        );
        $plan = TransferPlan::query()->where('alliance_id', $alliance->allianceId)->where('transfer_window_id', $windowId)->firstOrFail();
        app(SaveTransferParticipant::class)->handle(
            $alliance->allianceId,
            $actor->playerId,
            (string) $plan->id,
            [
                'direction' => TransferDirection::Outgoing,
                'roster_entry_id' => $roster->rosterEntryId,
                'destination_kingdom' => $targetNumber,
            ],
        );
        $participant = TransferParticipant::query()->where('transfer_plan_id', $plan->id)->firstOrFail();

        return compact('actor', 'alliance', 'roster', 'plan', 'participant', 'homeNumber', 'targetNumber');
    }
}
