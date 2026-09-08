<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Actions;

use App\Contexts\GameWorld\Players\Enums\PlayerIdentitySource;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\Models\PlayerReconciliation;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\Services\PlayerIdentityHistoryRecorder;
use App\Contexts\GameWorld\Players\Services\PlayerLifecyclePolicy;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ReconcilePlayers
{
    public function __construct(
        private PlayerReferenceQuery $references,
        private PlayerIdentityHistoryRecorder $history,
        private PlayerLifecyclePolicy $lifecycle,
        private AuditRecorder $audit,
    ) {}

    public function handle(
        string $canonicalPlayerId,
        string $duplicatePlayerId,
        string $reason,
        PlayerIdentitySource $source = PlayerIdentitySource::SystemReconciliation,
        ?string $sourceReference = null,
        ?int $confidenceBasisPoints = null,
        ?AuditActor $actor = null,
    ): PlayerReference {
        $reason = trim($reason);
        if ($canonicalPlayerId === $duplicatePlayerId) {
            throw ValidationException::withMessages(['duplicate_player_id' => 'Canonical and duplicate Player identities must be different.']);
        }
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reconciliation reason is required.']);
        }
        if ($confidenceBasisPoints !== null && ($confidenceBasisPoints < 0 || $confidenceBasisPoints > 10000)) {
            throw ValidationException::withMessages(['confidence_basis_points' => 'Confidence must be between 0 and 10000 basis points.']);
        }

        DB::transaction(function () use (
            $canonicalPlayerId,
            $duplicatePlayerId,
            $reason,
            $source,
            $sourceReference,
            $confidenceBasisPoints,
            $actor,
        ): void {
            $ids = [$canonicalPlayerId, $duplicatePlayerId];
            sort($ids, SORT_STRING);
            $locked = Player::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $canonical = $locked->get($canonicalPlayerId);
            $duplicate = $locked->get($duplicatePlayerId);
            if (! $canonical instanceof Player) {
                Player::query()->findOrFail($canonicalPlayerId);
            }
            if (! $duplicate instanceof Player) {
                Player::query()->findOrFail($duplicatePlayerId);
            }
            /** @var Player $canonical */
            /** @var Player $duplicate */

            if ((string) $duplicate->canonical_player_id === $canonicalPlayerId) {
                return;
            }
            if ($canonical->canonical_player_id !== null || $duplicate->canonical_player_id !== null) {
                throw ValidationException::withMessages([
                    'player' => 'Reconciliation inputs must be direct Player identities. Resolve an existing alias to its canonical identity first.',
                ]);
            }
            if ((string) $canonical->current_kingdom_id !== (string) $duplicate->current_kingdom_id) {
                throw ValidationException::withMessages(['player' => 'Player identities in different current Kingdoms cannot be reconciled directly.']);
            }

            $canonicalStableId = $this->nullable($canonical->game_player_id);
            $duplicateStableId = $this->nullable($duplicate->game_player_id);
            if ($canonicalStableId !== null && $duplicateStableId !== null && $canonicalStableId !== $duplicateStableId) {
                throw ValidationException::withMessages(['game_player_id' => 'Player identities with conflicting stable game IDs cannot be reconciled.']);
            }

            $canonicalUserId = $canonical->user_id === null ? null : (int) $canonical->user_id;
            $duplicateUserId = $duplicate->user_id === null ? null : (int) $duplicate->user_id;
            if ($canonicalUserId !== null && $duplicateUserId !== null && $canonicalUserId !== $duplicateUserId) {
                throw ValidationException::withMessages(['player' => 'Player identities owned by different accounts cannot be reconciled.']);
            }

            $this->lifecycle->assertReconciliationDuplicateIsDormant($duplicate);

            $effectiveStableId = $canonicalStableId ?? $duplicateStableId;
            $effectiveUserId = $canonicalUserId ?? $duplicateUserId;
            $canonicalChanges = $effectiveStableId !== $canonicalStableId || $effectiveUserId !== $canonicalUserId;
            if ($canonicalChanges) {
                $this->history->transition(
                    $canonical,
                    $effectiveUserId,
                    (string) $canonical->current_kingdom_id,
                    (string) $canonical->current_name,
                    $effectiveStableId,
                    $source,
                    $sourceReference,
                    confidenceBasisPoints: $confidenceBasisPoints,
                    reason: $reason,
                );
            }

            $this->history->closeCurrent($duplicate);
            $duplicate->forceFill([
                'user_id' => null,
                'game_player_id' => null,
                'canonical_player_id' => $canonicalPlayerId,
            ])->save();

            if ($canonicalChanges) {
                $canonical->forceFill([
                    'user_id' => $effectiveUserId,
                    'game_player_id' => $effectiveStableId,
                ])->save();
            }

            $reconciliation = PlayerReconciliation::query()->create([
                'canonical_player_id' => $canonicalPlayerId,
                'duplicate_player_id' => $duplicatePlayerId,
                'reason' => $reason,
                'source_type' => $source,
                'source_reference' => $this->nullable($sourceReference),
                'confidence_basis_points' => $confidenceBasisPoints,
                'reconciled_at' => now(),
            ]);

            $this->audit->record('player.reconciled', $actor, $canonical, null, [
                'reconciliation_id' => (string) $reconciliation->id,
                'canonical_player_id' => $canonicalPlayerId,
                'duplicate_player_id' => $duplicatePlayerId,
                'stable_game_player_id_transferred' => $canonicalStableId === null && $duplicateStableId !== null,
                'account_ownership_transferred' => $canonicalUserId === null && $duplicateUserId !== null,
                'source_type' => $source->value,
                'source_reference' => $sourceReference,
                'confidence_basis_points' => $confidenceBasisPoints,
                'reason' => $reason,
            ]);
        });

        return $this->references->requireCanonical($canonicalPlayerId);
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
