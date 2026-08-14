<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Http\Controllers;

use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Kingdoms\Actions\SaveTransferParticipant;
use App\Domain\Kingdoms\Actions\WithdrawTransferParticipant;
use App\Domain\Kingdoms\Enums\TransferDirection;
use App\Domain\Platform\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class TransferParticipantController extends Controller
{
    public function store(
        Request $request,
        AllianceContext $context,
        SaveTransferParticipant $save,
        string $plan,
    ): RedirectResponse {
        $save->handle(
            $context->alliance(),
            $context->player(),
            $plan,
            $this->validated($request),
        );

        return back()->with('status', 'transfer-participant-created');
    }

    public function update(
        Request $request,
        AllianceContext $context,
        SaveTransferParticipant $save,
        string $plan,
        string $participant,
    ): RedirectResponse {
        $save->handle(
            $context->alliance(),
            $context->player(),
            $plan,
            $this->validated($request),
            $participant,
        );

        return back()->with('status', 'transfer-participant-updated');
    }

    public function withdraw(
        Request $request,
        AllianceContext $context,
        WithdrawTransferParticipant $withdraw,
        string $plan,
        string $participant,
    ): RedirectResponse {
        $withdraw->handle(
            $context->alliance(),
            $context->player(),
            $plan,
            $participant,
        );

        return back()->with('status', 'transfer-participant-withdrawn');
    }

    /**
     * @return array{
     *   direction: TransferDirection,
     *   roster_entry_id?: string|null,
     *   name?: string|null,
     *   game_player_id?: string|null,
     *   source_kingdom?: int|null,
     *   destination_kingdom?: int|null,
     *   manager_notes?: string|null
     * }
     */
    private function validated(Request $request): array
    {
        /** @var array{
         *   direction: string,
         *   roster_entry_id?: string|null,
         *   name?: string|null,
         *   game_player_id?: string|null,
             *   source_kingdom?: int|null,
         *   destination_kingdom?: int|null,
         *   manager_notes?: string|null
         * } $validated
         */
        $validated = $request->validate([
            'direction' => ['required', Rule::in(array_column(TransferDirection::cases(), 'value'))],
            'roster_entry_id' => ['nullable', 'string', 'ulid'],
            'name' => ['nullable', 'string', 'max:160'],
            'game_player_id' => ['nullable', 'string', 'max:100'],
            'source_kingdom' => ['nullable', 'integer', 'min:1'],
            'destination_kingdom' => ['nullable', 'integer', 'min:1'],
            'manager_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $validated['direction'] = TransferDirection::from($validated['direction']);

        return $validated;
    }
}
