<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Observations\Actions;

use App\Contexts\GameWorld\Kingdoms\Actions\ResolveKingdomAlliance;
use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceStatus;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Observations\Enums\TrackedKingdomAllianceState;
use App\Contexts\Intelligence\Observations\Models\TrackedKingdomAlliance;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class StartTrackingKingdomAlliance
{
    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private ResolveKingdomAlliance $alliances,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param  array{current_name:string,current_tag?:string|null,game_alliance_id?:string|null,manager_notes?:string|null}  $attributes
     */
    public function handle(string $allianceId, string $actorPlayerId, array $attributes): string
    {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $attributes): string {
            [$scope, $actor] = $this->writeState->authorize(
                $actorPlayerId,
                $allianceId,
                IntelligencePermission::KingdomManage,
            );

            $reference = $this->alliances->handle(
                $scope->kingdomId,
                $attributes['current_name'],
                $attributes['current_tag'] ?? null,
                $attributes['game_alliance_id'] ?? null,
            );

            if ($reference->kingdomId !== $scope->kingdomId) {
                throw ValidationException::withMessages([
                    'tracking' => 'The game-side alliance must belong to the active alliance current Kingdom.',
                ]);
            }
            if ($reference->statusObservedAtRead !== KingdomAllianceStatus::Active) {
                throw ValidationException::withMessages([
                    'tracking' => 'Archived game-side alliance references cannot be newly tracked.',
                ]);
            }

            $alreadyTracked = TrackedKingdomAlliance::query()
                ->where('alliance_id', $allianceId)
                ->where('kingdom_alliance_id', $reference->kingdomAllianceId)
                ->where('state', TrackedKingdomAllianceState::Active->value)
                ->lockForUpdate()
                ->first();
            if ($alreadyTracked instanceof TrackedKingdomAlliance) {
                throw ValidationException::withMessages(['tracking' => 'That game-side alliance is already actively tracked.']);
            }

            $tracking = TrackedKingdomAlliance::query()->create([
                'alliance_id' => $allianceId,
                'kingdom_alliance_id' => $reference->kingdomAllianceId,
                'kingdom_id' => $scope->kingdomId,
                'state' => TrackedKingdomAllianceState::Active,
                'manager_notes' => $this->nullableText($attributes['manager_notes'] ?? null),
            ]);

            $metadata = [
                'tracked_kingdom_alliance_id' => (string) $tracking->id,
                'kingdom_alliance_id' => $reference->kingdomAllianceId,
                'kingdom_id' => $scope->kingdomId,
                'state' => $tracking->state->value,
                'stable_identity' => $reference->gameAllianceId !== null,
            ];
            $this->audit->record('kingdoms.alliance_intelligence_tracking_started', $actor, $tracking, $allianceId, $metadata);
            $this->outbox->record('kingdoms.alliance_intelligence_tracking_started', $allianceId, $tracking, $metadata);

            return (string) $tracking->id;
        });
    }

    private function nullableText(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
