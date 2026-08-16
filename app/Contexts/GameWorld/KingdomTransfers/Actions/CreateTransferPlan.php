<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Enums\KingdomStatus;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferMutationAuthority;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateTransferPlan
{
    public function __construct(
        private TransferMutationAuthority $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param  array{label: string, starts_on?: string|null, ends_on?: string|null}  $attributes
     */
    public function handle(Alliance $alliance, Player $actor, array $attributes): TransferPlan
    {
        return DB::transaction(function () use ($alliance, $actor, $attributes): TransferPlan {
            // Creating a Draft transfer plan is not an Alliance-wide singleton. The
            // ordinary mutation boundary protects lifecycle/authority without turning
            // unrelated Draft creation into an Alliance mutex.
            $context = $this->authority->require($actor, $alliance, TransferPermission::Manage);

            $homeKingdom = Kingdom::query()
                ->whereKey($context->alliance->kingdom_id)
                ->where('status', KingdomStatus::Active->value)
                ->sharedLock()
                ->first();

            if (! $homeKingdom instanceof Kingdom) {
                throw ValidationException::withMessages([
                    'plan' => 'The Alliance must reference an active Kingdom before creating a transfer cycle.',
                ]);
            }

            $label = trim($attributes['label']);
            if ($label === '') {
                throw ValidationException::withMessages(['label' => 'A transfer cycle label is required.']);
            }

            $startsOn = $this->date($attributes['starts_on'] ?? null);
            $endsOn = $this->date($attributes['ends_on'] ?? null);
            if ($startsOn !== null && $endsOn !== null && $endsOn->lt($startsOn)) {
                throw ValidationException::withMessages([
                    'ends_on' => 'The end date must be on or after the start date.',
                ]);
            }

            $plan = TransferPlan::query()->create([
                'alliance_id' => $context->alliance->id,
                'home_kingdom_id' => $homeKingdom->id,
                'label' => $label,
                'starts_on' => $startsOn?->toDateString(),
                'ends_on' => $endsOn?->toDateString(),
                'state' => TransferPlanState::Draft,
            ]);

            $metadata = [
                'transfer_plan_id' => (string) $plan->id,
                'home_kingdom_id' => (string) $plan->home_kingdom_id,
                'state' => TransferPlanState::Draft->value,
                'starts_on' => $plan->starts_on?->toDateString(),
                'ends_on' => $plan->ends_on?->toDateString(),
            ];

            $this->audit->record('kingdoms.transfer_plan_created', $context->actor, $plan, $context->alliance, $metadata);
            $this->outbox->record('kingdoms.transfer_plan_created', (string) $context->alliance->id, $plan, $metadata);

            return $plan->refresh()->load('homeKingdom');
        });
    }

    private function date(?string $value): ?Carbon
    {
        $value = $value === null ? null : trim($value);

        return $value === null || $value === '' ? null : Carbon::parse($value)->startOfDay();
    }
}
