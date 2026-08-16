<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentNote;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AddRecruitmentNote
{
    public function __construct(
        private AllianceAuthorization $authority,
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
            $context = $this->authority->require($actor, $alliance, AlliancePermission::RecruitmentManage);

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
