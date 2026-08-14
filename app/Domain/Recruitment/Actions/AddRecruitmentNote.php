<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use App\Domain\Recruitment\Models\RecruitmentCandidate;
use App\Domain\Recruitment\Models\RecruitmentNote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AddRecruitmentNote
{
    public function __construct(
        private AllianceMutationAuthority $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, Alliance $alliance, RecruitmentCandidate $candidate, string $body): RecruitmentNote
    {
        $cleanBody = trim($body);
        if ($cleanBody === '') {
            throw ValidationException::withMessages(['note' => 'Recruitment note text is required.']);
        }

        return DB::transaction(function () use ($actor, $alliance, $candidate, $cleanBody): RecruitmentNote {
            $context = $this->authority->require($actor, $alliance, PermissionKey::RecruitmentManage);

            // Notes are independent children, so they share-lock the candidate while
            // candidate-wide transitions/merges take an exclusive candidate lock.
            $currentCandidate = RecruitmentCandidate::query()
                ->whereKey($candidate->id)
                ->where('alliance_id', $context->alliance->id)
                ->sharedLock()
                ->firstOrFail();

            if ($currentCandidate->merged_into_id !== null) {
                throw ValidationException::withMessages([
                    'candidate' => 'Notes must be added to the current merged candidate record.',
                ]);
            }

            $note = RecruitmentNote::query()->create([
                'alliance_id' => $context->alliance->id,
                'candidate_id' => $currentCandidate->id,
                'author_player_id' => $context->actor->id,
                'body' => $cleanBody,
            ]);

            $this->audit->record('recruitment.note.created', $context->actor, $note, $context->alliance, [
                'candidate_id' => $currentCandidate->id,
            ]);
            $this->outbox->record('recruitment.note.created', (string) $context->alliance->id, $note, [
                'candidate_id' => $currentCandidate->id,
            ]);

            return $note;
        });
    }
}
