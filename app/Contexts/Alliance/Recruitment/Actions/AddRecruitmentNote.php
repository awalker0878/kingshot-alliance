<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentNote;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AddRecruitmentNote
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AllianceAuthorization $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $actorPlayerId, string $allianceId, string $candidateId, string $body): string
    {
        $cleanBody = trim($body);
        if ($cleanBody === '') {
            throw ValidationException::withMessages(['note' => 'Recruitment note text is required.']);
        }

        return DB::transaction(function () use ($actorPlayerId, $allianceId, $candidateId, $cleanBody): string {
            $context = $this->allianceWriteState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::RecruitmentManage);

            $currentCandidate = RecruitmentCandidate::query()
                ->whereKey($candidateId)
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
                'author_player_id' => $context->actor->playerId,
                'body' => $cleanBody,
            ]);

            $this->audit->record('recruitment.note.created', $context->actor, $note, $context->alliance, [
                'candidate_id' => $currentCandidate->id,
            ]);
            $this->outbox->record('recruitment.note.created', (string) $context->alliance->id, $note, [
                'candidate_id' => $currentCandidate->id,
            ]);

            return (string) $note->id;
        });
    }
}
