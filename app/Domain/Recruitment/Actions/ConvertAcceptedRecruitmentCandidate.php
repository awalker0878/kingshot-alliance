<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Shared\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Domain\Authorization\Services\AlliancePermissionEvaluator;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Memberships\Services\IssueAllianceInvitation;
use App\Shared\Messaging\Services\OutboxRecorder;
use App\Domain\Recruitment\Enums\RecruitmentOnboardingStatus;
use App\Domain\Recruitment\Enums\RecruitmentStage;
use App\Domain\Recruitment\Models\RecruitmentCandidate;
use App\Domain\Recruitment\Models\RecruitmentCandidateOnboarding;
use App\Domain\Recruitment\Models\RecruitmentOnboardingItem;
use App\Domain\Recruitment\ValueObjects\ConvertedRecruitmentCandidate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ConvertAcceptedRecruitmentCandidate
{
    public function __construct(
        private AllianceMutationAuthority $authority,
        private AlliancePermissionEvaluator $permissions,
        private IssueAllianceInvitation $invitationIssuer,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Player $actor,
        Alliance $alliance,
        RecruitmentCandidate $candidate,
        Player $target,
    ): ConvertedRecruitmentCandidate {
        return DB::transaction(function () use ($actor, $alliance, $candidate, $target): ConvertedRecruitmentCandidate {
            // Conversion creates a member-capacity reservation, so acquire the
            // Alliance-wide boundary before candidate/Player child state.
            $context = $this->authority->requireExclusive(
                $actor,
                $alliance,
                PermissionKey::RecruitmentManage,
            );

            // Conversion composes Recruitment and Memberships. Both permissions must
            // be true on the same locked membership authority snapshot.
            if (! $this->permissions->allows(
                $context->membership,
                $context->alliance,
                PermissionKey::InvitationManage,
            )) {
                throw new AuthorizationException('Invitation management permission is required to convert a candidate.');
            }

            $locked = RecruitmentCandidate::query()
                ->where('alliance_id', $context->alliance->id)
                ->whereKey($candidate->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->merged_into_id !== null) {
                throw ValidationException::withMessages(['candidate' => 'A merged recruitment record cannot be converted.']);
            }

            if ($locked->stage !== RecruitmentStage::Accepted) {
                throw ValidationException::withMessages(['candidate' => 'Only accepted recruitment candidates can be converted.']);
            }

            if ($locked->membership_invitation_id !== null) {
                if ($locked->player_id !== null && (string) $locked->player_id !== (string) $target->id) {
                    throw ValidationException::withMessages([
                        'player_id' => 'This candidate was already converted for a different Player.',
                    ]);
                }

                return new ConvertedRecruitmentCandidate(
                    $locked,
                    (string) $locked->membership_invitation_id,
                    null,
                    false,
                );
            }

            $issued = $this->invitationIssuer->handle($context, $target, (string) $locked->email);

            $locked->forceFill([
                'player_id' => $target->id,
                'membership_invitation_id' => $issued->invitationId,
                'updated_by_player_id' => $context->actor->id,
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
                    [
                        'candidate_id' => $locked->id,
                        'onboarding_item_id' => $item->id,
                    ],
                    [
                        'alliance_id' => $context->alliance->id,
                        'status' => RecruitmentOnboardingStatus::Pending,
                    ],
                );
            }

            $this->audit->record('recruitment.candidate.converted', $context->actor, $locked, $context->alliance, [
                'player_id' => $target->id,
                'membership_invitation_id' => $issued->invitationId,
                'onboarding_item_count' => $items->count(),
            ]);
            $this->outbox->record('recruitment.candidate.converted', (string) $context->alliance->id, $locked, [
                'candidate_id' => $locked->id,
                'player_id' => $target->id,
                'membership_invitation_id' => $issued->invitationId,
                'onboarding_item_count' => $items->count(),
            ]);

            return new ConvertedRecruitmentCandidate(
                $locked->refresh(),
                $issued->invitationId,
                $issued->token,
                true,
            );
        });
    }
}
