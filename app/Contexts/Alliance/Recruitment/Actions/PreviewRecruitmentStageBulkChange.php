<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use Illuminate\Validation\ValidationException;

final readonly class PreviewRecruitmentStageBulkChange
{
    public function __construct(private AllianceAuthorization $authority) {}

    /**
     * @param  list<string>  $candidateIds
     * @return array{
     *   targetStage: string,
     *   items: non-empty-list<array{itemId: string, label: string, fromStage: string|null, outcome: string, code: string}>,
     *   ready: int,
     *   blocked: int,
     *   readyItemIds: list<string>
     * }
     */
    public function handle(
        string $actorPlayerId,
        string $allianceId,
        array $candidateIds,
        RecruitmentStage $target,
    ): array {
        $candidateIds = array_values(array_unique($candidateIds));
        if ($candidateIds === [] || count($candidateIds) > 50) {
            throw ValidationException::withMessages(['candidate_ids' => 'Select between 1 and 50 recruitment candidates.']);
        }
        $this->authority->authorize($actorPlayerId, $allianceId, AlliancePermission::RecruitmentManage);

        $candidates = RecruitmentCandidate::query()
            ->where('alliance_id', $allianceId)
            ->whereIn('id', $candidateIds)
            ->get()
            ->keyBy(static fn (RecruitmentCandidate $candidate): string => (string) $candidate->id);
        $items = [];
        $readyItemIds = [];

        foreach ($candidateIds as $candidateId) {
            $candidate = $candidates->get($candidateId);
            if (! $candidate instanceof RecruitmentCandidate) {
                $items[] = $this->item($candidateId, $candidateId, null, 'blocked', 'candidate-unavailable');

                continue;
            }

            $label = (string) $candidate->full_name;
            $from = $candidate->recruitmentStage();
            if ($candidate->merged_into_id !== null || $candidate->anonymized_at !== null) {
                $items[] = $this->item($candidateId, $label, $from, 'blocked', 'candidate-unavailable');
            } elseif ($target === RecruitmentStage::Joined) {
                $items[] = $this->item($candidateId, $label, $from, 'blocked', 'transition-not-allowed');
            } elseif ($from === $target) {
                $items[] = $this->item($candidateId, $label, $from, 'skipped', 'already-in-target-stage');
            } elseif (! $from->canTransitionTo($target)) {
                $items[] = $this->item($candidateId, $label, $from, 'blocked', 'transition-not-allowed');
            } else {
                $items[] = $this->item($candidateId, $label, $from, 'ready', 'ready');
                $readyItemIds[] = $candidateId;
            }
        }

        return [
            'targetStage' => $target->value,
            'items' => $items,
            'ready' => count($readyItemIds),
            'blocked' => count($candidateIds) - count($readyItemIds),
            'readyItemIds' => $readyItemIds,
        ];
    }

    /** @return array{itemId: string, label: string, fromStage: string|null, outcome: string, code: string} */
    private function item(
        string $itemId,
        string $label,
        ?RecruitmentStage $from,
        string $outcome,
        string $code,
    ): array {
        return [
            'itemId' => $itemId,
            'label' => $label,
            'fromStage' => $from?->value,
            'outcome' => $outcome,
            'code' => $code,
        ];
    }
}
