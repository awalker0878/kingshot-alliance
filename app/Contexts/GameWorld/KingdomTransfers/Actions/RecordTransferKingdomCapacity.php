<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\GameWorld\Kingdoms\Actions\ResolveKingdom;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomCapacityObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomConditionObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferEvidenceReferenceGuard;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferOfficialRulebook;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RecordTransferKingdomCapacity
{
    public function __construct(
        private TransferWriteState $writeState,
        private TransferAuthorization $authority,
        private ResolveKingdom $kingdoms,
        private TransferEvidenceReferenceGuard $evidence,
        private TransferOfficialRulebook $rules,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $allianceId,
        string $actorPlayerId,
        string $windowId,
        int|string $kingdomNumber,
        ?int $ordinaryInvitesUsed,
        ?int $transferOpensUsed,
        ?int $specialInvitesAvailable,
        TransferSourceType $sourceType,
        string $sourceReference,
        string $observedAt,
        bool $isCorrection = false,
        ?string $evidenceId = null,
    ): string {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $windowId, $kingdomNumber, $ordinaryInvitesUsed, $transferOpensUsed, $specialInvitesAvailable, $sourceType, $sourceReference, $observedAt, $isCorrection, $evidenceId): string {
            $context = $this->writeState->lockAuthority($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, TransferPermission::Manage);
            TransferWindow::query()->where('alliance_id', $allianceId)->whereKey($windowId)->lockForUpdate()->firstOrFail();
            $kingdom = $this->kingdoms->handle($kingdomNumber);
            if ($kingdom === null) {
                throw ValidationException::withMessages(['kingdom' => 'A target Kingdom is required.']);
            }
            if ($specialInvitesAvailable !== null && $specialInvitesAvailable > TransferOfficialRulebook::MAX_SPECIAL_INVITES) {
                throw ValidationException::withMessages(['special_invites_available' => 'Special Invite inventory cannot exceed the official maximum of 3.']);
            }

            $condition = TransferKingdomConditionObservation::query()
                ->where('alliance_id', $allianceId)
                ->where('transfer_window_id', $windowId)
                ->where('kingdom_id', $kingdom->kingdomId)
                ->orderByDesc('observed_at')
                ->orderByDesc('id')
                ->get()
                ->first(static fn (TransferKingdomConditionObservation $row): bool => $row->source_type->isAuthoritative());
            $official = $condition instanceof TransferKingdomConditionObservation && $condition->classification !== null
                ? $this->rules->capacity($condition->classification)
                : null;
            if ($ordinaryInvitesUsed !== null && $official !== null && $ordinaryInvitesUsed > $official['ordinary_invites']) {
                throw ValidationException::withMessages(['ordinary_invites_used' => 'Observed Ordinary Invite use exceeds the official capacity for this Kingdom classification.']);
            }
            if ($transferOpensUsed !== null && $official !== null && $transferOpensUsed > $official['transfer_opens']) {
                throw ValidationException::withMessages(['transfer_opens_used' => 'Observed Transfer Opens use exceeds the official capacity for this Kingdom classification.']);
            }

            $sourceReference = trim($sourceReference);
            if ($sourceReference === '') {
                throw ValidationException::withMessages(['source_reference' => 'A source reference is required.']);
            }
            $evidenceId = $this->evidence->assertUsable($allianceId, $sourceType, $evidenceId);
            $observed = CarbonImmutable::parse($observedAt)->utc();
            $fingerprint = hash('sha256', implode('|', [
                $allianceId,
                $windowId,
                $kingdom->kingdomId,
                (string) $ordinaryInvitesUsed,
                (string) $transferOpensUsed,
                (string) $specialInvitesAvailable,
                $sourceType->value,
                $sourceReference,
                $observed->toIso8601String(),
                $isCorrection ? '1' : '0',
                $evidenceId ?? '',
            ]));
            $existing = TransferKingdomCapacityObservation::query()->where('fingerprint', $fingerprint)->first();
            if ($existing instanceof TransferKingdomCapacityObservation) {
                return (string) $existing->id;
            }

            $row = TransferKingdomCapacityObservation::query()->create([
                'alliance_id' => $allianceId,
                'transfer_window_id' => $windowId,
                'kingdom_id' => $kingdom->kingdomId,
                'ordinary_invites_used' => $ordinaryInvitesUsed,
                'transfer_opens_used' => $transferOpensUsed,
                'special_invites_available' => $specialInvitesAvailable,
                'source_type' => $sourceType,
                'source_reference' => $sourceReference,
                'observed_at' => $observed,
                'evidence_id' => $evidenceId,
                'is_correction' => $isCorrection,
                'fingerprint' => $fingerprint,
                'recorded_by_player_id' => $context->actor->playerId,
            ]);
            $metadata = [
                'alliance_id' => $allianceId,
                'transfer_window_id' => $windowId,
                'kingdom_id' => $kingdom->kingdomId,
                'capacity_observation_id' => (string) $row->id,
                'source_type' => $sourceType->value,
                'is_correction' => $isCorrection,
            ];
            $this->audit->record('kingdoms.transfer_capacity_observed', $context->actor, $row, null, $metadata);
            $this->outbox->record('kingdoms.transfer_capacity_observed', $allianceId, $row, $metadata);

            return (string) $row->id;
        });
    }
}
