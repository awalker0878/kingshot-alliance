<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Http\Controllers;

use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\ArchiveTransferGroup;
use App\Domain\Kingdoms\Actions\AssignTransferParticipantGroup;
use App\Domain\Kingdoms\Actions\SaveTransferGroup;
use App\Domain\Kingdoms\Enums\TransferDirection;
use App\Domain\Platform\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class TransferGroupController extends Controller
{
    public function store(
        Request $request,
        AllianceContext $context,
        SaveTransferGroup $save,
        string $plan,
    ): RedirectResponse {
        $save->handle(
            $context->alliance(),
            $this->user($request),
            $plan,
            $this->validatedGroup($request),
        );

        return back()->with('status', 'transfer-group-created');
    }

    public function update(
        Request $request,
        AllianceContext $context,
        SaveTransferGroup $save,
        string $plan,
        string $group,
    ): RedirectResponse {
        $save->handle(
            $context->alliance(),
            $this->user($request),
            $plan,
            $this->validatedGroup($request),
            $group,
        );

        return back()->with('status', 'transfer-group-updated');
    }

    public function archive(
        Request $request,
        AllianceContext $context,
        ArchiveTransferGroup $archive,
        string $plan,
        string $group,
    ): RedirectResponse {
        $archive->handle(
            $context->alliance(),
            $this->user($request),
            $plan,
            $group,
        );

        return back()->with('status', 'transfer-group-archived');
    }

    public function assignParticipant(
        Request $request,
        AllianceContext $context,
        AssignTransferParticipantGroup $assign,
        string $plan,
        string $participant,
    ): RedirectResponse {
        /** @var array{transfer_group_id?: string|null} $validated */
        $validated = $request->validate([
            'transfer_group_id' => ['nullable', 'string', 'ulid'],
        ]);

        $groupId = isset($validated['transfer_group_id'])
            ? trim((string) $validated['transfer_group_id'])
            : null;

        $assign->handle(
            $context->alliance(),
            $this->user($request),
            $plan,
            $participant,
            $groupId === '' ? null : $groupId,
        );

        return back()->with('status', 'transfer-participant-group-updated');
    }

    /**
     * @return array{
     *   name: string,
     *   direction: TransferDirection,
     *   destination_kingdom?: int|null,
     *   coordinator_membership_id?: string|null,
     *   manager_notes?: string|null
     * }
     */
    private function validatedGroup(Request $request): array
    {
        /** @var array{
         *   name: string,
         *   direction: string,
         *   destination_kingdom?: int|null,
         *   coordinator_membership_id?: string|null,
         *   manager_notes?: string|null
         * } $validated
         */
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'direction' => ['required', Rule::in([
                TransferDirection::Incoming->value,
                TransferDirection::Outgoing->value,
            ])],
            'destination_kingdom' => ['nullable', 'integer', 'min:1'],
            'coordinator_membership_id' => ['nullable', 'string', 'ulid'],
            'manager_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $validated['direction'] = TransferDirection::from($validated['direction']);

        return $validated;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
