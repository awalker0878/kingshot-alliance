<?php

declare(strict_types=1);

namespace App\Application\Recruitment;

use App\Application\Identity\AllianceAuthorization;
use App\Application\Identity\AuditRecorder;
use App\Domain\Identity\Authorization\PermissionKey;
use App\Domain\Identity\Enums\MembershipStatus;
use App\Models\Alliance;
use App\Models\AllianceMembership;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentNote;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AddRecruitmentNote
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private RecruitmentOutbox $outbox,
    ) {}

    public function handle(User $actor, Alliance $alliance, RecruitmentCandidate $candidate, string $body): RecruitmentNote
    {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::RecruitmentManage)) {
            throw new AuthorizationException('You are not allowed to add recruitment notes.');
        }

        if ($candidate->alliance_id !== $alliance->id) {
            throw new AuthorizationException('The candidate belongs to another alliance.');
        }

        $cleanBody = trim($body);
        if ($cleanBody === '') {
            throw ValidationException::withMessages(['note' => 'Recruitment note text is required.']);
        }

        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('user_id', $actor->id)
            ->where('status', MembershipStatus::Active->value)
            ->first();

        if (! $membership instanceof AllianceMembership) {
            throw new AuthorizationException('An active alliance membership is required to add recruitment notes.');
        }

        return DB::transaction(function () use ($actor, $alliance, $candidate, $cleanBody, $membership): RecruitmentNote {
            $note = RecruitmentNote::query()->create([
                'alliance_id' => $alliance->id,
                'candidate_id' => $candidate->id,
                'author_membership_id' => $membership->id,
                'body' => $cleanBody,
            ]);

            $this->audit->record('recruitment.note.created', $actor, $note, $alliance, [
                'candidate_id' => $candidate->id,
            ]);
            $this->outbox->record('recruitment.note.created', $alliance, $note, [
                'candidate_id' => $candidate->id,
            ]);

            return $note;
        });
    }
}
