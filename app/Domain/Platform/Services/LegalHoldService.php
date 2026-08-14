<?php

declare(strict_types=1);

namespace App\Domain\Platform\Services;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\LegalHold;
use InvalidArgumentException;

final readonly class LegalHoldService
{
    public function __construct(
        private AuditRecorder $audit,
        private PlatformAdministratorAuthorization $authorization,
    ) {}

    public function active(string $subjectType, string $subjectId): bool
    {
        return LegalHold::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->whereNull('released_at')
            ->exists();
    }

    public function place(User $actor, string $subjectType, string $subjectId, string $reason): LegalHold
    {
        $this->authorization->authorize($actor);

        if (! in_array($subjectType, ['user', 'alliance'], true)) {
            throw new InvalidArgumentException('Legal holds may target only users or alliances.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('A legal hold reason is required.');
        }

        $hold = LegalHold::query()->create([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'reason' => trim($reason),
            'placed_by_user_id' => $actor->id,
            'placed_at' => now(),
        ]);

        $this->audit->record('platform.legal-hold.placed', $actor, $hold, null, [
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'reason' => trim($reason),
        ]);

        return $hold;
    }

    public function release(User $actor, LegalHold $hold): LegalHold
    {
        $this->authorization->authorize($actor);

        if ($hold->released_at !== null) {
            return $hold;
        }

        $hold->forceFill([
            'released_by_user_id' => $actor->id,
            'released_at' => now(),
        ])->save();

        $this->audit->record('platform.legal-hold.released', $actor, $hold, null, [
            'subject_type' => $hold->subject_type,
            'subject_id' => $hold->subject_id,
        ]);

        return $hold->refresh();
    }
}
