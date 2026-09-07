<?php

declare(strict_types=1);

namespace App\Workflows\KingdomGovernance\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Shared\Infrastructure\Http\Controller;
use App\Workflows\KingdomGovernance\Actions\ReconcileKingdomGovernancePolicy;
use App\Workflows\KingdomGovernance\Actions\RecoverKingdomAdministrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class KingdomGovernanceWorkflowController extends Controller
{
    public function reconcile(AllianceContext $context, ReconcileKingdomGovernancePolicy $reconcile): RedirectResponse
    {
        $scope = $context->scope();
        $reconcile->handle($scope->playerId, $scope->kingdomId);

        return back()->with('actionReceipt', $this->receipt('kingdom-governance-reconciled'));
    }

    public function recover(Request $request, AccountIdentityQuery $accounts, RecoverKingdomAdministrator $recover): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);
        $validated = $request->validate([
            'kingdom_id' => ['required', 'string', 'size:26', Rule::exists('kingdoms', 'id')],
            'player_id' => ['required', 'string', 'size:26', Rule::exists('players', 'id')],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'replace_existing' => ['sometimes', 'boolean'],
        ]);
        $operator = $accounts->require((int) $user->getAuthIdentifier());
        $recover->handle($operator, (string) $validated['kingdom_id'], (string) $validated['player_id'], (string) $validated['reason'], (bool) ($validated['replace_existing'] ?? false));

        return back()->with('actionReceipt', $this->receipt('kingdom-administrator-recovered'));
    }
}
