<?php

declare(strict_types=1);

namespace App\Contexts\Platform\DataGovernance\Services;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Platform\Administration\Services\PlatformAuthorization;
use App\Contexts\Platform\Administration\Services\PlatformWriteState;
use App\Contexts\Platform\DataGovernance\Models\LegalHold;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class LegalHoldService
{
    public function __construct(
        private AuditRecorder $audit,
        private PlatformWriteState $platformWriteState,
        private PlatformAuthorization $mutations,
        private AccountIdentityQuery $accounts,
        private AllianceReferenceQuery $alliances,
    ) {}

    public function active(string $subjectType, string $subjectId): bool
    {
        return LegalHold::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->whereNull('released_at')
            ->exists();
    }

    public function place(AccountIdentity $actor, string $subjectType, string $subjectId, string $reason): void
    {
        $this->assertSubjectType($subjectType);
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A legal hold reason is required.');
        }

        DB::transaction(function () use ($actor, $subjectType, $subjectId, $reason): void {
            $context = $this->mutations->authorizeContext($this->platformWriteState->lock($actor));
            $this->lockSubject($subjectType, $subjectId);

            $hold = LegalHold::query()->create([
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'reason' => $reason,
                'placed_by_user_id' => $context->actor->userId,
                'placed_at' => now(),
            ]);

            $this->audit->record(
                'platform.legal-hold.placed',
                $context->actor,
                $hold,
                null,
                ['subject_type' => $subjectType, 'subject_id' => $subjectId, 'reason' => $reason],
            );
        });
    }

    public function release(AccountIdentity $actor, string $holdId): void
    {
        DB::transaction(function () use ($actor, $holdId): void {
            $context = $this->mutations->authorizeContext($this->platformWriteState->lock($actor));
            $route = LegalHold::query()->select(['id', 'subject_type', 'subject_id'])->whereKey($holdId)->firstOrFail();
            $subjectType = (string) $route->subject_type;
            $subjectId = (string) $route->subject_id;
            $this->assertSubjectType($subjectType);
            $this->lockSubject($subjectType, $subjectId);

            $locked = LegalHold::query()->whereKey($route->id)->lockForUpdate()->firstOrFail();
            if ($locked->released_at !== null) {
                return;
            }

            $locked->forceFill([
                'released_by_user_id' => $context->actor->userId,
                'released_at' => now(),
            ])->save();

            $this->audit->record(
                'platform.legal-hold.released',
                $context->actor,
                $locked,
                null,
                ['subject_type' => $subjectType, 'subject_id' => $subjectId],
            );
        });
    }

    private function assertSubjectType(string $subjectType): void
    {
        if (! in_array($subjectType, ['user', 'alliance'], true)) {
            throw new InvalidArgumentException('Legal holds may target only users or alliances.');
        }
    }

    private function lockSubject(string $subjectType, string $subjectId): void
    {
        match ($subjectType) {
            'alliance' => $this->alliances->lockCurrent($subjectId),
            'user' => $this->accounts->lockCurrent($this->userId($subjectId)),
            default => throw new InvalidArgumentException('Unsupported legal hold subject type.'),
        };
    }

    private function userId(string $subjectId): int
    {
        if (! ctype_digit($subjectId) || (int) $subjectId < 1) {
            throw new InvalidArgumentException('User legal hold subjects must use a numeric user ID.');
        }

        return (int) $subjectId;
    }
}
