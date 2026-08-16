<?php

declare(strict_types=1);

namespace App\Contexts\Platform\DataGovernance\Services;

use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Platform\Administration\Services\PlatformAuthorization;
use App\Contexts\Platform\Administration\Services\PlatformWriteState;
use App\Contexts\Platform\DataGovernance\Models\LegalHold;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class LegalHoldService
{
    public function __construct(private AuditRecorder $audit, private PlatformWriteState $platformWriteState, private PlatformAuthorization $mutations) {}

    public function active(string $subjectType, string $subjectId): bool
    {
        return LegalHold::query()->where('subject_type', $subjectType)->where('subject_id', $subjectId)->whereNull('released_at')->exists();
    }

    public function place(AccountIdentity $actor, string $subjectType, string $subjectId, string $reason): LegalHold
    {
        $this->assertSubjectType($subjectType);
        $reason = trim($reason);
        if ($reason === '') throw new InvalidArgumentException('A legal hold reason is required.');
        return DB::transaction(function () use ($actor, $subjectType, $subjectId, $reason): LegalHold {
            $context = $this->mutations->authorizeContext($this->platformWriteState->lock($actor));
            $this->lockSubject($subjectType, $subjectId);
            $hold = LegalHold::query()->create(['subject_type'=>$subjectType,'subject_id'=>$subjectId,'reason'=>$reason,'placed_by_user_id'=>$context->actor->id,'placed_at'=>now()]);
            $this->audit->record('platform.legal-hold.placed', $context->actor, $hold, null, ['subject_type'=>$subjectType,'subject_id'=>$subjectId,'reason'=>$reason]);
            return $hold;
        });
    }

    public function release(AccountIdentity $actor, LegalHold $hold): LegalHold
    {
        return DB::transaction(function () use ($actor, $hold): LegalHold {
            $context = $this->mutations->authorizeContext($this->platformWriteState->lock($actor));
            $route = LegalHold::query()->select(['id','subject_type','subject_id'])->whereKey($hold->id)->firstOrFail();
            $this->assertSubjectType((string) $route->subject_type);
            $this->lockSubject((string) $route->subject_type, (string) $route->subject_id);
            $locked = LegalHold::query()->whereKey($route->id)->lockForUpdate()->firstOrFail();
            if ($locked->released_at !== null) return $locked;
            $locked->forceFill(['released_by_user_id'=>$context->actor->id,'released_at'=>now()])->save();
            $this->audit->record('platform.legal-hold.released', $context->actor, $locked, null, ['subject_type'=>$locked->subject_type,'subject_id'=>$locked->subject_id]);
            return $locked->refresh();
        });
    }

    private function assertSubjectType(string $subjectType): void
    {
        if (! in_array($subjectType, ['user','alliance'], true)) throw new InvalidArgumentException('Legal holds may target only users or alliances.');
    }

    private function lockSubject(string $subjectType, string $subjectId): Model
    {
        return match ($subjectType) {
            'alliance' => Alliance::query()->whereKey($subjectId)->lockForUpdate()->firstOrFail(),
            'user' => User::query()->whereKey($subjectId)->lockForUpdate()->firstOrFail(),
            default => throw new InvalidArgumentException('Unsupported legal hold subject type.'),
        };
    }
}
