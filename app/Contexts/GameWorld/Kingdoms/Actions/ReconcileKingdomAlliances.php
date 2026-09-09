<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\Actions;

use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceIdentitySource;
use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceStatus;
use App\Contexts\GameWorld\Kingdoms\Enums\KingdomStatus;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Kingdoms\Models\KingdomAlliance;
use App\Contexts\GameWorld\Kingdoms\Models\KingdomAllianceReconciliation;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomAllianceReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\Services\KingdomAllianceIdentityHistoryRecorder;
use App\Contexts\GameWorld\Kingdoms\ValueObjects\KingdomAllianceReference;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ReconcileKingdomAlliances
{
    public function __construct(
        private KingdomAllianceReferenceQuery $references,
        private KingdomAllianceIdentityHistoryRecorder $history,
        private AuditRecorder $audit,
    ) {}

    public function handle(
        string $canonicalKingdomAllianceId,
        string $duplicateKingdomAllianceId,
        string $reason,
        KingdomAllianceIdentitySource $source = KingdomAllianceIdentitySource::SystemReconciliation,
        ?string $sourceReference = null,
        ?int $confidenceBasisPoints = null,
        ?AuditActor $actor = null,
    ): KingdomAllianceReference {
        $reason = trim($reason);
        if ($canonicalKingdomAllianceId === $duplicateKingdomAllianceId) {
            throw ValidationException::withMessages(['duplicate_kingdom_alliance_id' => 'Canonical and duplicate Alliance identities must be different.']);
        }
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reconciliation reason is required.']);
        }
        if ($confidenceBasisPoints !== null && ($confidenceBasisPoints < 0 || $confidenceBasisPoints > 10000)) {
            throw ValidationException::withMessages(['confidence_basis_points' => 'Confidence must be between 0 and 10000 basis points.']);
        }

        DB::transaction(function () use (
            $canonicalKingdomAllianceId,
            $duplicateKingdomAllianceId,
            $reason,
            $source,
            $sourceReference,
            $confidenceBasisPoints,
            $actor,
        ): void {
            $ids = [$canonicalKingdomAllianceId, $duplicateKingdomAllianceId];
            sort($ids, SORT_STRING);
            $candidate = KingdomAlliance::query()->findOrFail($canonicalKingdomAllianceId);
            $kingdom = Kingdom::query()->whereKey($candidate->kingdom_id)->lockForUpdate()->firstOrFail();
            $locked = KingdomAlliance::query()->whereIn('id', $ids)->where('kingdom_id', $kingdom->id)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $canonical = $locked->get($canonicalKingdomAllianceId);
            $duplicate = $locked->get($duplicateKingdomAllianceId);
            if ($canonical === null) {
                KingdomAlliance::query()->findOrFail($canonicalKingdomAllianceId);
                throw ValidationException::withMessages(['kingdom_alliance' => 'The canonical identity no longer belongs to the expected Kingdom.']);
            }
            if ($duplicate === null) {
                KingdomAlliance::query()->findOrFail($duplicateKingdomAllianceId);
                throw ValidationException::withMessages(['kingdom_alliance' => 'Alliance identities from different Kingdoms cannot be reconciled.']);
            }
            /** @var KingdomAlliance $canonical */
            /** @var KingdomAlliance $duplicate */
            if ((string) $duplicate->canonical_kingdom_alliance_id === $canonicalKingdomAllianceId) {
                return;
            }
            if ($canonical->canonical_kingdom_alliance_id !== null || $duplicate->canonical_kingdom_alliance_id !== null) {
                throw ValidationException::withMessages([
                    'kingdom_alliance' => 'Reconciliation inputs must be direct identities. Resolve existing aliases to their canonical identity first.',
                ]);
            }
            if ((string) $canonical->kingdom_id !== (string) $duplicate->kingdom_id) {
                throw ValidationException::withMessages(['kingdom_alliance' => 'Alliance identities from different Kingdoms cannot be reconciled.']);
            }
            if ($canonical->status !== KingdomAllianceStatus::Active) {
                throw ValidationException::withMessages(['canonical_kingdom_alliance_id' => 'The canonical Alliance identity must be active.']);
            }

            if ($kingdom->status !== KingdomStatus::Active) {
                throw ValidationException::withMessages(['kingdom' => 'Alliance identities cannot be reconciled while their Kingdom is archived.']);
            }

            $canonicalStableId = $this->nullable($canonical->game_alliance_id);
            $duplicateStableId = $this->nullable($duplicate->game_alliance_id);
            if ($canonicalStableId !== null && $duplicateStableId !== null && $canonicalStableId !== $duplicateStableId) {
                throw ValidationException::withMessages(['game_alliance_id' => 'Alliance identities with conflicting stable game IDs cannot be reconciled.']);
            }

            $effectiveStableId = $canonicalStableId ?? $duplicateStableId;
            if ($canonicalStableId === null && $effectiveStableId !== null) {
                $this->history->transition(
                    $canonical,
                    (string) $canonical->current_name,
                    $this->nullable($canonical->current_tag),
                    $effectiveStableId,
                    $source,
                    $sourceReference,
                    null,
                    $confidenceBasisPoints,
                    $reason,
                );
            }

            $this->history->closeCurrent($duplicate);
            $duplicate->forceFill([
                'game_alliance_id' => null,
                'status' => KingdomAllianceStatus::Archived,
                'canonical_kingdom_alliance_id' => $canonicalKingdomAllianceId,
            ])->save();

            if ($canonicalStableId === null && $effectiveStableId !== null) {
                $canonical->forceFill(['game_alliance_id' => $effectiveStableId])->save();
            }

            $reconciliation = KingdomAllianceReconciliation::query()->create([
                'kingdom_id' => (string) $canonical->kingdom_id,
                'canonical_kingdom_alliance_id' => $canonicalKingdomAllianceId,
                'duplicate_kingdom_alliance_id' => $duplicateKingdomAllianceId,
                'reason' => $reason,
                'source_type' => $source,
                'source_reference' => $this->nullable($sourceReference),
                'confidence_basis_points' => $confidenceBasisPoints,
                'reconciled_at' => now(),
            ]);

            $this->audit->record('kingdoms.alliance_identity_reconciled', $actor, $canonical, null, [
                'reconciliation_id' => (string) $reconciliation->id,
                'kingdom_id' => (string) $canonical->kingdom_id,
                'canonical_kingdom_alliance_id' => $canonicalKingdomAllianceId,
                'duplicate_kingdom_alliance_id' => $duplicateKingdomAllianceId,
                'stable_game_alliance_id_transferred' => $canonicalStableId === null && $duplicateStableId !== null,
                'source_type' => $source->value,
                'source_reference' => $sourceReference,
                'confidence_basis_points' => $confidenceBasisPoints,
                'reason' => $reason,
            ]);
        });

        return $this->references->requireActive($canonicalKingdomAllianceId);
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
