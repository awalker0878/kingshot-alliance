<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\GameWorld\Kingdoms\Enums\KingdomStatus;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateTransferPlan
{
    public function __construct(private TransferWriteState $writeState, private TransferAuthorization $authority, private AuditRecorder $audit, private OutboxRecorder $outbox) {}

    /** @param array{label:string,transfer_window_id:string} $attributes */
    public function handle(string $allianceId, string $actorPlayerId, array $attributes): void
    {
        DB::transaction(function () use ($allianceId, $actorPlayerId, $attributes): void {
            $context = $this->writeState->lockAuthority($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, TransferPermission::Manage);
            $home = Kingdom::query()->whereKey($context->kingdomId())->where('status', KingdomStatus::Active->value)->sharedLock()->first();
            if (! $home instanceof Kingdom) {
                throw ValidationException::withMessages(['plan' => 'The Alliance must reference an active Kingdom before creating a transfer plan.']);
            }$label = trim($attributes['label']);
            if ($label === '') {
                throw ValidationException::withMessages(['label' => 'A transfer plan label is required.']);
            }$window = TransferWindow::query()->where('alliance_id', $allianceId)->whereKey($attributes['transfer_window_id'])->sharedLock()->first();
            if (! $window instanceof TransferWindow) {
                throw ValidationException::withMessages(['transfer_window_id' => 'Select a Transfer Window owned by this Alliance.']);
            }if (TransferPlan::query()->where('alliance_id', $allianceId)->where('transfer_window_id', $window->id)->exists()) {
                throw ValidationException::withMessages(['transfer_window_id' => 'This Alliance already has a plan for that Transfer Window.']);
            }$plan = TransferPlan::query()->create(['alliance_id' => $allianceId, 'home_kingdom_id' => $home->id, 'transfer_window_id' => $window->id, 'label' => $label, 'state' => TransferPlanState::Draft]);
            $metadata = ['alliance_id' => $allianceId, 'transfer_plan_id' => (string) $plan->id, 'transfer_window_id' => (string) $window->id, 'home_kingdom_id' => (string) $plan->home_kingdom_id, 'state' => TransferPlanState::Draft->value];
            $this->audit->record('kingdoms.transfer_plan_created', $context->actor, $plan, null, $metadata);
            $this->outbox->record('kingdoms.transfer_plan_created', $allianceId, $plan, $metadata);
        });
    }
}
