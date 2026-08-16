<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Diplomacy\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\KingdomAlliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Diplomacy\Enums\KingdomAllianceContactState;
use App\Contexts\Intelligence\Diplomacy\Models\KingdomAllianceDiplomacyContact;
use App\Contexts\Intelligence\Observations\Enums\TrackedKingdomAllianceState;
use App\Contexts\Intelligence\Observations\Models\TrackedKingdomAlliance;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class DeactivateKingdomAllianceDiplomacyContact
{
    public function __construct(
        private AllianceIntelligenceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Alliance $alliance,
        Player $actor,
        string $trackingId,
        string $contactId,
    ): KingdomAllianceDiplomacyContact {
        if (! $this->authorization->allows($actor, $alliance, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $trackingId, $contactId): KingdomAllianceDiplomacyContact {
            $currentAlliance = Alliance::query()->lockForUpdate()->findOrFail($alliance->id);
            $tracking = TrackedKingdomAlliance::query()
                ->where('alliance_id', $currentAlliance->id)
                ->lockForUpdate()
                ->findOrFail($trackingId);

            if ($tracking->state !== TrackedKingdomAllianceState::Active) {
                throw ValidationException::withMessages([
                    'contact' => 'Diplomacy contacts can only change for actively tracked game-side alliances.',
                ]);
            }

            if ($currentAlliance->kingdom_id === null || $tracking->kingdom_id !== $currentAlliance->kingdom_id) {
                throw ValidationException::withMessages([
                    'contact' => 'The tracked alliance belongs to historical Kingdom context. Contact history remains readable, but changes require matching current Kingdom context.',
                ]);
            }

            $reference = KingdomAlliance::query()->lockForUpdate()->findOrFail($tracking->kingdom_alliance_id);
            if ($reference->kingdom_id !== $tracking->kingdom_id) {
                throw ValidationException::withMessages([
                    'contact' => 'The tracked alliance reference no longer matches its captured Kingdom context.',
                ]);
            }

            $contact = KingdomAllianceDiplomacyContact::query()
                ->where('alliance_id', $currentAlliance->id)
                ->where('tracked_kingdom_alliance_id', $tracking->id)
                ->lockForUpdate()
                ->findOrFail($contactId);

            if ($contact->kingdom_alliance_id !== $reference->id) {
                throw ValidationException::withMessages([
                    'contact' => 'The diplomacy contact no longer matches the tracked neutral alliance reference.',
                ]);
            }

            if ($contact->state === KingdomAllianceContactState::Inactive) {
                return $contact->load(['createdBy:id,current_name', 'updatedBy:id,current_name', 'deactivatedBy:id,current_name']);
            }

            $deactivatedAt = now();
            $contact->forceFill([
                'state' => KingdomAllianceContactState::Inactive,
                'deactivated_at' => $deactivatedAt,
                'deactivated_by_player_id' => $actor->id,
                'updated_by_player_id' => $actor->id,
            ])->save();

            $metadata = [
                'diplomacy_contact_id' => (string) $contact->id,
                'tracked_kingdom_alliance_id' => (string) $tracking->id,
                'kingdom_alliance_id' => (string) $reference->id,
                'state' => KingdomAllianceContactState::Inactive->value,
                'deactivated_at' => $deactivatedAt->toIso8601String(),
            ];
            $event = 'kingdoms.diplomacy_contact_deactivated';
            $this->audit->record($event, $actor, $contact, $currentAlliance, $metadata);
            $this->outbox->record(
                $event,
                (string) $currentAlliance->id,
                $contact,
                $metadata,
                $event.':'.$contact->id,
            );

            return $contact->refresh()->load(['createdBy:id,current_name', 'updatedBy:id,current_name', 'deactivatedBy:id,current_name']);
        });
    }
}
