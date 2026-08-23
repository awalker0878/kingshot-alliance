<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Http\Controllers;

use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\KingdomTransfers\Actions\RecordTransferKingdomCondition;
use App\Contexts\GameWorld\KingdomTransfers\Actions\RecordTransferObservation;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferInvitationStatus;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferKingdomClassification;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferObservationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class TransferPlanningController extends Controller
{
    public function storeWindow(Request $request, AllianceContext $context, SaveTransferWindow $save): RedirectResponse
    {
        $s = $context->scope();
        $save->handle($s->allianceId, $s->playerId, $this->windowData($request));

        return back()->with('actionReceipt', $this->receipt('transfer-window-created'));
    }

    public function updateWindow(Request $request, AllianceContext $context, SaveTransferWindow $save, string $window): RedirectResponse
    {
        $s = $context->scope();
        $save->handle($s->allianceId, $s->playerId, $this->windowData($request), $window);

        return back()->with('actionReceipt', $this->receipt('transfer-window-updated'));
    }

    public function storeGroup(Request $request, AllianceContext $context, SaveTransferGroup $save, string $window): RedirectResponse
    {/** @var array{official_label:string,kingdom_numbers:list<int>,source_type:string,source_reference:string,observed_at:string,evidence_id?:string|null} $v */ $v = $request->validate(['official_label' => ['required', 'string', 'max:160'], 'kingdom_numbers' => ['required', 'array', 'min:1', 'max:500'], 'kingdom_numbers.*' => ['integer', 'min:1'], 'source_type' => ['required', Rule::in(array_column(TransferSourceType::cases(), 'value'))], 'source_reference' => ['required', 'string', 'max:2048'], 'observed_at' => ['required', 'date'], 'evidence_id' => ['nullable', 'string', 'max:64']]);
        $s = $context->scope();
        $save->handle($s->allianceId, $s->playerId, $window, [...$v, 'source_type' => TransferSourceType::from($v['source_type'])]);

        return back()->with('actionReceipt', $this->receipt('transfer-official-group-recorded'));
    }

    public function storeCondition(Request $request, AllianceContext $context, RecordTransferKingdomCondition $record, string $window): RedirectResponse
    {/** @var array{kingdom_number:int,power_cap?:int|null,classification:string,source_type:string,source_reference:string,observed_at:string,is_correction?:bool,evidence_id?:string|null} $v */ $v = $request->validate(['kingdom_number' => ['required', 'integer', 'min:1'], 'power_cap' => ['nullable', 'integer', 'min:0'], 'classification' => ['required', Rule::in(array_column(TransferKingdomClassification::cases(), 'value'))], 'source_type' => ['required', Rule::in(array_column(TransferSourceType::cases(), 'value'))], 'source_reference' => ['required', 'string', 'max:2048'], 'observed_at' => ['required', 'date'], 'is_correction' => ['sometimes', 'boolean'], 'evidence_id' => ['nullable', 'string', 'max:64']]);
        $s = $context->scope();
        $record->handle($s->allianceId, $s->playerId, $window, $v['kingdom_number'], $v['power_cap'] ?? null, TransferKingdomClassification::from($v['classification']), TransferSourceType::from($v['source_type']), $v['source_reference'], $v['observed_at'], $v['is_correction'] ?? false, $v['evidence_id'] ?? null);

        return back()->with('actionReceipt', $this->receipt('transfer-kingdom-condition-recorded'));
    }

    public function storeObservation(Request $request, AllianceContext $context, RecordTransferObservation $record, string $plan, string $participant): RedirectResponse
    {/** @var array{kind:string,source_type:string,source_reference:string,observed_at:string,valid_until?:string|null,details?:string|null,evidence_id?:string|null} $base */ $base = $request->validate(['kind' => ['required', Rule::in(array_column(TransferObservationKind::cases(), 'value'))], 'source_type' => ['required', Rule::in(array_column(TransferSourceType::cases(), 'value'))], 'source_reference' => ['required', 'string', 'max:2048'], 'observed_at' => ['required', 'date'], 'valid_until' => ['nullable', 'date'], 'details' => ['nullable', 'string', 'max:5000'], 'evidence_id' => ['nullable', 'string', 'max:64']]);
        $kind = TransferObservationKind::from($base['kind']);
        if ($kind->usesNumericValue()) {/** @var array{value:int} $valueRow */ $valueRow = $request->validate(['value' => ['required', 'integer', 'min:0']]);
            $value = $valueRow['value'];
        } elseif ($kind === TransferObservationKind::InGameRulesVerified) {
            $request->validate(['value' => ['required', 'boolean']]);
            $value = $request->boolean('value');
        } else {/** @var array{value:string} $valueRow */ $valueRow = $request->validate(['value' => ['required', Rule::in(array_column(TransferInvitationStatus::cases(), 'value'))]]);
            $value = $valueRow['value'];
        }$s = $context->scope();
        $record->handle($s->allianceId, $s->playerId, $plan, $participant, $kind, $value, TransferSourceType::from($base['source_type']), $base['source_reference'], $base['observed_at'], $base['valid_until'] ?? null, $base['details'] ?? null, $base['evidence_id'] ?? null);

        return back()->with('actionReceipt', $this->receipt('transfer-observation-recorded'));
    }

    /** @return array{label:string,pre_transfer_starts_at:string,invitational_starts_at:string,transfer_opens_at:string,ends_at:string,source_type:TransferSourceType,source_reference:string,observed_at:string,evidence_id?:string|null} */
    private function windowData(Request $request): array
    {/** @var array{label:string,pre_transfer_starts_at:string,invitational_starts_at:string,transfer_opens_at:string,ends_at:string,source_type:string,source_reference:string,observed_at:string,evidence_id?:string|null} $v */ $v = $request->validate(['label' => ['required', 'string', 'max:160'], 'pre_transfer_starts_at' => ['required', 'date'], 'invitational_starts_at' => ['required', 'date'], 'transfer_opens_at' => ['required', 'date'], 'ends_at' => ['required', 'date'], 'source_type' => ['required', Rule::in(array_column(TransferSourceType::cases(), 'value'))], 'source_reference' => ['required', 'string', 'max:2048'], 'observed_at' => ['required', 'date'], 'evidence_id' => ['nullable', 'string', 'max:64']]);

        return [...$v, 'source_type' => TransferSourceType::from($v['source_type'])];
    }
}
