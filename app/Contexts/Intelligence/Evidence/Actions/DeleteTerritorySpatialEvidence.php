<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final readonly class DeleteTerritorySpatialEvidence
{
    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private AuditRecorder $audit,
    ) {
    }

    public function handle(
        string $actorPlayerId,
        string $allianceId,
        string $kingdomId,
        string $evidenceId,
        string $reason = 'user_requested',
    ): void {
        $storage = DB::transaction(function () use ($actorPlayerId, $allianceId, $kingdomId, $evidenceId, $reason): ?array {
            [$scope, $actor] = $this->writeState->authorize(
                $actorPlayerId,
                $allianceId,
                IntelligencePermission::KingdomManage,
            );
            if ($scope->kingdomId !== $kingdomId) {
                throw ValidationException::withMessages([
                    'kingdom_id' => 'The Territory evidence belongs to historical Kingdom context.',
                ]);
            }

            $evidence = GameEvidence::query()
                ->whereKey($evidenceId)
                ->where('alliance_id', $allianceId)
                ->where('kingdom_id', $kingdomId)
                ->lockForUpdate()
                ->firstOrFail();
            if ($evidence->expected_kind !== EvidenceKind::TerritoryMapObservation) {
                throw ValidationException::withMessages([
                    'evidence' => 'The selected Evidence is not Territory spatial evidence.',
                ]);
            }
            if ($evidence->lifecycle_status === EvidenceLifecycleStatus::Deleted) {
                return null;
            }

            $storage = $evidence->path === null
                ? null
                : ['disk' => (string) $evidence->disk, 'path' => (string) $evidence->path];
            $evidence->forceFill([
                'lifecycle_status' => EvidenceLifecycleStatus::Deleted,
                'path' => null,
                'binary_deleted_at' => now(),
                'deletion_reason' => mb_substr(trim($reason), 0, 1000),
            ])->save();
            $this->audit->record(
                'evidence.territory_spatial_deleted',
                $actor,
                $evidence,
                $allianceId,
                ['evidence_id' => $evidenceId, 'kingdom_id' => $kingdomId],
            );

            return $storage;
        });

        if (is_array($storage)) {
            Storage::disk($storage['disk'])->delete($storage['path']);
        }
    }
}
