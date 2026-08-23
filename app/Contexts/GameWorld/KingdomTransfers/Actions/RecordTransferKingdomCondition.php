<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\GameWorld\Kingdoms\Actions\ResolveKingdom;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferKingdomClassification;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferWindowPhase;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomConditionObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RecordTransferKingdomCondition
{
    public function __construct(private TransferWriteState $writeState, private TransferAuthorization $authority, private ResolveKingdom $kingdoms, private AuditRecorder $audit, private OutboxRecorder $outbox) {}

    public function handle(string $allianceId, string $actorPlayerId, string $windowId, int|string $kingdomNumber, ?int $powerCap, TransferKingdomClassification $classification, TransferSourceType $sourceType, string $sourceReference, string $observedAt, bool $isCorrection = false, ?string $evidenceId = null): string
    {
        return DB::transaction(function () use ($allianceId,$actorPlayerId,$windowId,$kingdomNumber,$powerCap,$classification,$sourceType,$sourceReference,$observedAt,$isCorrection,$evidenceId): string {
            $context = $this->writeState->lockAuthority($actorPlayerId, $allianceId); $this->authority->authorizeContext($context, TransferPermission::Manage);
            $window = TransferWindow::query()->where('alliance_id',$allianceId)->whereKey($windowId)->lockForUpdate()->firstOrFail();
            $kingdom = $this->kingdoms->handle($kingdomNumber); if ($kingdom === null) throw ValidationException::withMessages(['kingdom' => 'A target Kingdom is required.']);
            $sourceReference = trim($sourceReference); if ($sourceReference === '') throw ValidationException::withMessages(['source_reference' => 'A source reference is required.']);
            $observed = CarbonImmutable::parse($observedAt)->utc();
            $previous = TransferKingdomConditionObservation::query()->where('alliance_id',$allianceId)->where('transfer_window_id',$windowId)->where('kingdom_id',$kingdom->kingdomId)->orderByDesc('observed_at')->orderByDesc('id')->lockForUpdate()->first();
            if ($previous instanceof TransferKingdomConditionObservation && $previous->power_cap !== $powerCap && in_array($window->phaseAt(CarbonImmutable::now('UTC')), [TransferWindowPhase::InvitationalTransfer, TransferWindowPhase::TransferOpens, TransferWindowPhase::Closed], true)) {
                if (! $isCorrection || ! $sourceType->isAuthoritative()) throw ValidationException::withMessages(['power_cap' => 'The Power Cap is fixed after Phase II begins. A changed value must be recorded explicitly as an authoritative correction.']);
            }
            $fingerprint = hash('sha256', implode('|', [$allianceId,$windowId,$kingdom->kingdomId,(string)$powerCap,$classification->value,$sourceType->value,$sourceReference,$observed->toIso8601String(),$isCorrection?'1':'0']));
            $existing = TransferKingdomConditionObservation::query()->where('fingerprint',$fingerprint)->first(); if ($existing instanceof TransferKingdomConditionObservation) return (string) $existing->id;
            $row = TransferKingdomConditionObservation::query()->create(['alliance_id'=>$allianceId,'transfer_window_id'=>$windowId,'kingdom_id'=>$kingdom->kingdomId,'power_cap'=>$powerCap,'classification'=>$classification,'source_type'=>$sourceType,'source_reference'=>$sourceReference,'observed_at'=>$observed,'evidence_id'=>$evidenceId,'is_correction'=>$isCorrection,'fingerprint'=>$fingerprint,'recorded_by_player_id'=>$actorPlayerId]);
            $metadata=['alliance_id'=>$allianceId,'transfer_window_id'=>$windowId,'kingdom_id'=>$kingdom->kingdomId,'condition_observation_id'=>(string)$row->id,'source_type'=>$sourceType->value,'is_correction'=>$isCorrection];
            $this->audit->record('kingdoms.transfer_kingdom_condition_recorded',$context->actor,$row,null,$metadata); $this->outbox->record('kingdoms.transfer_kingdom_condition_recorded',$allianceId,$row,$metadata);
            return (string) $row->id;
        });
    }
}
