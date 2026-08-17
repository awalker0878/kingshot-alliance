<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Diplomacy\Actions;

use App\Contexts\GameWorld\Kingdoms\Queries\KingdomAllianceReferenceQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Diplomacy\Enums\KingdomAllianceContactState;
use App\Contexts\Intelligence\Diplomacy\Models\KingdomAllianceDiplomacyContact;
use App\Contexts\Intelligence\Observations\Enums\TrackedKingdomAllianceState;
use App\Contexts\Intelligence\Observations\Models\TrackedKingdomAlliance;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class DeactivateKingdomAllianceDiplomacyContact
{
    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private KingdomAllianceReferenceQuery $kingdomAlliances,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $allianceId,
        string $actorPlayerId,
        string $trackingId,
        string $contactId,
    ): string {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $trackingId, $contactId): string {
            [$scope, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);
            $tracking = TrackedKingdomAlliance::query()
                ->where('alliance_id', $allianceId)
                ->lockForUpdate()
                ->findOrFail($trackingId);

            if ($tracking->state !== TrackedKingdomAllianceState::Active) {
                throw ValidationException::withMessages([
                    'contact' => 'Diplomacy contacts can only change for actively tracked game-side alliances.',
                ]);
            }

            if ((string) $tracking->kingdom_id !== $scope->kingdomId) {
                throw ValidationException::withMessages([
                    'contact' => 'The tracked alliance belongs to historical Kingdom context. Contact history remains readable, but changes require matching current Kingdom context.',
                ]);
            }

            $reference = $this->kingdomAlliances->require((string) $tracking->kingdom_alliance_id);
            if ($reference->kingdomId !== (string) $tracking->kingdom_id) {
                throw ValidationException::withMessages([
                    'contact' => 'The tracked alliance reference no longer matches its captured Kingdom context.',
                ]);
            }

            $contact = KingdomAllianceDiplomacyContact::query()
                ->where('alliance_id', $allianceId)
                ->where('tracked_kingdom_alliance_id', $tracking->id)
                ->lockForUpdate()
                ->findOrFail($contactId);

            if ($contact->kingdom_alliance_id !== $reference->kingdomAllianceId) {
                throw ValidationException::withMessages([
                    'contact' => 'The diplomacy contact no longer matches the tracked neutral alliance reference.',
                ]);
            }

            if ($contact->state === KingdomAllianceContactState::Inactive) {
                return (string) $contact->id;
            }

            $deactivatedAt = now();
            $contact->forceFill([
                'state' => KingdomAllianceContactState::Inactive,
                'deactivated_at' => $deactivatedAt,
                'deactivated_by_player_id' => $actor->playerId,
                'updated_by_player_id' => $actor->playerId,
            ])->save();

            $metadata = [
                'diplomacy_contact_id' => (string) $contact->id,
                'tracked_kingdom_alliance_id' => (string) $tracking->id,
                'kingdom_alliance_id' => (string) $reference->kingdomAllianceId,
                'state' => KingdomAllianceContactState::Inactive->value,
                'deactivated_at' => $deactivatedAt->toIso8601String(),
            ];
            $event = 'kingdoms.diplomacy_contact_deactivated';
            $this->audit->record($event, $actor, $contact, $allianceId, $metadata);
            $this->outbox->record(
                $event,
                $allianceId,
                $contact,
                $metadata,
                $event.':'.$contact->id,
            );

            return (string) $contact->id;
        });
    }
}
