<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\Queries;

use App\Contexts\GameWorld\Kingdoms\Models\KingdomAlliance;

final class KingdomAllianceReconciliationCandidateQuery
{
    /**
     * Candidates are advisory only. Name/tag similarity must never perform an automatic merge.
     *
     * @return list<array{kingdom_alliance_id:string,reasons:list<string>}>
     */
    public function forAlliance(string $kingdomAllianceId): array
    {
        $target = KingdomAlliance::query()->findOrFail($kingdomAllianceId);
        $targetName = $this->normalize((string) $target->current_name);
        $targetTag = $this->normalize($target->current_tag === null ? null : (string) $target->current_tag);
        $candidates = [];

        foreach (KingdomAlliance::query()
            ->where('kingdom_id', $target->kingdom_id)
            ->whereKeyNot($target->id)
            ->whereNull('canonical_kingdom_alliance_id')
            ->get() as $candidate) {
            $reasons = [];
            if ($targetName !== '' && $this->normalize((string) $candidate->current_name) === $targetName) {
                $reasons[] = 'same_normalized_name';
            }
            if ($targetTag !== '' && $this->normalize($candidate->current_tag === null ? null : (string) $candidate->current_tag) === $targetTag) {
                $reasons[] = 'same_normalized_tag';
            }
            if ($reasons !== []) {
                $candidates[] = [
                    'kingdom_alliance_id' => (string) $candidate->id,
                    'reasons' => $reasons,
                ];
            }
        }

        return $candidates;
    }

    private function normalize(?string $value): string
    {
        return mb_strtolower(trim($value ?? ''), 'UTF-8');
    }
}
