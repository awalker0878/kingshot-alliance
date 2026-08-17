<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Diplomacy\Actions;

use App\Contexts\GameWorld\Kingdoms\Queries\KingdomAllianceReferenceQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Diplomacy\Enums\KingdomAllianceDiplomacyState;
use App\Contexts\Intelligence\Diplomacy\Models\KingdomAllianceDiplomacy;
use App\Contexts\Intelligence\Diplomacy\Models\KingdomAllianceDiplomacyTransition;
use App\Contexts\Intelligence\Observations\Enums\TrackedKingdomAllianceState;
use App\Contexts\Intelligence\Observations\Models\TrackedKingdomAlliance;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class TransitionKingdomAllianceDiplomacy
{
    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private KingdomAllianceReferenceQuery $kingdomAlliances,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param array{
     *   effective_at: string,
     *   review_at?: string|null,
     *   expires_at?: string|null,
     *   terms?: string|null,
     *   rationale?: string|null
     * } $attributes
     */
    public function handle(
        string $allianceId,
        string $actorPlayerId,
        string $trackingId,
        KingdomAllianceDiplomacyState $target,
        array $attributes,
    ): string {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $trackingId, $target, $attributes): string {
            [$scope, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);
            $tracking = TrackedKingdomAlliance::query()
                ->where('alliance_id', $allianceId)
                ->lockForUpdate()
                ->findOrFail($trackingId);

            if ($tracking->state !== TrackedKingdomAllianceState::Active) {
                throw ValidationException::withMessages([
                    'diplomacy' => 'Diplomacy can only change for actively tracked game-side alliances.',
                ]);
            }

            if ((string) $tracking->kingdom_id !== $scope->kingdomId) {
                throw ValidationException::withMessages([
                    'diplomacy' => 'The tracked alliance belongs to historical Kingdom context. Diplomacy history remains readable, but changes require matching current Kingdom context.',
                ]);
            }

            $reference = $this->kingdomAlliances->require((string) $tracking->kingdom_alliance_id);
            if ($reference->kingdomId !== (string) $tracking->kingdom_id) {
                throw ValidationException::withMessages([
                    'diplomacy' => 'The tracked alliance reference no longer matches its captured Kingdom context.',
                ]);
            }

            $effectiveAt = Carbon::parse($attributes['effective_at'])->utc();
            $reviewAt = $this->optionalDate($attributes['review_at'] ?? null);
            $expiresAt = $this->optionalDate($attributes['expires_at'] ?? null);
            $terms = $this->nullableText($attributes['terms'] ?? null);
            $rationale = $this->nullableText($attributes['rationale'] ?? null);

            if ($reviewAt !== null && $reviewAt->lt($effectiveAt)) {
                throw ValidationException::withMessages([
                    'review_at' => 'Review time cannot be earlier than the relationship effective time.',
                ]);
            }

            if ($expiresAt !== null && $expiresAt->lt($effectiveAt)) {
                throw ValidationException::withMessages([
                    'expires_at' => 'Expiry time cannot be earlier than the relationship effective time.',
                ]);
            }

            if ($reviewAt !== null && $expiresAt !== null && $reviewAt->gt($expiresAt)) {
                throw ValidationException::withMessages([
                    'review_at' => 'Review time cannot be later than the relationship expiry time.',
                ]);
            }

            $relationship = KingdomAllianceDiplomacy::query()
                ->where('alliance_id', $allianceId)
                ->where('tracked_kingdom_alliance_id', $tracking->id)
                ->lockForUpdate()
                ->first();
            $from = $relationship instanceof KingdomAllianceDiplomacy
                ? $relationship->current_state
                : KingdomAllianceDiplomacyState::Unknown;

            if ($relationship instanceof KingdomAllianceDiplomacy
                && $relationship->current_state === $target
                && $relationship->effective_at->equalTo($effectiveAt)
                && $this->sameDate($relationship->review_at, $reviewAt)
                && $this->sameDate($relationship->expires_at, $expiresAt)
                && $relationship->terms === $terms
                && $relationship->rationale === $rationale) {
                return (string) $relationship->id;
            }

            if (! $relationship instanceof KingdomAllianceDiplomacy) {
                $relationship = KingdomAllianceDiplomacy::query()->create([
                    'alliance_id' => $currentAlliance->id,
                    'tracked_kingdom_alliance_id' => $tracking->id,
                    'kingdom_alliance_id' => $reference->kingdomAllianceId,
                    'current_state' => $target,
                    'effective_at' => $effectiveAt,
                    'review_at' => $reviewAt,
                    'expires_at' => $expiresAt,
                    'terms' => $terms,
                    'rationale' => $rationale,
                    'last_transition_player_id' => $actor->playerId,
                ]);
            } else {
                if ($relationship->kingdom_alliance_id !== $reference->kingdomAllianceId) {
                    throw ValidationException::withMessages([
                        'diplomacy' => 'The diplomacy relationship no longer matches the tracked neutral alliance reference.',
                    ]);
                }

                $relationship->forceFill([
                    'current_state' => $target,
                    'effective_at' => $effectiveAt,
                    'review_at' => $reviewAt,
                    'expires_at' => $expiresAt,
                    'terms' => $terms,
                    'rationale' => $rationale,
                    'last_transition_player_id' => $actor->playerId,
                ])->save();
            }

            $transition = KingdomAllianceDiplomacyTransition::query()->create([
                'alliance_id' => $currentAlliance->id,
                'diplomacy_relationship_id' => $relationship->id,
                'tracked_kingdom_alliance_id' => $tracking->id,
                'kingdom_alliance_id' => $reference->kingdomAllianceId,
                'from_state' => $from,
                'to_state' => $target,
                'effective_at' => $effectiveAt,
                'review_at' => $reviewAt,
                'expires_at' => $expiresAt,
                'terms' => $terms,
                'rationale' => $rationale,
                'actor_player_id' => $actor->playerId,
                'created_at' => now(),
            ]);

            $metadata = [
                'diplomacy_relationship_id' => (string) $relationship->id,
                'diplomacy_transition_id' => (string) $transition->id,
                'tracked_kingdom_alliance_id' => (string) $tracking->id,
                'kingdom_alliance_id' => (string) $reference->kingdomAllianceId,
                'from_state' => $from->value,
                'to_state' => $target->value,
                'effective_at' => $effectiveAt->toIso8601String(),
                'review_at' => $reviewAt?->toIso8601String(),
                'expires_at' => $expiresAt?->toIso8601String(),
            ];
            $event = 'kingdoms.diplomacy_transitioned';
            $this->audit->record($event, $actor, $relationship, $allianceId, $metadata);
            $this->outbox->record(
                $event,
                $allianceId,
                $relationship,
                $metadata,
                $event.':'.$transition->id,
            );

            return (string) $relationship->id;
        });
    }

    private function optionalDate(?string $value): ?Carbon
    {
        $value = $value === null ? null : trim($value);

        return $value === null || $value === '' ? null : Carbon::parse($value)->utc();
    }

    private function nullableText(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    private function sameDate(?Carbon $left, ?Carbon $right): bool
    {
        if ($left === null || $right === null) {
            return $left === null && $right === null;
        }

        return $left->equalTo($right);
    }
}
