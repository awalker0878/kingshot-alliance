<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Http\Controllers;

use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CancelTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CloseTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\LockTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\OpenTransferPlan;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class TransferPlanController extends Controller
{
    public function store(Request $request, AllianceContext $context, CreateTransferPlan $create): RedirectResponse
    {
        /** @var array{label:string,transfer_window_id:string} $v */
        $v = $request->validate([
            'label' => ['required', 'string', 'max:160'],
            'transfer_window_id' => ['required', 'string', 'ulid'],
        ]);
        $s = $context->scope();
        $create->handle($s->allianceId, $s->playerId, $v);

        return back()->with('actionReceipt', $this->receipt('transfer-plan-created'));
    }

    public function open(Request $request, AllianceContext $context, OpenTransferPlan $action, string $plan): RedirectResponse
    {
        $s = $context->scope();
        $action->handle($s->allianceId, $s->playerId, $plan);

        return back()->with('actionReceipt', $this->receipt('transfer-plan-opened'));
    }

    public function lock(Request $request, AllianceContext $context, LockTransferPlan $action, string $plan): RedirectResponse
    {
        $s = $context->scope();
        $action->handle($s->allianceId, $s->playerId, $plan);

        return back()->with('actionReceipt', $this->receipt('transfer-plan-locked'));
    }

    public function close(Request $request, AllianceContext $context, CloseTransferPlan $action, string $plan): RedirectResponse
    {
        $s = $context->scope();
        $action->handle($s->allianceId, $s->playerId, $plan);

        return back()->with('actionReceipt', $this->receipt('transfer-plan-closed'));
    }

    public function cancel(Request $request, AllianceContext $context, CancelTransferPlan $action, string $plan): RedirectResponse
    {
        $s = $context->scope();
        $action->handle($s->allianceId, $s->playerId, $plan);

        return back()->with('actionReceipt', $this->receipt('transfer-plan-cancelled'));
    }
}
