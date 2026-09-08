<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\Actions;

use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceIdentitySource;
use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceStatus;
use App\Contexts\GameWorld\Kingdoms\Enums\KingdomStatus;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Kingdoms\Models\KingdomAlliance;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomAllianceReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\Services\KingdomAllianceIdentityHistoryRecorder;
use App\Contexts\GameWorld\Kingdoms\ValueObjects\KingdomAllianceReference;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateKingdomAllianceIdentity
{
    public function __construct(
        private KingdomAllianceReferenceQuery $references,
        private KingdomAllianceIdentityHistoryRecorder $history,
        private AuditRecorder $audit,
    ) {}

    public function handle(
        string $kingdomAllianceId,
        string $expectedKingdomId,
        string $currentName,
        ?string $currentTag,
        ?string $gameAllianceId = null,
        KingdomAllianceIdentitySource $source = KingdomAllianceIdentitySource::Manual,
        ?string $sourceReference = null,
        ?DateTimeInterface $observedAt = null,
        ?int $confidenceBasisPoints = null,
        ?AuditActor $actor = null,
        ?string $reason = null,
    ): KingdomAllianceReference {
        $name = trim($currentName);
        if ($name === '') {
            throw ValidationException::withMessages(['current_name' => 'Alliance name is required.']);
        }

        $tag = $this->nullable($currentTag);
        $stableId = $this->nullable($gameAllianceId);
        $canonicalReference = $this->references->requireCanonical($kingdomAllianceId);
        $targetKingdomAllianceId = $canonicalReference->kingdomAllianceId;

        DB::transaction(function () use (
            $targetKingdomAllianceId,
            $expectedKingdomId,
            $name,
            $tag,
            $stableId,
            $source,
            $sourceReference,
            $observedAt,
            $confidenceBasisPoints,
            $actor,
            $reason,
        ): void {
            $alliance = KingdomAlliance::query()->whereKey($targetKingdomAllianceId)->lockForUpdate()->firstOrFail();
            if ((string) $alliance->kingdom_id !== $expectedKingdomId) {
                throw ValidationException::withMessages(['kingdom_alliance' => 'The neutral alliance no longer belongs to the expected Kingdom.']);
            }
            if ($alliance->status !== KingdomAllianceStatus::Active || $alliance->canonical_kingdom_alliance_id !== null) {
                throw ValidationException::withMessages(['kingdom_alliance' => 'Archived or reconciled Alliance identities cannot be mutated. Resolve the active canonical identity first.']);
            }

            $kingdom = Kingdom::query()->whereKey($expectedKingdomId)->lockForUpdate()->firstOrFail();
            if ($kingdom->status !== KingdomStatus::Active) {
                throw ValidationException::withMessages(['kingdom' => 'Alliance identity cannot change while its Kingdom is archived.']);
            }

            $existingStableId = $alliance->game_alliance_id === null ? null : (string) $alliance->game_alliance_id;
            if ($existingStableId !== null && $stableId !== null && $stableId !== $existingStableId) {
                throw ValidationException::withMessages(['game_alliance_id' => 'A stable game Alliance ID cannot be changed in place.']);
            }

            $effectiveStableId = $existingStableId ?? $stableId;
            if ($effectiveStableId !== null) {
                $conflict = KingdomAlliance::query()
                    ->where('kingdom_id', $expectedKingdomId)
                    ->where('game_alliance_id', $effectiveStableId)
                    ->whereKeyNot($targetKingdomAllianceId)
                    ->first();
                if ($conflict instanceof KingdomAlliance) {
                    throw ValidationException::withMessages([
                        'game_alliance_id' => 'That stable game Alliance ID belongs to another neutral identity. Reconcile the identities explicitly instead of overwriting either record.',
                    ]);
                }
            }

            $changed = $this->history->transition(
                $alliance,
                $name,
                $tag,
                $effectiveStableId,
                $source,
                $sourceReference,
                $observedAt,
                $confidenceBasisPoints,
                $reason,
            );
            if (! $changed) {
                return;
            }

            $before = [
                'current_name' => (string) $alliance->current_name,
                'current_tag' => $alliance->current_tag,
                'game_alliance_id' => $alliance->game_alliance_id,
            ];
            $alliance->forceFill([
                'current_name' => $name,
                'current_tag' => $tag,
                'game_alliance_id' => $effectiveStableId,
            ])->save();

            $this->audit->record('kingdoms.alliance_identity_updated', $actor, $alliance, null, [
                'kingdom_id' => $expectedKingdomId,
                'before' => $before,
                'after' => [
                    'current_name' => $name,
                    'current_tag' => $tag,
                    'game_alliance_id' => $effectiveStableId,
                ],
                'source_type' => $source->value,
                'source_reference' => $sourceReference,
                'observed_at' => $observedAt?->format(DATE_ATOM),
                'confidence_basis_points' => $confidenceBasisPoints,
                'reason' => $reason,
            ]);
        });

        return $this->references->requireActive($targetKingdomAllianceId);
    }

    private function nullable(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
