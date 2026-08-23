<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Http\Controllers;

use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\KingdomTransfers\Actions\ArchiveTransferCohort;
use App\Contexts\GameWorld\KingdomTransfers\Actions\AssignTransferParticipantCohort;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferCohort;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class TransferCohortController extends Controller
{
    public function store(Request $request, AllianceContext $context, SaveTransferCohort $save, string $plan): RedirectResponse
    {
        $s = $context->scope();
        $save->handle($s->allianceId, $s->playerId, $plan, $this->validated($request));

        return back()->with('actionReceipt', $this->receipt('transfer-cohort-created'));
    }

    public function update(Request $request, AllianceContext $context, SaveTransferCohort $save, string $plan, string $cohort): RedirectResponse
    {
        $s = $context->scope();
        $save->handle($s->allianceId, $s->playerId, $plan, $this->validated($request), $cohort);

        return back()->with('actionReceipt', $this->receipt('transfer-cohort-updated'));
    }

    public function archive(Request $request, AllianceContext $context, ArchiveTransferCohort $archive, string $plan, string $cohort): RedirectResponse
    {
        $s = $context->scope();
        $archive->handle($s->allianceId, $s->playerId, $plan, $cohort);

        return back()->with('actionReceipt', $this->receipt('transfer-cohort-archived'));
    }

    public function assignParticipant(Request $request, AllianceContext $context, AssignTransferParticipantCohort $assign, string $plan, string $participant): RedirectResponse
    {/** @var array{transfer_cohort_id?:string|null} $v */ $v = $request->validate(['transfer_cohort_id' => ['nullable', 'string', 'ulid']]);
        $id = isset($v['transfer_cohort_id']) ? trim((string) $v['transfer_cohort_id']) : null;
        $s = $context->scope();
        $assign->handle($s->allianceId, $s->playerId, $plan, $participant, $id === '' ? null : $id);

        return back()->with('actionReceipt', $this->receipt('transfer-participant-cohort-updated'));
    }

    /** @return array{name:string,direction:TransferDirection,destination_kingdom?:int|null,coordinator_player_id?:string|null,manager_notes?:string|null} */
    private function validated(Request $request): array
    {/** @var array{name:string,direction:string,destination_kingdom?:int|null,coordinator_player_id?:string|null,manager_notes?:string|null} $v */ $v = $request->validate(['name' => ['required', 'string', 'max:160'], 'direction' => ['required', Rule::in([TransferDirection::Incoming->value, TransferDirection::Outgoing->value])], 'destination_kingdom' => ['nullable', 'integer', 'min:1'], 'coordinator_player_id' => ['nullable', 'string', 'ulid'], 'manager_notes' => ['nullable', 'string', 'max:5000']]);
        $v['direction'] = TransferDirection::from($v['direction']);

        return $v;
    }
}
