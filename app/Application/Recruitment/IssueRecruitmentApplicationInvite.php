<?php

declare(strict_types=1);

namespace App\Application\Recruitment;

use App\Application\Identity\AllianceAuthorization;
use App\Application\Identity\AuditRecorder;
use App\Domain\Identity\Authorization\PermissionKey;
use App\Models\Alliance;
use App\Models\RecruitmentApplicationInvite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class IssueRecruitmentApplicationInvite
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private RecruitmentApplicationTokenService $tokens,
        private AuditRecorder $audit,
        private RecruitmentOutbox $outbox,
    ) {}

    public function handle(
        User $actor,
        Alliance $alliance,
        ?string $email = null,
        int $ttlHours = 72,
    ): IssuedRecruitmentApplicationInvite {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::RecruitmentManage)) {
            throw new AuthorizationException('You are not allowed to issue recruitment application invitations.');
        }

        if ($ttlHours < 1 || $ttlHours > 720) {
            throw new InvalidArgumentException('Recruitment application invitation lifetime must be between 1 and 720 hours.');
        }

        $normalizedEmail = $email === null || trim($email) === '' ? null : Str::lower(trim($email));

        return DB::transaction(function () use ($actor, $alliance, $normalizedEmail, $ttlHours): IssuedRecruitmentApplicationInvite {
            $token = $this->tokens->issue();
            $invite = RecruitmentApplicationInvite::query()->create([
                'alliance_id' => $alliance->id,
                'email' => $normalizedEmail,
                'token_hash' => $this->tokens->hash($token),
                'expires_at' => now()->addHours($ttlHours),
                'created_by_user_id' => $actor->id,
            ]);

            $this->audit->record('recruitment.application_invite.created', $actor, $invite, $alliance, [
                'email_restricted' => $normalizedEmail !== null,
                'expires_at' => $invite->expires_at->toIso8601String(),
            ]);
            $this->outbox->record('recruitment.application_invite.created', $alliance, $invite, [
                'email_restricted' => $normalizedEmail !== null,
                'expires_at' => $invite->expires_at->toIso8601String(),
            ]);

            return new IssuedRecruitmentApplicationInvite($invite, $token);
        });
    }
}
