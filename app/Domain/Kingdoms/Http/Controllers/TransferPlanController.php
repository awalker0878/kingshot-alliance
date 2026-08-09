<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Http\Controllers;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Actions\CancelTransferPlan;
use App\Domain\Kingdoms\Actions\CloseTransferPlan;
use App\Domain\Kingdoms\Actions\CreateTransferPlan;
use App\Domain\Kingdoms\Actions\LockTransferPlan;
use App\Domain\Kingdoms\Actions\OpenTransferPlan;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Domain\Kingdoms\Queries\TransferPlanQuery;
use App\Domain\Platform\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TransferPlanController extends Controller
{
    public function index(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        TransferPlanQuery $plans,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance()->load('kingdom');

        if (! $authorization->allows($user, $alliance, PermissionKey::AllianceView)) {
            throw new AuthorizationException;
        }

        $current = $plans->currentForAlliance($alliance);

        return Inertia::render('Alliance/TransferPlans', [
            'alliance' => $this->alliance($alliance),
            'canManage' => $authorization->allows($user, $alliance, PermissionKey::KingdomManage),
            'plan' => $current === null ? null : $this->plan($current),
        ]);
    }

    public function manage(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        TransferPlanQuery $plans,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance()->load('kingdom');

        if (! $authorization->allows($user, $alliance, PermissionKey::KingdomManage)) {
            throw new AuthorizationException;
        }

        return Inertia::render('Alliance/TransferPlansManage', [
            'alliance' => $this->alliance($alliance),
            'plans' => $plans->forAlliance($alliance)->map(fn (TransferPlan $plan): array => $this->plan($plan))->all(),
        ]);
    }

    public function store(
        Request $request,
        AllianceContext $context,
        CreateTransferPlan $create,
    ): RedirectResponse {
        /** @var array{label: string, starts_on?: string|null, ends_on?: string|null} $validated */
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:160'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date'],
        ]);

        $create->handle($context->alliance(), $this->user($request), $validated);

        return back()->with('status', 'transfer-plan-created');
    }

    public function open(
        Request $request,
        AllianceContext $context,
        OpenTransferPlan $open,
        string $plan,
    ): RedirectResponse {
        $open->handle($context->alliance(), $this->user($request), $plan);

        return back()->with('status', 'transfer-plan-opened');
    }

    public function lock(
        Request $request,
        AllianceContext $context,
        LockTransferPlan $lock,
        string $plan,
    ): RedirectResponse {
        $lock->handle($context->alliance(), $this->user($request), $plan);

        return back()->with('status', 'transfer-plan-locked');
    }

    public function close(
        Request $request,
        AllianceContext $context,
        CloseTransferPlan $close,
        string $plan,
    ): RedirectResponse {
        $close->handle($context->alliance(), $this->user($request), $plan);

        return back()->with('status', 'transfer-plan-closed');
    }

    public function cancel(
        Request $request,
        AllianceContext $context,
        CancelTransferPlan $cancel,
        string $plan,
    ): RedirectResponse {
        $cancel->handle($context->alliance(), $this->user($request), $plan);

        return back()->with('status', 'transfer-plan-cancelled');
    }

    /** @return array{id: string, name: string, kingdom: string|null} */
    private function alliance(Alliance $alliance): array
    {
        return [
            'id' => (string) $alliance->id,
            'name' => (string) $alliance->name,
            'kingdom' => $alliance->kingdom === null ? null : (string) $alliance->kingdom->number,
        ];
    }

    /** @return array<string, string|null> */
    private function plan(TransferPlan $plan): array
    {
        return [
            'id' => (string) $plan->id,
            'label' => (string) $plan->label,
            'homeKingdom' => (string) $plan->homeKingdom->number,
            'startsOn' => $plan->starts_on?->toDateString(),
            'endsOn' => $plan->ends_on?->toDateString(),
            'state' => $plan->state->value,
            'createdAt' => $plan->created_at?->toIso8601String(),
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
