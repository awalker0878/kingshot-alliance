<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Profile\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Platform\DataGovernance\Actions\CancelAccountDeletion;
use App\Contexts\Platform\DataGovernance\Actions\RequestAccountDeletion;
use App\Contexts\Platform\DataGovernance\Queries\AccountDeletionRequestQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AccountDeletionController extends Controller
{
    public function show(Request $request, AccountDeletionRequestQuery $requests): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return Inertia::render('Accounts/Governor/Delete', [
            'user' => ['name' => $user->name, 'email' => $user->email],
            'request' => $requests->forUser((int) $user->id),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request, RequestAccountDeletion $requestDeletion): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_if($user->anonymized_at !== null, 403);

        $requestDeletion->handle((int) $user->id);

        return redirect()->route('profile.delete-account.show')->with('status', 'account-deletion-requested');
    }

    public function destroy(Request $request, CancelAccountDeletion $cancelDeletion): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $cancelDeletion->handle((int) $user->id);

        return redirect()->route('profile.delete-account.show')->with('status', 'account-deletion-cancelled');
    }
}
