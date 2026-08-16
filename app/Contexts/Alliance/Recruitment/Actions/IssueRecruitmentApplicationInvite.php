<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentApplicationInvite;
use App\Contexts\Alliance\Recruitment\Services\RecruitmentApplicationTokenService;
use App\Contexts\Alliance\Recruitment\ValueObjects\IssuedRecruitmentApplicationInvite;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class IssueRecruitmentApplicationInvite
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AllianceAuthorization $authority,
        private RecruitmentApplicationTokenService $tokens,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Player $actor,
        Alliance $alliance,
        ?string $email = null,
        int $ttlHours = 72,
    ): IssuedRecruitmentApplicationInvite {
        if ($ttlHours < 1 || $ttlHours > 720) {
            throw new InvalidArgumentException('Recruitment application invitation lifetime must be between 1 and 720 hours.');
        }

        $normalizedEmail = $email === null || trim($email) === '' ? null : Str::lower(trim($email));

        return DB::transaction(function () use ($actor, $alliance, $normalizedEmail, $ttlHours): IssuedRecruitmentApplicationInvite {
            $context = $this->allianceWriteState->lockActiveScope($actor, $alliance);
            $this->authority->authorizeContext($context, AlliancePermission::RecruitmentManage);

            $token = $this->tokens->issue();
            $invite = RecruitmentApplicationInvite::query()->create([
                'alliance_id' => $context->alliance->id,
                'email' => $normalizedEmail,
                'token_hash' => $this->tokens->hash($token),
                'expires_at' => now()->addHours($ttlHours),
                'created_by_player_id' => $context->actor->id,
            ]);

            $this->audit->record('recruitment.application_invite.created', $context->actor, $invite, $context->alliance, [
                'email_restricted' => $normalizedEmail !== null,
                'expires_at' => $invite->expires_at->toIso8601String(),
            ]);
            $this->outbox->record('recruitment.application_invite.created', (string) $context->alliance->id, $invite, [
                'email_restricted' => $normalizedEmail !== null,
                'expires_at' => $invite->expires_at->toIso8601String(),
            ]);

            return new IssuedRecruitmentApplicationInvite($invite, $token);
        });
    }
}
