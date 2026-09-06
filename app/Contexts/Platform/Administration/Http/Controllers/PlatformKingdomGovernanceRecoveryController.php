<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Administration\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Shared\Infrastructure\Http\Controller;
use App\Workflows\KingdomGovernance\Actions\RecoverKingdomAdministrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PlatformKingdomGovernanceRecoveryController extends Controller
{
    public function store(Request $request, AccountIdentityQuery $accounts, RecoverKingdomAdministrator $recover): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $validated = $request->validate([
            'kingdom_id' => ['required', 'string', 'size:26', Rule::exists('kingdoms', 'id')],
            'player_id' => ['required', 'string', 'size:26', Rule::exists('players', 'id')],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'replace_existing' => ['sometimes', 'boolean'],
        ]);
        $recover->handle($accounts->require((int) $user->id), (string) $validated['kingdom_id'], (string) $validated['player_id'], (string) $validated['reason'], (bool) ($validated['replace_existing'] ?? false));
        return back()->with('actionReceipt', $this->receipt('kingdom-administrator-recovered'));
    }
}
