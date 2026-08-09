<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Enums\KingdomStatus;
use App\Domain\Kingdoms\Enums\TransferPlanState;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateTransferPlan
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param array{label: string, starts_on?: string|null, ends_on?: string|null} $attributes
     */
    public function handle(Alliance $alliance, User $actor, array $attributes): TransferPlan
    {
        if ($this->authorization->allows($actor, $alliance, PermissionKey::KingdomManage) === false) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $attributes): TransferPlan {
            $currentAlliance = Alliance::query()
                ->lockForUpdate()
                ->findOrFail($alliance->id);

            if ($currentAlliance->kingdom_id === null) {
                throw ValidationException::withMessages([
                    'plan' => 'Set the alliance Kingdom before creating a transfer cycle.',
                ]);
            }

            $homeKingdom = Kingdom::query()
                ->whereKey($currentAlliance->kingdom_id)
                ->where('status', KingdomStatus::Active->value)
                ->first();

            if (($homeKingdom instanceof Kingdom) === false) {
                throw ValidationException::withMessages([
                    'plan' => 'The alliance must reference an active Kingdom before creating a transfer cycle.',
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
                'alliance_id' => $currentAlliance->id,
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

            $this->audit->record('kingdoms.transfer_plan_created', $actor, $plan, $currentAlliance, $metadata);
            $this->outbox->record('kingdoms.transfer_plan_created', (string) $currentAlliance->id, $plan, $metadata);

            return $plan->refresh()->load('homeKingdom');
        });
    }

    private function date(?string $value): ?Carbon
    {
        $value = $value === null ? null : trim($value);

        return $value === null || $value === '' ? null : Carbon::parse($value)->startOfDay();
    }
}
