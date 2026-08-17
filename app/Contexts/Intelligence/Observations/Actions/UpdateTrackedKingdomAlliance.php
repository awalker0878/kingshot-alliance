<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Observations\Actions;

use App\Contexts\GameWorld\Kingdoms\Actions\UpdateKingdomAllianceIdentity;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomAllianceReferenceQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Observations\Enums\TrackedKingdomAllianceState;
use App\Contexts\Intelligence\Observations\Models\TrackedKingdomAlliance;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateTrackedKingdomAlliance
{
    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private KingdomAllianceReferenceQuery $references,
        private UpdateKingdomAllianceIdentity $updateIdentity,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param array{current_name:string,current_tag?:string|null,game_alliance_id?:string|null,manager_notes?:string|null} $attributes */
    public function handle(string $allianceId, string $actorPlayerId, string $trackingId, array $attributes): string
    {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $trackingId, $attributes): string {
            [$scope, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);

            $tracking = TrackedKingdomAlliance::query()
                ->where('alliance_id', $allianceId)
                ->lockForUpdate()
                ->findOrFail($trackingId);
            if ($tracking->state !== TrackedKingdomAllianceState::Active) {
                throw ValidationException::withMessages(['tracking' => 'Archived tracking records cannot be edited.']);
            }
            if ((string) $tracking->kingdom_id !== $scope->kingdomId) {
                throw ValidationException::withMessages(['tracking' => 'This tracking record belongs to an earlier Kingdom context and cannot be edited.']);
            }

            $reference = $this->references->require((string) $tracking->kingdom_alliance_id);
            if ($reference->kingdomId !== (string) $tracking->kingdom_id) {
                throw ValidationException::withMessages(['tracking' => 'The tracked alliance identity no longer matches its captured Kingdom context.']);
            }

            $name = trim($attributes['current_name']);
            if ($name === '') {
                throw ValidationException::withMessages(['current_name' => 'Alliance name is required.']);
            }
            $tag = array_key_exists('current_tag', $attributes) ? $this->nullableLine($attributes['current_tag']) : $reference->currentTag;
            $stableId = array_key_exists('game_alliance_id', $attributes) ? $this->nullableLine($attributes['game_alliance_id']) : $reference->gameAllianceId;
            $managerNotes = array_key_exists('manager_notes', $attributes) ? $this->nullableText($attributes['manager_notes']) : $tracking->manager_notes;

            $identityChanged = $reference->currentName !== $name || $reference->currentTag !== $tag || $reference->gameAllianceId !== $stableId;
            $notesChanged = $tracking->manager_notes !== $managerNotes;
            if (! $identityChanged && ! $notesChanged) {
                return (string) $tracking->id;
            }

            if ($identityChanged) {
                $updated = $this->updateIdentity->handle(
                    $reference->kingdomAllianceId,
                    $scope->kingdomId,
                    $name,
                    $tag,
                    $stableId,
                );
            } else {
                $updated = $reference;
            }
            if ($notesChanged) {
                $tracking->forceFill(['manager_notes' => $managerNotes])->save();
            }

            $metadata = [
                'tracked_kingdom_alliance_id' => (string) $tracking->id,
                'kingdom_alliance_id' => $updated->kingdomAllianceId,
                'kingdom_id' => (string) $tracking->kingdom_id,
                'identity_changed' => $identityChanged,
                'stable_identity_assigned' => $reference->gameAllianceId === null && $updated->gameAllianceId !== null,
                'manager_notes_changed' => $notesChanged,
            ];
            $this->audit->record('kingdoms.alliance_intelligence_tracking_updated', $actor, $tracking, $allianceId, $metadata);
            $this->outbox->record('kingdoms.alliance_intelligence_tracking_updated', $allianceId, $tracking, $metadata);

            return (string) $tracking->id;
        });
    }

    private function nullableLine(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);
        return $value === '' ? null : $value;
    }

    private function nullableText(?string $value): ?string
    {
        return $this->nullableLine($value);
    }
}
