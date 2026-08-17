<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Lifecycle\Services;

use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use Carbon\CarbonInterface;
use InvalidArgumentException;

final class AllianceLifecycleMutation
{
    public function acquire(string $allianceId): Alliance
    {
        $locked = Alliance::query()->whereKey($allianceId)->lockForUpdate()->first();
        if (! $locked instanceof Alliance) {
            throw new InvalidArgumentException('Alliance no longer exists.');
        }

        return $locked;
    }

    /** Owner-internal mutation over an Alliance row acquired by acquire(). */
    public function transitionLocked(
        Alliance $alliance,
        AllianceStatus $target,
        string $reason,
        ?CarbonInterface $retentionUntil = null,
    ): Alliance {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A lifecycle reason is required.');
        }

        $this->assertTransitionAllowed($alliance->status, $target);
        if ($target === AllianceStatus::Active
            && $alliance->status === AllianceStatus::Deleted
            && $alliance->retention_until !== null
            && $alliance->retention_until->isPast()) {
            throw new InvalidArgumentException('The alliance restoration window has expired.');
        }

        if ($target === AllianceStatus::Closed && $retentionUntil === null) {
            throw new InvalidArgumentException('Closing an alliance requires a retention deadline.');
        }

        $alliance->forceFill([
            ...$this->transitionAttributes($target, $retentionUntil),
            'status' => $target,
            'lifecycle_reason' => $reason,
        ])->save();

        return $alliance->refresh();
    }

    /** @return array<string, mixed> */
    private function transitionAttributes(AllianceStatus $target, ?CarbonInterface $retentionUntil): array
    {
        return match ($target) {
            AllianceStatus::Suspended => ['suspended_at' => now()],
            AllianceStatus::Closed => ['closed_at' => now(), 'retention_until' => $retentionUntil],
            AllianceStatus::Deleted => ['deleted_at' => now()],
            AllianceStatus::Active => [
                'suspended_at' => null,
                'closed_at' => null,
                'deleted_at' => null,
                'retention_until' => null,
                'restored_at' => now(),
            ],
        };
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
