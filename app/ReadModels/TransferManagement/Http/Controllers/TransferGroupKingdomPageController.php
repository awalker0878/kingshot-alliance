<?php

declare(strict_types=1);

namespace App\ReadModels\TransferManagement\Http\Controllers;

use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\ReadModels\TransferManagement\Queries\TransferGroupKingdomPageQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TransferGroupKingdomPageController extends Controller
{
    public function __invoke(Request $request, AllianceContext $context, TransferGroupKingdomPageQuery $pages, string $plan, string $group): JsonResponse
    {
        $scope = $context->scope();
        $input = $request->validate(['cursor' => ['nullable', 'string', 'max:4096']]);

        return response()->json($pages->page($scope->playerId, $scope->allianceId, $plan, $group, $input['cursor'] ?? null))
            ->header('Cache-Control', 'private, no-store');
    }
}
