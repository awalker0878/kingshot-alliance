<?php

declare(strict_types=1);

namespace App\ReadModels\ScreenshotIntake\Queries;

use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Operations\Results\Queries\BearHuntEvidenceTargetQuery;

final readonly class ScreenshotIntakeWorkspaceQuery
{
    public function __construct(private BearHuntEvidenceTargetQuery $targets) {}

    /** @return array{occurrenceId:string,allianceId:string,evidence:list<array<string,mixed>>} */
    public function forBearHunt(string $actorPlayerId, string $occurrenceId): array
    {
        $target = $this->targets->authorizeManage($actorPlayerId, $occurrenceId);

        $evidence = GameEvidence::query()
            ->where('alliance_id', $target->allianceId)
            ->where('occurrence_id', $target->occurrenceId)
            ->whereNotIn('lifecycle_status', ['purged'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(static fn (GameEvidence $item): array => [
                'id' => (string) $item->id,
                'originalName' => (string) $item->original_name,
                'mimeType' => (string) $item->mime_type,
                'sizeBytes' => (int) $item->size_bytes,
                'width' => (int) $item->width,
                'height' => (int) $item->height,
                'sha256Prefix' => substr((string) $item->sha256, 0, 12),
                'status' => (string) $item->getRawOriginal('lifecycle_status'),
                'kind' => (string) $item->getRawOriginal('kind'),
                'receivedAt' => $item->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'occurrenceId' => $target->occurrenceId,
            'allianceId' => $target->allianceId,
            'evidence' => $evidence,
        ];
    }
}
