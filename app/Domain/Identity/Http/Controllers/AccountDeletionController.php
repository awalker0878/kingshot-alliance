<?php

declare(strict_types=1);

namespace App\Domain\Identity\Http\Controllers;

use App\Domain\Identity\Actions\RequestAccountDeletion;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Http\Controllers\Controller;
use App\Domain\Platform\Models\AccountDeletionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AccountDeletionController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $deletion = AccountDeletionRequest::query()->where('user_id', $user->id)->first();

        return Inertia::render('AccountDeletion', [
            'request' => $deletion instanceof AccountDeletionRequest ? [
                'status' => (string) $deletion->status,
                'requestedAt' => $deletion->requested_at->toIso8601String(),
                'eligibleAt' => $deletion->eligible_at->toIso8601String(),
                'processedAt' => $deletion->processed_at?->toIso8601String(),
                'blockedReason' => $deletion->blocked_reason,
            ] : null,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request, RequestAccountDeletion $deletion): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $deletion->handle($user);

        return redirect()->route('profile.deletion.show')->with('status', 'account-deletion-requested');
    }
}
