<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Jobs\ClassifyGameEvidenceJob;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RetryTerritorySpatialEvidenceProcessing
{
    public function __construct(private AllianceIntelligenceWriteState $writeState) {}

    public function handle(string $actorPlayerId, string $allianceId, string $kingdomId, string $evidenceId): void
    {
        DB::transaction(function () use ($actorPlayerId, $allianceId, $kingdomId, $evidenceId): void {
            [$scope] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);
            if ($scope->kingdomId !== $kingdomId) {
                throw ValidationException::withMessages(['kingdom_id' => 'The Territory evidence belongs to historical Kingdom context.']);
            }
            $evidence = GameEvidence::query()->whereKey($evidenceId)->where('alliance_id', $allianceId)->where('kingdom_id', $kingdomId)->lockForUpdate()->firstOrFail();
            if ($evidence->expected_kind !== EvidenceKind::TerritoryMapObservation || in_array($evidence->lifecycle_status, [EvidenceLifecycleStatus::Deleted, EvidenceLifecycleStatus::Committed, EvidenceLifecycleStatus::Committing], true)) {
                throw ValidationException::withMessages(['evidence' => 'This Territory evidence cannot be reprocessed in its current state.']);
            }
            $evidence->forceFill(['kind' => EvidenceKind::Unknown, 'lifecycle_status' => EvidenceLifecycleStatus::Uploaded])->save();
        });
        ClassifyGameEvidenceJob::dispatch($evidenceId);
    }
}
