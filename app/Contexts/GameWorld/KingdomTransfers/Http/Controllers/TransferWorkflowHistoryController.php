<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Http\Controllers;

use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferBlockerState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferBlocker;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferReadinessTransition;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferWorkflowHistoryQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class TransferWorkflowHistoryController extends Controller
{
    public function blockers(Request $request, AllianceContext $context, TransferWorkflowHistoryQuery $history, string $plan, string $participant): JsonResponse
    {
        /** @var array{cursor?:string|null,state?:string} $filters */
        $filters = $request->validate(['cursor' => ['nullable', 'string', 'max:4096'], 'state' => ['sometimes', Rule::enum(TransferBlockerState::class)]]);
        $scope = $context->scope();
        $page = $history->blockers($scope->playerId, $scope->allianceId, $plan, $participant,
            TransferBlockerState::from($filters['state'] ?? TransferBlockerState::Active->value), $filters['cursor'] ?? null);

        return response()->json([...$page->toArray(), 'items' => array_map(static fn (TransferBlocker $blocker): array => [
            'id' => (string) $blocker->id, 'state' => $blocker->state->value, 'summary' => $blocker->summary,
            'details' => $blocker->details, 'createdAt' => $blocker->created_at?->toIso8601String(),
            'resolvedAt' => $blocker->resolved_at?->toIso8601String(),
            'createdBy' => $blocker->createdBy === null ? null : ['name' => $blocker->createdBy->current_name],
            'resolvedBy' => $blocker->resolvedBy === null ? null : ['name' => $blocker->resolvedBy->current_name],
        ], $page->items)]);
    }

    public function transitions(Request $request, AllianceContext $context, TransferWorkflowHistoryQuery $history, string $plan, string $participant): JsonResponse
    {
        /** @var array{cursor?:string|null} $filters */
        $filters = $request->validate(['cursor' => ['nullable', 'string', 'max:4096']]);
        $scope = $context->scope();
        $page = $history->transitions($scope->playerId, $scope->allianceId, $plan, $participant, $filters['cursor'] ?? null);

        return response()->json([...$page->toArray(), 'items' => array_map(static fn (TransferReadinessTransition $transition): array => [
            'id' => (string) $transition->id, 'from' => $transition->from_state?->value, 'to' => $transition->to_state->value,
            'changedAt' => $transition->created_at->toIso8601String(),
            'actor' => $transition->actor === null ? null : ['name' => $transition->actor->current_name],
        ], $page->items)]);
    }
}
