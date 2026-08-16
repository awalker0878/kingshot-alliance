<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Actions;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\AllianceLifecycleMutation;
use App\Contexts\Alliance\Core\Enums\AllianceStatus;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Platform\Access\Services\PlatformAuthorization;
use App\Contexts\Platform\Models\AlliancePlatformSetting;
use App\Contexts\Platform\Services\LegalHoldService;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ManageAllianceLifecycle
{
    public function __construct(
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
        private LegalHoldService $legalHolds,
        private PlatformAuthorization $mutations,
        private AllianceLifecycleMutation $allianceLifecycle,
    ) {}

    public function suspend(User $actor, Alliance $alliance, string $reason): Alliance
    {
        return $this->transition($actor, $alliance, AllianceStatus::Suspended, $reason, 'platform.alliance.suspended');
    }

    public function close(User $actor, Alliance $alliance, string $reason): Alliance
    {
        return $this->transition($actor, $alliance, AllianceStatus::Closed, $reason, 'platform.alliance.closed');
    }

    public function delete(User $actor, Alliance $alliance, string $reason): Alliance
    {
        return $this->transition($actor, $alliance, AllianceStatus::Deleted, $reason, 'platform.alliance.deleted');
    }

    public function restore(User $actor, Alliance $alliance, string $reason): Alliance
    {
        return $this->transition($actor, $alliance, AllianceStatus::Active, $reason, 'platform.alliance.restored');
    }

    private function transition(
        User $actor,
        Alliance $alliance,
        AllianceStatus $target,
        string $reason,
        string $event,
    ): Alliance {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A lifecycle reason is required.');
        }

        return DB::transaction(function () use ($actor, $alliance, $target, $reason, $event): Alliance {
            $context = $this->mutations->require($actor);

            // Alliance Core owns the exclusive lifecycle lock and state transition.
            // Holding that lock while Platform checks its legal-hold and retention
            // policy preserves the existing cross-context serialization contract.
            $locked = $this->allianceLifecycle->acquire($alliance);

            if ($target === AllianceStatus::Deleted
                && $this->legalHolds->active('alliance', (string) $locked->id)) {
                throw new InvalidArgumentException('This alliance is protected by an active legal hold.');
            }

            $retentionUntil = $target === AllianceStatus::Closed
                ? $this->retentionUntil($locked)
                : null;
            $previous = $locked->status;

            $updated = $this->allianceLifecycle->transitionLocked(
                alliance: $locked,
                target: $target,
                reason: $reason,
                retentionUntil: $retentionUntil,
            );

            $this->audit->record($event, $context->actor, $updated, $updated, [
                'from' => $previous->value,
                'to' => $target->value,
                'reason' => $reason,
                'retention_until' => $updated->retention_until?->toIso8601String(),
            ]);
            $this->outbox->record($event, (string) $updated->id, $updated, [
                'alliance_id' => $updated->id,
                'from' => $previous->value,
                'to' => $target->value,
            ]);

            return $updated;
        });
    }

    private function retentionUntil(Alliance $alliance): CarbonInterface
    {
        $settings = AlliancePlatformSetting::query()
            ->whereKey($alliance->id)
            ->lockForUpdate()
            ->first();
        $retentionDays = $settings instanceof AlliancePlatformSetting
            ? (int) $settings->retention_days
            : 30;

        return now()->addDays(max(1, min(3650, $retentionDays)));
    }
}
