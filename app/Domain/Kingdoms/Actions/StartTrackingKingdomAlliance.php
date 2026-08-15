<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Enums\KingdomAllianceStatus;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Kingdoms\Enums\TrackedKingdomAllianceState;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class StartTrackingKingdomAlliance
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private ResolveKingdomAlliance $alliances,
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
    public function handle(Alliance $alliance, Player $actor, array $attributes): TrackedKingdomAlliance
    {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::KingdomManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $attributes): TrackedKingdomAlliance {
            $lockedAlliance = Alliance::query()->lockForUpdate()->findOrFail($alliance->id);

            if ($lockedAlliance->kingdom_id === null) {
                throw ValidationException::withMessages([
                    'tracking' => 'The alliance must have a current Kingdom before game-side alliances can be tracked.',
                ]);
            }

            $reference = $this->alliances->handle(
                $lockedAlliance,
                $attributes['current_name'],
                $attributes['current_tag'] ?? null,
                $attributes['game_alliance_id'] ?? null,
            );

            if ($reference->kingdom_id !== $lockedAlliance->kingdom_id) {
                throw ValidationException::withMessages([
                    'tracking' => 'The game-side alliance must belong to the active alliance current Kingdom.',
                ]);
            }

            if ($reference->status !== KingdomAllianceStatus::Active) {
                throw ValidationException::withMessages([
                    'tracking' => 'Archived game-side alliance references cannot be newly tracked.',
                ]);
            }

            $alreadyTracked = TrackedKingdomAlliance::query()
                ->where('alliance_id', $lockedAlliance->id)
                ->where('kingdom_alliance_id', $reference->id)
                ->where('state', TrackedKingdomAllianceState::Active->value)
                ->lockForUpdate()
                ->first();

            if ($alreadyTracked instanceof TrackedKingdomAlliance) {
                throw ValidationException::withMessages([
                    'tracking' => 'That game-side alliance is already actively tracked.',
                ]);
            }

            $tracking = TrackedKingdomAlliance::query()->create([
                'alliance_id' => $lockedAlliance->id,
                'kingdom_alliance_id' => $reference->id,
                'kingdom_id' => $lockedAlliance->kingdom_id,
                'state' => TrackedKingdomAllianceState::Active,
                'manager_notes' => $this->nullableText($attributes['manager_notes'] ?? null),
            ]);

            $metadata = [
                'tracked_kingdom_alliance_id' => (string) $tracking->id,
                'kingdom_alliance_id' => (string) $reference->id,
                'kingdom_id' => (string) $tracking->kingdom_id,
                'state' => $tracking->state->value,
                'stable_identity' => $reference->game_alliance_id !== null,
            ];

            $this->audit->record(
                'kingdoms.alliance_intelligence_tracking_started',
                $actor,
                $tracking,
                $lockedAlliance,
                $metadata,
            );
            $this->outbox->record(
                'kingdoms.alliance_intelligence_tracking_started',
                (string) $lockedAlliance->id,
                $tracking,
                $metadata,
            );

            return $tracking->refresh()->load(['kingdomAlliance', 'kingdom']);
        });
    }

    private function nullableText(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
