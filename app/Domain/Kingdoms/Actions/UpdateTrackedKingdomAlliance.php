<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\KingdomAlliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Observations\Enums\TrackedKingdomAllianceState;
use App\Contexts\Intelligence\Observations\Models\TrackedKingdomAlliance;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateTrackedKingdomAlliance
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param array{
     *   current_name: string,
     *   current_tag?: string|null,
     *   game_alliance_id?: string|null,
     *   manager_notes?: string|null
     * } $attributes
     */
    public function handle(
        Alliance $alliance,
        Player $actor,
        string $trackingId,
        array $attributes,
    ): TrackedKingdomAlliance {
        if (! $this->authorization->allows($actor, $alliance, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $trackingId, $attributes): TrackedKingdomAlliance {
            $lockedAlliance = Alliance::query()->lockForUpdate()->findOrFail($alliance->id);
            $tracking = TrackedKingdomAlliance::query()
                ->where('alliance_id', $lockedAlliance->id)
                ->lockForUpdate()
                ->findOrFail($trackingId);

            if ($tracking->state !== TrackedKingdomAllianceState::Active) {
                throw ValidationException::withMessages([
                    'tracking' => 'Archived tracking records cannot be edited.',
                ]);
            }

            $this->assertCurrentKingdom($lockedAlliance, $tracking);

            $reference = KingdomAlliance::query()->lockForUpdate()->findOrFail($tracking->kingdom_alliance_id);
            if ($reference->kingdom_id !== $tracking->kingdom_id) {
                throw ValidationException::withMessages([
                    'tracking' => 'The tracked alliance identity no longer matches its captured Kingdom context.',
                ]);
            }

            $name = trim($attributes['current_name']);
            if ($name === '') {
                throw ValidationException::withMessages([
                    'current_name' => 'Alliance name is required.',
                ]);
            }

            $tag = array_key_exists('current_tag', $attributes)
                ? $this->nullableLine($attributes['current_tag'])
                : $reference->current_tag;
            $existingStableId = $reference->game_alliance_id;
            $stableId = array_key_exists('game_alliance_id', $attributes)
                ? $this->nullableLine($attributes['game_alliance_id'])
                : $existingStableId;

            if ($existingStableId !== null && $stableId !== $existingStableId) {
                throw ValidationException::withMessages([
                    'game_alliance_id' => 'A stable game alliance ID cannot be cleared or changed in place.',
                ]);
            }

            if ($existingStableId === null && $stableId !== null) {
                $conflict = KingdomAlliance::query()
                    ->where('kingdom_id', $reference->kingdom_id)
                    ->where('game_alliance_id', $stableId)
                    ->where('id', '<>', $reference->id)
                    ->exists();

                if ($conflict) {
                    throw ValidationException::withMessages([
                        'game_alliance_id' => 'That stable game alliance ID already belongs to another neutral alliance reference.',
                    ]);
                }
            }

            $managerNotes = array_key_exists('manager_notes', $attributes)
                ? $this->nullableText($attributes['manager_notes'])
                : $tracking->manager_notes;
            $identityChanged = $reference->current_name !== $name
                || $reference->current_tag !== $tag
                || $reference->game_alliance_id !== $stableId;
            $notesChanged = $tracking->manager_notes !== $managerNotes;

            if (! $identityChanged && ! $notesChanged) {
                return $tracking->load(['kingdomAlliance', 'kingdom']);
            }

            if ($identityChanged) {
                $reference->forceFill([
                    'current_name' => $name,
                    'current_tag' => $tag,
                    'game_alliance_id' => $stableId,
                ])->save();
            }

            if ($notesChanged) {
                $tracking->forceFill(['manager_notes' => $managerNotes])->save();
            }

            $metadata = [
                'tracked_kingdom_alliance_id' => (string) $tracking->id,
                'kingdom_alliance_id' => (string) $reference->id,
                'kingdom_id' => (string) $tracking->kingdom_id,
                'identity_changed' => $identityChanged,
                'stable_identity_assigned' => $existingStableId === null && $stableId !== null,
                'manager_notes_changed' => $notesChanged,
            ];

            $this->audit->record(
                'kingdoms.alliance_intelligence_tracking_updated',
                $actor,
                $tracking,
                $lockedAlliance,
                $metadata,
            );
            $this->outbox->record(
                'kingdoms.alliance_intelligence_tracking_updated',
                (string) $lockedAlliance->id,
                $tracking,
                $metadata,
            );

            return $tracking->refresh()->load(['kingdomAlliance', 'kingdom']);
        });
    }

    private function assertCurrentKingdom(Alliance $alliance, TrackedKingdomAlliance $tracking): void
    {
        if ($alliance->kingdom_id === null || $alliance->kingdom_id !== $tracking->kingdom_id) {
            throw ValidationException::withMessages([
                'tracking' => 'This tracking record belongs to an earlier Kingdom context and cannot be edited.',
            ]);
        }
    }

    private function nullableLine(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    private function nullableText(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
