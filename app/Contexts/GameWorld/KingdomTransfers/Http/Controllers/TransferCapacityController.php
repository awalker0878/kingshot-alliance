<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Http\Controllers;

use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\KingdomTransfers\Actions\RecordTransferKingdomCapacity;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferCapacityReservation;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferInvitationAllocation;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferCapacityBucket;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferCapacityReservationState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferInvitationAllocationState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferInvitationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class TransferCapacityController extends Controller
{
    public function storeObservation(
        Request $request,
        AllianceContext $context,
        RecordTransferKingdomCapacity $record,
        string $window,
    ): RedirectResponse {
        /** @var array{kingdom_number:int,ordinary_invites_used?:int|null,transfer_opens_used?:int|null,special_invites_available?:int|null,source_type:string,source_reference:string,observed_at:string,is_correction?:bool,evidence_id?:string|null} $v */
        $v = $request->validate([
            'kingdom_number' => ['required', 'integer', 'min:1'],
            'ordinary_invites_used' => ['nullable', 'integer', 'min:0'],
            'transfer_opens_used' => ['nullable', 'integer', 'min:0'],
            'special_invites_available' => ['nullable', 'integer', 'between:0,3'],
            'source_type' => ['required', Rule::in(array_column(TransferSourceType::cases(), 'value'))],
            'source_reference' => ['required', 'string', 'max:2048'],
            'observed_at' => ['required', 'date'],
            'is_correction' => ['sometimes', 'boolean'],
            'evidence_id' => ['nullable', 'string', 'max:64'],
        ]);
        $scope = $context->scope();
        $record->handle(
            $scope->allianceId,
            $scope->playerId,
            $window,
            $v['kingdom_number'],
            $v['ordinary_invites_used'] ?? null,
            $v['transfer_opens_used'] ?? null,
            $v['special_invites_available'] ?? null,
            TransferSourceType::from($v['source_type']),
            $v['source_reference'],
            $v['observed_at'],
            $v['is_correction'] ?? false,
            $v['evidence_id'] ?? null,
        );

        return back()->with('actionReceipt', $this->receipt('transfer-capacity-observed'));
    }

    public function saveReservation(
        Request $request,
        AllianceContext $context,
        SaveTransferCapacityReservation $save,
        string $plan,
        string $participant,
    ): RedirectResponse {
        /** @var array{bucket:string,state:string,notes?:string|null} $v */
        $v = $request->validate([
            'bucket' => ['required', Rule::in(array_column(TransferCapacityBucket::cases(), 'value'))],
            'state' => ['required', Rule::in(array_column(TransferCapacityReservationState::cases(), 'value'))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $scope = $context->scope();
        $save->handle(
            $scope->allianceId,
            $scope->playerId,
            $plan,
            $participant,
            TransferCapacityBucket::from($v['bucket']),
            TransferCapacityReservationState::from($v['state']),
            $v['notes'] ?? null,
        );

        return back()->with('actionReceipt', $this->receipt('transfer-capacity-reservation-saved'));
    }

    public function saveInvitation(
        Request $request,
        AllianceContext $context,
        SaveTransferInvitationAllocation $save,
        string $plan,
        string $participant,
    ): RedirectResponse {
        /** @var array{kind:string,state:string,notes?:string|null} $v */
        $v = $request->validate([
            'kind' => ['required', Rule::in(array_column(TransferInvitationKind::cases(), 'value'))],
            'state' => ['required', Rule::in(array_column(TransferInvitationAllocationState::cases(), 'value'))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $scope = $context->scope();
        $save->handle(
            $scope->allianceId,
            $scope->playerId,
            $plan,
            $participant,
            TransferInvitationKind::from($v['kind']),
            TransferInvitationAllocationState::from($v['state']),
            $v['notes'] ?? null,
        );

        return back()->with('actionReceipt', $this->receipt('transfer-invitation-allocation-saved'));
    }
}
