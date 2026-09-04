<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Http\Controllers;

use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Alliance\Membership\Actions\BulkUpdateAllianceRank;
use App\Contexts\Alliance\Membership\Actions\PreviewBulkAllianceRankChange;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class BulkAllianceRankController extends Controller
{
    public function preview(
        Request $request,
        AllianceContext $context,
        PreviewBulkAllianceRankChange $preview,
    ): RedirectResponse {
        $validated = $this->validated($request);
        $scope = $context->scope();
        $result = $preview->handle(
            $scope->allianceId,
            $scope->playerId,
            array_values($validated['membership_ids']),
            AllianceRank::from((string) $validated['rank']),
        );

        return back()->with('bulkRankPreview', $result);
    }

    public function commit(
        Request $request,
        AllianceContext $context,
        BulkUpdateAllianceRank $bulk,
    ): RedirectResponse {
        $validated = $this->validated($request);
        $scope = $context->scope();
        $result = $bulk->handle(
            $scope->allianceId,
            $scope->playerId,
            array_values($validated['membership_ids']),
            AllianceRank::from((string) $validated['rank']),
        );

        return back()
            ->with('bulkRankResult', $result)
            ->with('actionReceipt', $this->receipt('alliance-bulk-rank-completed', [
                'succeeded' => $result['succeeded'],
                'failed' => $result['failed'],
                'skipped' => $result['skipped'],
            ]));
    }

    /** @return array{membership_ids:list<string>,rank:string} */
    private function validated(Request $request): array
    {
        return $request->validate([
            'membership_ids' => ['required', 'array', 'min:1', 'max:50'],
            'membership_ids.*' => ['required', 'string', 'ulid', 'distinct'],
            'rank' => ['required', Rule::enum(AllianceRank::class), Rule::notIn([AllianceRank::R5->value])],
        ]);
    }
}
