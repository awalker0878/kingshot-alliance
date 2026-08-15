<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Shared\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Kingdoms\Enums\KingdomAllianceContactChannel;
use App\Domain\Kingdoms\Enums\KingdomAllianceContactState;
use App\Domain\Kingdoms\Enums\TrackedKingdomAllianceState;
use App\Contexts\GameWorld\Models\KingdomAlliance;
use App\Contexts\GameWorld\Models\KingdomAllianceDiplomacyContact;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveKingdomAllianceDiplomacyContact
{
    public function __construct(
        private AllianceAuthorization $authorization,
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
        Alliance $alliance,
        Player $actor,
        string $trackingId,
        array $attributes,
        ?string $contactId = null,
    ): KingdomAllianceDiplomacyContact {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::KingdomManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $trackingId, $attributes, $contactId): KingdomAllianceDiplomacyContact {
            $currentAlliance = Alliance::query()->lockForUpdate()->findOrFail($alliance->id);
            $tracking = TrackedKingdomAlliance::query()
                ->where('alliance_id', $currentAlliance->id)
                ->lockForUpdate()
                ->findOrFail($trackingId);
            $this->assertMutableContext($currentAlliance, $tracking);

            $reference = KingdomAlliance::query()->lockForUpdate()->findOrFail($tracking->kingdom_alliance_id);
            if ($reference->kingdom_id !== $tracking->kingdom_id) {
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
                    ->where('alliance_id', $currentAlliance->id)
                    ->where('tracked_kingdom_alliance_id', $tracking->id)
                    ->lockForUpdate()
                    ->findOrFail($contactId);

            if ($contact instanceof KingdomAllianceDiplomacyContact) {
                if ($contact->kingdom_alliance_id !== $reference->id) {
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
                    return $contact->load(['createdBy:id,current_name', 'updatedBy:id,current_name', 'deactivatedBy:id,current_name']);
                }

                $contact->forceFill([
                    'display_name' => $displayName,
                    'game_role' => $gameRole,
                    'channel_type' => $channel,
                    'handle' => $handle,
                    'last_verified_at' => $lastVerifiedAt,
                    'manager_notes' => $managerNotes,
                    'updated_by_player_id' => $actor->id,
                ])->save();
                $created = false;
            } else {
                $contact = KingdomAllianceDiplomacyContact::query()->create([
                    'alliance_id' => $currentAlliance->id,
                    'tracked_kingdom_alliance_id' => $tracking->id,
                    'kingdom_alliance_id' => $reference->id,
                    'display_name' => $displayName,
                    'game_role' => $gameRole,
                    'channel_type' => $channel,
                    'handle' => $handle,
                    'state' => KingdomAllianceContactState::Active,
                    'last_verified_at' => $lastVerifiedAt,
                    'manager_notes' => $managerNotes,
                    'created_by_player_id' => $actor->id,
                    'updated_by_player_id' => $actor->id,
                ]);
                $created = true;
            }

            $metadata = [
                'diplomacy_contact_id' => (string) $contact->id,
                'tracked_kingdom_alliance_id' => (string) $tracking->id,
                'kingdom_alliance_id' => (string) $reference->id,
                'state' => $contact->state->value,
                'last_verified_at' => $lastVerifiedAt?->toIso8601String(),
                'created' => $created,
            ];
            $event = 'kingdoms.diplomacy_contact_saved';
            $this->audit->record($event, $actor, $contact, $currentAlliance, $metadata);
            $this->outbox->record(
                $event,
                (string) $currentAlliance->id,
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

            return $contact->refresh()->load(['createdBy:id,current_name', 'updatedBy:id,current_name', 'deactivatedBy:id,current_name']);
        });
    }

    private function assertMutableContext(Alliance $alliance, TrackedKingdomAlliance $tracking): void
    {
        if ($tracking->state !== TrackedKingdomAllianceState::Active) {
            throw ValidationException::withMessages([
                'contact' => 'Diplomacy contacts can only change for actively tracked game-side alliances.',
            ]);
        }

        if ($alliance->kingdom_id === null || $tracking->kingdom_id !== $alliance->kingdom_id) {
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
