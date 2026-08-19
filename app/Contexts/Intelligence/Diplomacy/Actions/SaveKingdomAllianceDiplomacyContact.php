<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Diplomacy\Actions;

use App\Contexts\GameWorld\Kingdoms\Queries\KingdomAllianceReferenceQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Diplomacy\Enums\KingdomAllianceContactChannel;
use App\Contexts\Intelligence\Diplomacy\Enums\KingdomAllianceContactState;
use App\Contexts\Intelligence\Diplomacy\Models\KingdomAllianceDiplomacyContact;
use App\Contexts\Intelligence\Observations\Enums\TrackedKingdomAllianceState;
use App\Contexts\Intelligence\Observations\Models\TrackedKingdomAlliance;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveKingdomAllianceDiplomacyContact
{
    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private KingdomAllianceReferenceQuery $kingdomAlliances,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param array{
     *   display_name: string,
     *   game_role?: string|null,
     *   channel_type: string,
     *   handle: string,
     *   last_verified_at?: string|null,
     *   manager_notes?: string|null
     * } $attributes
     */
    public function handle(
        string $allianceId,
        string $actorPlayerId,
        string $trackingId,
        array $attributes,
        ?string $contactId = null,
    ): string {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $trackingId, $attributes, $contactId): string {
            [$scope, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);
            $tracking = TrackedKingdomAlliance::query()
                ->where('alliance_id', $allianceId)
                ->lockForUpdate()
                ->findOrFail($trackingId);
            $this->assertMutableContext($scope->kingdomId, $tracking);

            $reference = $this->kingdomAlliances->require((string) $tracking->kingdom_alliance_id);
            if ($reference->kingdomId !== (string) $tracking->kingdom_id) {
                throw ValidationException::withMessages([
                    'contact' => 'The tracked alliance reference no longer matches its captured Kingdom context.',
                ]);
            }

            $displayName = trim($attributes['display_name']);
            $gameRole = $this->nullableText($attributes['game_role'] ?? null);
            $channel = KingdomAllianceContactChannel::from($attributes['channel_type']);
            $handle = trim($attributes['handle']);
            $lastVerifiedAt = $this->optionalDate($attributes['last_verified_at'] ?? null);
            $managerNotes = $this->nullableText($attributes['manager_notes'] ?? null);

            $contact = $contactId === null
                ? null
                : KingdomAllianceDiplomacyContact::query()
                    ->where('alliance_id', $allianceId)
                    ->where('tracked_kingdom_alliance_id', $tracking->id)
                    ->lockForUpdate()
                    ->findOrFail($contactId);

            if ($contact instanceof KingdomAllianceDiplomacyContact) {
                if ($contact->kingdom_alliance_id !== $reference->kingdomAllianceId) {
                    throw ValidationException::withMessages([
                        'contact' => 'The diplomacy contact no longer matches the tracked neutral alliance reference.',
                    ]);
                }

                if ($contact->state !== KingdomAllianceContactState::Active) {
                    throw ValidationException::withMessages([
                        'contact' => 'Inactive diplomacy contacts are preserved as history and cannot be edited. Create a new active contact if coordination resumes.',
                    ]);
                }

                if ($contact->display_name === $displayName
                    && $contact->game_role === $gameRole
                    && $contact->channel_type === $channel
                    && $contact->handle === $handle
                    && $this->sameDate($contact->last_verified_at, $lastVerifiedAt)
                    && $contact->manager_notes === $managerNotes) {
                    return (string) $contact->id;
                }

                $contact->forceFill([
                    'display_name' => $displayName,
                    'game_role' => $gameRole,
                    'channel_type' => $channel,
                    'handle' => $handle,
                    'last_verified_at' => $lastVerifiedAt,
                    'manager_notes' => $managerNotes,
                    'updated_by_player_id' => $actor->playerId,
                ])->save();
                $created = false;
            } else {
                $contact = KingdomAllianceDiplomacyContact::query()->create([
                    'alliance_id' => $allianceId,
                    'tracked_kingdom_alliance_id' => $tracking->id,
                    'kingdom_alliance_id' => $reference->kingdomAllianceId,
                    'display_name' => $displayName,
                    'game_role' => $gameRole,
                    'channel_type' => $channel,
                    'handle' => $handle,
                    'state' => KingdomAllianceContactState::Active,
                    'last_verified_at' => $lastVerifiedAt,
                    'manager_notes' => $managerNotes,
                    'created_by_player_id' => $actor->playerId,
                    'updated_by_player_id' => $actor->playerId,
                ]);
                $created = true;
            }

            $metadata = [
                'diplomacy_contact_id' => (string) $contact->id,
                'tracked_kingdom_alliance_id' => (string) $tracking->id,
                'kingdom_alliance_id' => (string) $reference->kingdomAllianceId,
                'state' => $contact->state->value,
                'last_verified_at' => $lastVerifiedAt?->toIso8601String(),
                'created' => $created,
            ];
            $event = 'kingdoms.diplomacy_contact_saved';
            $this->audit->record($event, $actor, $contact, $allianceId, $metadata);
            $this->outbox->record(
                $event,
                $allianceId,
                $contact,
                $metadata,
                $event.':'.hash('sha256', implode('|', [
                    (string) $contact->id,
                    $displayName,
                    $gameRole ?? '',
                    $channel->value,
                    $handle,
                    $lastVerifiedAt?->toIso8601String() ?? '',
                    $managerNotes ?? '',
                ])),
            );

            return (string) $contact->id;
        });
    }

    private function assertMutableContext(string $kingdomId, TrackedKingdomAlliance $tracking): void
    {
        if ($tracking->state !== TrackedKingdomAllianceState::Active) {
            throw ValidationException::withMessages([
                'contact' => 'Diplomacy contacts can only change for actively tracked game-side alliances.',
            ]);
        }

        if ((string) $tracking->kingdom_id !== $kingdomId) {
            throw ValidationException::withMessages([
                'contact' => 'The tracked alliance belongs to historical Kingdom context. Contact history remains readable, but changes require matching current Kingdom context.',
            ]);
        }
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
