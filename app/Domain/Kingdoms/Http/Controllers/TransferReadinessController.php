<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Http\Controllers;

use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\CreateTransferBlocker;
use App\Domain\Kingdoms\Actions\ResolveTransferBlocker;
use App\Domain\Kingdoms\Actions\TransitionTransferReadiness;
use App\Domain\Kingdoms\Enums\TransferReadinessState;
use App\Domain\Platform\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class TransferReadinessController extends Controller
{
    public function transition(
        Request $request,
        AllianceContext $context,
        TransitionTransferReadiness $transition,
        string $plan,
        string $participant,
    ): RedirectResponse {
        /** @var array{readiness: string} $validated */
        $validated = $request->validate([
            'readiness' => ['required', Rule::in(array_column(TransferReadinessState::cases(), 'value'))],
        ]);

        $transition->handle(
            $context->alliance(),
            $this->user($request),
            $plan,
            $participant,
            TransferReadinessState::from($validated['readiness']),
        );

        return back()->with('status', 'transfer-readiness-updated');
    }

    public function storeBlocker(
        Request $request,
        AllianceContext $context,
        CreateTransferBlocker $create,
        string $plan,
        string $participant,
    ): RedirectResponse {
        /** @var array{summary: string, details?: string|null} $validated */
        $validated = $request->validate([
            'summary' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:5000'],
        ]);

        $create->handle(
            $context->alliance(),
            $this->user($request),
            $plan,
            $participant,
            $validated['summary'],
            $validated['details'] ?? null,
        );

        return back()->with('status', 'transfer-blocker-created');
    }

    public function resolveBlocker(
        Request $request,
        AllianceContext $context,
        ResolveTransferBlocker $resolve,
        string $plan,
        string $participant,
        string $blocker,
    ): RedirectResponse {
        $resolve->handle(
            $context->alliance(),
            $this->user($request),
            $plan,
            $participant,
            $blocker,
        );

        return back()->with('status', 'transfer-blocker-resolved');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
