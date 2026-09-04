<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Http\Controllers;

use App\Contexts\Alliance\Access\Actions\BulkChangeMembershipRole;
use App\Contexts\Alliance\Access\Actions\PreviewBulkMembershipRoleChange;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class BulkMembershipRoleController extends Controller
{
    public function preview(
        Request $request,
        AllianceContext $context,
        PreviewBulkMembershipRoleChange $preview,
    ): RedirectResponse {
        $validated = $this->validated($request);
        $scope = $context->scope();
        $result = $preview->handle(
            $scope->allianceId,
            $scope->playerId,
            array_values($validated['membership_ids']),
            (string) $validated['role_id'],
            (string) $validated['operation'],
        );

        return back()->with('bulkRolePreview', $result);
    }

    public function commit(
        Request $request,
        AllianceContext $context,
        BulkChangeMembershipRole $bulk,
    ): RedirectResponse {
        $validated = $this->validated($request);
        $scope = $context->scope();
        $result = $bulk->handle(
            $scope->allianceId,
            $scope->playerId,
            array_values($validated['membership_ids']),
            (string) $validated['role_id'],
            (string) $validated['operation'],
        );

        return back()
            ->with('bulkRoleResult', $result)
            ->with('actionReceipt', $this->receipt('alliance-bulk-role-completed', [
                'succeeded' => $result['succeeded'],
                'failed' => $result['failed'],
                'skipped' => $result['skipped'],
            ]));
    }

    /** @return array{membership_ids:list<string>,role_id:string,operation:string} */
    private function validated(Request $request): array
    {
        return $request->validate([
            'membership_ids' => ['required', 'array', 'min:1', 'max:50'],
            'membership_ids.*' => ['required', 'string', 'ulid', 'distinct'],
            'role_id' => ['required', 'string', 'ulid'],
            'operation' => ['required', 'string', Rule::in(['assign', 'remove'])],
        ]);
    }
}
