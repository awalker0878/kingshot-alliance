<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AlliancePermissionEvaluator;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Membership\Services\IssueAllianceInvitation;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentOnboardingStatus;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidateOnboarding;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentOnboardingItem;
use App\Contexts\Alliance\Recruitment\Services\RecruitmentReentryPolicy;
use App\Contexts\Alliance\Recruitment\ValueObjects\ConvertedRecruitmentCandidate;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ConvertAcceptedRecruitmentCandidate
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AllianceAuthorization $authority,
        private AlliancePermissionEvaluator $permissions,
        private IssueAllianceInvitation $invitationIssuer,
        private RecruitmentReentryPolicy $reentryPolicy,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $allianceId,
        string $candidateId,
        string $targetPlayerId,
    ): ConvertedRecruitmentCandidate {
        return DB::transaction(function () use ($actorPlayerId, $allianceId, $candidateId, $targetPlayerId): ConvertedRecruitmentCandidate {
            $context = $this->allianceWriteState->lockExclusiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::RecruitmentManage);
            if (! $this->permissions->allows($context->membership, $context->alliance, AlliancePermission::InvitationManage)) {
                throw new AuthorizationException('Invitation management permission is required to convert a candidate.');
            }

            $locked = RecruitmentCandidate::query()
                ->where('alliance_id', $context->alliance->id)
                ->whereKey($candidateId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->merged_into_id !== null) {
                throw ValidationException::withMessages(['candidate' => 'A merged recruitment record cannot be converted.']);
            }
            if ($locked->stage !== RecruitmentStage::Accepted) {
                throw ValidationException::withMessages(['candidate' => 'Only accepted recruitment candidates can be converted.']);
            }
            $this->reentryPolicy->assertCanConvert($locked);

            if ($locked->membership_invitation_id !== null) {
                if ($locked->player_id !== null && (string) $locked->player_id !== $targetPlayerId) {
                    throw ValidationException::withMessages(['player_id' => 'This candidate was already converted for a different Player.']);
                }

                return new ConvertedRecruitmentCandidate((string) $locked->id, (string) $locked->membership_invitation_id, null, false);
            }

            $issued = $this->invitationIssuer->handle($context, $targetPlayerId, (string) $locked->email);
            $locked->forceFill([
                'player_id' => $targetPlayerId,
                'membership_invitation_id' => $issued->invitationId,
                'updated_by_player_id' => $context->actor->playerId,
            ])->save();

            $items = RecruitmentOnboardingItem::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('is_active', true)
                ->orderBy('position')
                ->orderBy('id')
                ->sharedLock()
                ->get();
            foreach ($items as $item) {
                RecruitmentCandidateOnboarding::query()->firstOrCreate(
                    ['candidate_id' => $locked->id, 'onboarding_item_id' => $item->id],
                    ['alliance_id' => $context->alliance->id, 'status' => RecruitmentOnboardingStatus::Pending],
                );
            }

            $metadata = [
                'candidate_id' => (string) $locked->id,
                'player_id' => $targetPlayerId,
                'membership_invitation_id' => $issued->invitationId,
                'onboarding_item_count' => $items->count(),
            ];
            $this->audit->record('recruitment.candidate.converted', $context->actor, $locked, $context->alliance, $metadata);
            $this->outbox->record('recruitment.candidate.converted', (string) $context->alliance->id, $locked, $metadata);

            return new ConvertedRecruitmentCandidate((string) $locked->id, $issued->invitationId, $issued->token, true);
        });
    }
}
