<?php

declare(strict_types=1);

namespace App\Domain\Platform\Actions;

use App\Domain\Alliances\Enums\AllianceStatus;
use App\Domain\Alliances\Models\Alliance;
use App\Shared\Audit\Services\AuditRecorder;
use App\Contexts\Accounts\Models\User;
use App\Domain\Platform\Models\AlliancePlatformSetting;
use App\Domain\Platform\Services\LegalHoldService;
use App\Shared\Messaging\Services\OutboxRecorder;
use App\Domain\Platform\Services\PlatformMutationAuthority;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class ManageAllianceLifecycle
{
    public function __construct(
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
        private LegalHoldService $legalHolds,
        private PlatformMutationAuthority $mutations,
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

            // Alliance lifecycle is a true parent-wide invariant. This exclusive row
            // lock intentionally serializes every child mutation that takes the normal
            // shared Alliance lifecycle boundary.
            $locked = Alliance::query()->whereKey($alliance->id)->lockForUpdate()->first();
            if (! $locked instanceof Alliance) {
                throw new InvalidArgumentException('Alliance no longer exists.');
            }

            $this->assertTransitionAllowed($locked->status, $target);

            if ($target === AllianceStatus::Deleted
                && $this->legalHolds->active('alliance', (string) $locked->id)) {
                throw new InvalidArgumentException('This alliance is protected by an active legal hold.');
            }

            if ($target === AllianceStatus::Active
                && $locked->status === AllianceStatus::Deleted
                && $locked->retention_until !== null
                && $locked->retention_until->isPast()) {
                throw new InvalidArgumentException('The alliance restoration window has expired.');
            }

            $attributes = $this->transitionAttributes($locked, $target);
            $previous = $locked->status;
            $locked->forceFill([
                ...$attributes,
                'status' => $target,
                'lifecycle_reason' => $reason,
            ])->save();

            $this->audit->record($event, $context->actor, $locked, $locked, [
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

    /** @return array<string, mixed> */
    private function transitionAttributes(Alliance $alliance, AllianceStatus $target): array
    {
        return match ($target) {
            AllianceStatus::Suspended => [
                'suspended_at' => now(),
            ],
            AllianceStatus::Closed => $this->closeAttributes($alliance),
            AllianceStatus::Deleted => [
                'deleted_at' => now(),
            ],
            AllianceStatus::Active => [
                'suspended_at' => null,
                'closed_at' => null,
                'deleted_at' => null,
                'retention_until' => null,
                'restored_at' => now(),
            ],
        };
    }

    /** @return array<string, mixed> */
    private function closeAttributes(Alliance $alliance): array
    {
        $settings = AlliancePlatformSetting::query()
            ->whereKey($alliance->id)
            ->lockForUpdate()
            ->first();
        $retentionDays = $settings instanceof AlliancePlatformSetting
            ? (int) $settings->retention_days
            : 30;

        return [
            'closed_at' => now(),
            'retention_until' => now()->addDays(max(1, min(3650, $retentionDays))),
        ];
    }

    private function assertTransitionAllowed(AllianceStatus $from, AllianceStatus $to): void
    {
        $allowed = match ($to) {
            AllianceStatus::Suspended => [AllianceStatus::Active],
            AllianceStatus::Closed => [AllianceStatus::Active, AllianceStatus::Suspended],
            AllianceStatus::Deleted => [AllianceStatus::Closed],
            AllianceStatus::Active => [AllianceStatus::Suspended, AllianceStatus::Closed, AllianceStatus::Deleted],
        };

        if (! in_array($from, $allowed, true)) {
            throw new InvalidArgumentException(sprintf(
                'Alliance lifecycle transition from %s to %s is not allowed.',
                $from->value,
                $to->value,
            ));
        }
    }
}
