<?php

declare(strict_types=1);

namespace App\ReadModels\TransferManagement\Http\Controllers;

use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\ReadModels\TransferManagement\Enums\TransferChoiceKind;
use App\ReadModels\TransferManagement\Queries\TransferManagementChoiceQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TransferManagementChoiceController extends Controller
{
    public function __invoke(Request $request, TransferChoiceKind $kind, AllianceContext $context, TransferManagementChoiceQuery $choices): JsonResponse
    {
        $scope = $context->scope();
        $input = $request->validate(['plan' => ['nullable', 'ulid'], 'q' => ['nullable', 'string', 'max:160'],
            'cursor' => ['nullable', 'string', 'max:4096'], 'selected' => ['nullable', 'ulid'], 'participant' => ['nullable', 'ulid']]);

        return response()->json($choices->page($scope->playerId, $scope->allianceId, $kind,
            $input['plan'] ?? null, $input['q'] ?? '', $input['cursor'] ?? null, $input['selected'] ?? null, $input['participant'] ?? null))
            ->header('Cache-Control', 'private, no-store');
    }
}
