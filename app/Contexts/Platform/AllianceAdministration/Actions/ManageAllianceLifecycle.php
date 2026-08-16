<?php

declare(strict_types=1);

namespace App\Contexts\Platform\AllianceAdministration\Actions;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\Actions\AllianceLifecycleMutation;
use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Platform\Administration\Services\PlatformAuthorization;
use App\Contexts\Platform\Administration\Services\PlatformWriteState;
use App\Contexts\Platform\AllianceAdministration\Models\AlliancePlatformSetting;
use App\Contexts\Platform\DataGovernance\Services\LegalHoldService;
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
        private PlatformWriteState $platformWriteState,
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

    private function transition(User $actor, Alliance $alliance, AllianceStatus $target, string $reason, string $event): Alliance
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A lifecycle reason is required.');
        }

        return DB::transaction(function () use ($actor, $alliance, $target, $reason, $event): Alliance {
            $context = $this->mutations->authorizeContext($this->platformWriteState->lock($actor));
            $locked = $this->allianceLifecycle->acquire($alliance);
            if ($target === AllianceStatus::Deleted && $this->legalHolds->active('alliance', (string) $locked->id)) {
                throw new InvalidArgumentException('This alliance is protected by an active legal hold.');
            }
            $retentionUntil = $target === AllianceStatus::Closed ? $this->retentionUntil($locked) : null;
            $previous = $locked->status;
            $updated = $this->allianceLifecycle->transitionLocked($locked, $target, $reason, $retentionUntil);
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
        $settings = AlliancePlatformSetting::query()->whereKey($alliance->id)->lockForUpdate()->first();
        $retentionDays = $settings instanceof AlliancePlatformSetting ? (int) $settings->retention_days : 30;

        return now()->addDays(max(1, min(3650, $retentionDays)));
    }
}
