<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Actions;

use App\Domain\Recruitment\Services\RecruitmentOutbox;
use App\Domain\Recruitment\ValueObjects\ConvertedRecruitmentCandidate;

use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Memberships\Actions\CreateInvitation;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Recruitment\Enums\RecruitmentOnboardingStatus;
use App\Domain\Recruitment\Enums\RecruitmentStage;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Memberships\Models\Invitation;
use App\Domain\Recruitment\Models\RecruitmentCandidate;
use App\Domain\Recruitment\Models\RecruitmentCandidateOnboarding;
use App\Domain\Recruitment\Models\RecruitmentOnboardingItem;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ConvertAcceptedRecruitmentCandidate
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private CreateInvitation $createInvitation,
        private AuditRecorder $audit,
        private RecruitmentOutbox $outbox,
    ) {}

    public function handle(
        User $actor,
        Alliance $alliance,
        RecruitmentCandidate $candidate,
    ): ConvertedRecruitmentCandidate {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::RecruitmentManage)) {
            throw new AuthorizationException('You are not allowed to convert recruitment candidates.');
        }

        if ($candidate->alliance_id !== $alliance->id) {
            throw new AuthorizationException('The candidate belongs to another alliance.');
        }

        return DB::transaction(function () use ($actor, $alliance, $candidate): ConvertedRecruitmentCandidate {
            $locked = RecruitmentCandidate::query()
                ->where('alliance_id', $alliance->id)
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
                $existing = Invitation::query()
                    ->where('alliance_id', $alliance->id)
                    ->whereKey($locked->membership_invitation_id)
                    ->firstOrFail();

                return new ConvertedRecruitmentCandidate($locked, $existing, null, false);
            }

            $issued = $this->createInvitation->handle($alliance, $actor, (string) $locked->email);

            $locked->forceFill([
                'membership_invitation_id' => $issued->invitation->id,
                'updated_by_user_id' => $actor->id,
            ])->save();

            $items = RecruitmentOnboardingItem::query()
                ->where('alliance_id', $alliance->id)
                ->where('is_active', true)
                ->orderBy('position')
                ->orderBy('id')
                ->get();

            foreach ($items as $item) {
                RecruitmentCandidateOnboarding::query()->firstOrCreate(
                    [
                        'candidate_id' => $locked->id,
                        'onboarding_item_id' => $item->id,
                    ],
                    [
                        'alliance_id' => $alliance->id,
                        'status' => RecruitmentOnboardingStatus::Pending,
                    ],
                );
            }

            $this->audit->record('recruitment.candidate.converted', $actor, $locked, $alliance, [
                'membership_invitation_id' => $issued->invitation->id,
                'onboarding_item_count' => $items->count(),
            ]);
            $this->outbox->record('recruitment.candidate.converted', $alliance, $locked, [
                'candidate_id' => $locked->id,
                'membership_invitation_id' => $issued->invitation->id,
                'onboarding_item_count' => $items->count(),
            ]);

            return new ConvertedRecruitmentCandidate(
                $locked->refresh(),
                $issued->invitation,
                $issued->token,
                true,
            );
        });
    }
}
