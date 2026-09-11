<?php

declare(strict_types=1);

namespace App\ReadModels\TransferManagement\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\ReadModels\TransferManagement\Queries\TransferManagementPageQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TransferManagementPageController extends Controller
{
    public function index(Request $request, AllianceContext $context, TransferManagementPageQuery $pages, AccountIdentityQuery $accounts): Response
    {
        $scope = $context->scope();
        $input = $request->validate(['participant_cursor' => ['nullable', 'string', 'max:4096']]);
        $payload = $pages->overview($scope->playerId, $scope->allianceId, $input['participant_cursor'] ?? null);

        return Inertia::render('Kingdom/Transfer/Index', ['user' => $this->user($request, $accounts), ...$payload]);
    }

    public function manage(Request $request, AllianceContext $context, TransferManagementPageQuery $pages, AccountIdentityQuery $accounts): Response
    {
        $scope = $context->scope();
        $input = $request->validate(['participant_cursor' => ['nullable', 'string', 'max:4096']]);
        $payload = $pages->management($scope->playerId, $scope->allianceId, $input['participant_cursor'] ?? null);

        return Inertia::render('Kingdom/Transfer/Manage', ['user' => $this->user($request, $accounts), ...$payload]);
    }

    /** @return array{name:string,email:string} */
    private function user(Request $request, AccountIdentityQuery $accounts): array
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_numeric($id), 401);
        $account = $accounts->require((int) $id);

        return ['name' => $account->name, 'email' => $account->email];
    }
}
