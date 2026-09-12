<?php

declare(strict_types=1);

namespace App\ReadModels\PlatformAdministration\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Platform\Administration\Services\PlatformAdministratorAuthorization;
use App\ReadModels\PlatformAdministration\KingdomRecoveryChoiceQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class PlatformKingdomGovernanceRecoveryReadController
{
    public function __construct(private AccountIdentityQuery $accounts, private PlatformAdministratorAuthorization $authorization) {}

    public function __invoke(Request $request): Response
    {
        $identifier = $request->user()?->getAuthIdentifier();
        abort_unless(is_numeric($identifier), 401);
        $account = $this->accounts->require((int) $identifier);
        $this->authorization->authorize($account);

        return Inertia::render('Platform/GovernanceRecovery', [
            'user' => ['name' => $account->name, 'email' => $account->email], 'actorId' => $account->userId,
        ]);
    }

    public function choices(Request $request, string $kind, KingdomRecoveryChoiceQuery $query): JsonResponse
    {
        $identifier = $request->user()?->getAuthIdentifier();
        abort_unless(is_numeric($identifier), 401);
        $input = $request->validate(['kingdom' => ['nullable', 'ulid'], 'q' => ['nullable', 'string', 'max:160'],
            'cursor' => ['nullable', 'string', 'max:4096'], 'selected' => ['nullable', 'ulid']]);

        return response()->json($query->page((int) $identifier, $kind, $input['kingdom'] ?? null,
            $input['q'] ?? '', $input['cursor'] ?? null, $input['selected'] ?? null));
    }
}
