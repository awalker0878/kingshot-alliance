<?php

declare(strict_types=1);

namespace App\Domain\Platform\Actions;

use App\Domain\Alliances\Enums\AllianceStatus;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AlliancePlatformSetting;
use App\Domain\Platform\Services\LegalHoldService;
use App\Domain\Platform\Services\OutboxRecorder;
use App\Domain\Platform\Services\PlatformAdministratorAuthorization;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ManageAllianceLifecycle
{
    public function __construct(
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
        private LegalHoldService $legalHolds,
        private PlatformAdministratorAuthorization $authorization,
    ) {}

    public function suspend(User $actor, Alliance $alliance, string $reason): Alliance
    {
        $this->authorization->authorize($actor);

        if ($alliance->status !== AllianceStatus::Active) {
            throw new InvalidArgumentException('Only active alliances can be suspended.');
        }

        return $this->transition($actor, $alliance, AllianceStatus::Suspended, $reason, [
            'suspended_at' => now(),
        ], 'platform.alliance.suspended');
    }

    public function close(User $actor, Alliance $alliance, string $reason): Alliance
    {
        $this->authorization->authorize($actor);

        if (! in_array($alliance->status, [AllianceStatus::Active, AllianceStatus::Suspended], true)) {
            throw new InvalidArgumentException('Only active or suspended alliances can be closed.');
        }

        $settings = AlliancePlatformSetting::query()->whereKey($alliance->id)->first();
        $retentionDays = $settings instanceof AlliancePlatformSetting
            ? (int) $settings->retention_days
            : 30;

        return $this->transition($actor, $alliance, AllianceStatus::Closed, $reason, [
            'closed_at' => now(),
            'retention_until' => now()->addDays(max(1, min(3650, $retentionDays))),
        ], 'platform.alliance.closed');
    }

    public function delete(User $actor, Alliance $alliance, string $reason): Alliance
    {
        $this->authorization->authorize($actor);

        if ($alliance->status !== AllianceStatus::Closed) {
            throw new InvalidArgumentException('An alliance must be closed before it can be deleted.');
        }
        if ($this->legalHolds->active('alliance', (string) $alliance->id)) {
            throw new InvalidArgumentException('This alliance is protected by an active legal hold.');
        }

        return $this->transition($actor, $alliance, AllianceStatus::Deleted, $reason, [
            'deleted_at' => now(),
        ], 'platform.alliance.deleted');
    }

    public function restore(User $actor, Alliance $alliance, string $reason): Alliance
    {
        $this->authorization->authorize($actor);

        if (! in_array($alliance->status, [AllianceStatus::Suspended, AllianceStatus::Closed, AllianceStatus::Deleted], true)) {
            throw new InvalidArgumentException('Only suspended, closed, or deleted alliances can be restored.');
        }
        if ($alliance->status === AllianceStatus::Deleted
            && $alliance->retention_until !== null
            && $alliance->retention_until->isPast()) {
            throw new InvalidArgumentException('The alliance restoration window has expired.');
        }

        return $this->transition($actor, $alliance, AllianceStatus::Active, $reason, [
            'suspended_at' => null,
            'closed_at' => null,
            'deleted_at' => null,
            'retention_until' => null,
            'restored_at' => now(),
        ], 'platform.alliance.restored');
    }

    /** @param array<string, mixed> $attributes */
    private function transition(
        User $actor,
        Alliance $alliance,
        AllianceStatus $target,
        string $reason,
        array $attributes,
        string $event,
    ): Alliance {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A lifecycle reason is required.');
        }

        return DB::transaction(function () use ($actor, $alliance, $target, $reason, $attributes, $event): Alliance {
            $locked = Alliance::query()->whereKey($alliance->id)->lockForUpdate()->first();
            if (! $locked instanceof Alliance) {
                throw new InvalidArgumentException('Alliance no longer exists.');
            }
            $previous = $locked->status;
            $locked->forceFill([
                ...$attributes,
                'status' => $target,
                'lifecycle_reason' => $reason,
            ])->save();

            $this->audit->record($event, $actor, $locked, $locked, [
                'from' => $previous->value,
                'to' => $target->value,
                'reason' => $reason,
                'retention_until' => $locked->retention_until?->toIso8601String(),
            ]);
            $this->outbox->record($event, (string) $locked->id, $locked, [
                'alliance_id' => $locked->id,
                'from' => $previous->value,
                'to' => $target->value,
            ]);

            return $locked->refresh();
        });
    }
}
