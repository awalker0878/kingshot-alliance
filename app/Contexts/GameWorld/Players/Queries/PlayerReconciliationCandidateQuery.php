<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Queries;

use App\Contexts\GameWorld\Players\Models\Player;

final class PlayerReconciliationCandidateQuery
{
    /**
     * Candidate signals are advisory only. Name similarity must never merge Players.
     *
     * @return list<array{player_id:string,reasons:list<string>}>
     */
    public function forPlayer(string $playerId): array
    {
        $target = Player::query()->whereNull('canonical_player_id')->findOrFail($playerId);
        $targetName = $this->normalize((string) $target->current_name);
        $targetUserId = $target->user_id === null ? null : (int) $target->user_id;
        $candidates = [];

        foreach (Player::query()
            ->where('current_kingdom_id', $target->current_kingdom_id)
            ->whereKeyNot($target->id)
            ->whereNull('canonical_player_id')
            ->get() as $candidate) {
            $reasons = [];
            if ($targetName !== '' && $this->normalize((string) $candidate->current_name) === $targetName) {
                $reasons[] = 'same_normalized_name';
            }
            if ($targetUserId !== null && $candidate->user_id !== null && (int) $candidate->user_id === $targetUserId) {
                $reasons[] = 'same_account_owner';
            }
            if ($reasons !== []) {
                $candidates[] = [
                    'player_id' => (string) $candidate->id,
                    'reasons' => $reasons,
                ];
            }
        }

        return $candidates;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value), 'UTF-8');
    }
}
