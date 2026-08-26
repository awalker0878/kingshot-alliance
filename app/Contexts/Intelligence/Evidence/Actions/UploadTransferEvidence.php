<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferEvidenceTargetQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Jobs\ClassifyGameEvidenceJob;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Services\ImagePerceptualHasher;
use App\Contexts\Intelligence\Evidence\ValueObjects\EvidenceUploadResult;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use App\Shared\Infrastructure\Uploads\Services\UploadScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class UploadTransferEvidence
{
    public function __construct(
        private TransferEvidenceTargetQuery $targets,
        private PlayerReferenceQuery $players,
        private UploadScanner $scanner,
        private ImagePerceptualHasher $hasher,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $allianceId,
        string $planId,
        string $participantId,
        EvidenceKind $expectedKind,
        UploadedFile $file,
    ): EvidenceUploadResult {
        if (! $expectedKind->isTransfer()) {
            throw ValidationException::withMessages(['evidence_kind' => 'Select a supported Transfer screenshot class.']);
        }
        $target = $this->targets->authorizeManage($actorPlayerId, $allianceId, $planId, $participantId);
        if ($this->requiresTarget($expectedKind) && $target->targetKingdomId === null) {
            throw ValidationException::withMessages(['participant' => 'Set a target Kingdom before adding this screenshot class.']);
        }
        $actor = $this->players->require($actorPlayerId);
        $disk = (string) config('evidence.disk', 'local');
        if ($disk === 'public') {
            throw ValidationException::withMessages(['evidence' => 'Game evidence must use a private storage disk.']);
        }

        $size = $file->getSize();
        $mimeType = (string) $file->getMimeType();
        $allowedMimes = array_values(array_filter((array) config('evidence.mime_types', []), 'is_string'));
        $maxBytes = max(1, (int) config('evidence.max_kilobytes', 12288)) * 1024;
        if (! is_int($size) || $size < 1 || $size > $maxBytes || ! in_array($mimeType, $allowedMimes, true)) {
            throw ValidationException::withMessages(['evidence' => 'The screenshot type or size is not permitted.']);
        }

        $imageInfo = @getimagesize($file->getPathname());
        if ($imageInfo === false
            || (string) $imageInfo['mime'] !== $mimeType
            || (int) $imageInfo[0] < 1
            || (int) $imageInfo[1] < 1) {
            throw ValidationException::withMessages(['evidence' => 'The uploaded file is not a valid supported image.']);
        }

        $scan = $this->scanner->scan($file);
        if (! $scan->clean) {
            $this->audit->record('evidence.transfer_upload_rejected', $actor, null, $target->allianceId, [
                'evidence_kind' => $expectedKind->value,
                'reason' => $scan->reason,
            ]);
            throw ValidationException::withMessages(['evidence' => $scan->reason ?? 'The screenshot failed security screening.']);
        }

        $sha256 = hash_file('sha256', $file->getPathname());
        if (! is_string($sha256)) {
            throw ValidationException::withMessages(['evidence' => 'The screenshot could not be checksummed.']);
        }
        $existing = $this->exactDuplicate($target->allianceId, $planId, $participantId, $expectedKind, $sha256);
        if ($existing instanceof GameEvidence) {
            $this->audit->record('evidence.exact_duplicate_detected', $actor, $existing, $target->allianceId, [
                'evidence_kind' => $expectedKind->value,
                'scope' => 'transfer_participant',
            ]);

            return new EvidenceUploadResult((string) $existing->id, true);
        }

        $perceptualHash = $this->hasher->hashFile($file->getPathname());
        [$visualDuplicateId, $visualDistance] = $this->visualDuplicate(
            $target->allianceId,
            $planId,
            $participantId,
            $expectedKind,
            $perceptualHash,
        );
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw ValidationException::withMessages(['evidence' => 'Unsupported screenshot type.']),
        };
        $path = 'evidence/'.$target->allianceId.'/transfer/'.$planId.'/'.Str::ulid().'.'.$extension;
        if (Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path)) === false) {
            throw ValidationException::withMessages(['evidence' => 'The screenshot could not be stored.']);
        }

        try {
            $result = DB::transaction(function () use ($actorPlayerId, $allianceId, $planId, $participantId, $expectedKind, $file, $disk, $path, $mimeType, $size, $sha256, $imageInfo, $perceptualHash, $visualDuplicateId, $visualDistance): EvidenceUploadResult {
                $target = $this->targets->authorizeManage($actorPlayerId, $allianceId, $planId, $participantId);
                if ($this->requiresTarget($expectedKind) && $target->targetKingdomId === null) {
                    throw ValidationException::withMessages(['participant' => 'Set a target Kingdom before adding this screenshot class.']);
                }
                $actor = $this->players->lockCurrent($actorPlayerId);
                $duplicate = $this->exactDuplicate($target->allianceId, $planId, $participantId, $expectedKind, $sha256, true);
                if ($duplicate instanceof GameEvidence) {
                    return new EvidenceUploadResult((string) $duplicate->id, true);
                }

                $evidence = GameEvidence::query()->create([
                    'alliance_id' => $target->allianceId,
                    'occurrence_id' => null,
                    'transfer_plan_id' => $planId,
                    'transfer_participant_id' => $participantId,
                    'expected_kind' => $expectedKind,
                    'kind' => EvidenceKind::Unknown,
                    'lifecycle_status' => EvidenceLifecycleStatus::Uploaded,
                    'original_name' => $file->getClientOriginalName(),
                    'disk' => $disk,
                    'path' => $path,
                    'mime_type' => $mimeType,
                    'size_bytes' => $size,
                    'width' => (int) $imageInfo[0],
                    'height' => (int) $imageInfo[1],
                    'sha256' => $sha256,
                    'perceptual_hash' => $perceptualHash,
                    'visual_duplicate_evidence_id' => $visualDuplicateId,
                    'visual_duplicate_distance' => $visualDistance,
                    'uploaded_by_player_id' => $actorPlayerId,
                    'scanned_at' => now(),
                ]);
                $metadata = [
                    'evidence_id' => (string) $evidence->id,
                    'evidence_kind' => $expectedKind->value,
                    'scope' => 'transfer_participant',
                    'has_visual_duplicate' => $visualDuplicateId !== null,
                    'mime_type' => $mimeType,
                    'size_bytes' => $size,
                    'width' => (int) $imageInfo[0],
                    'height' => (int) $imageInfo[1],
                ];
                $this->audit->record('evidence.transfer_uploaded', $actor, $evidence, $target->allianceId, $metadata);
                $this->outbox->record('evidence.transfer_uploaded', $target->allianceId, $evidence, $metadata);
                if ($visualDuplicateId !== null) {
                    $this->audit->record('evidence.visual_duplicate_detected', $actor, $evidence, $target->allianceId, $metadata);
                }

                return new EvidenceUploadResult((string) $evidence->id, false);
            });
            if ($result->duplicate) {
                Storage::disk($disk)->delete($path);
            } else {
                ClassifyGameEvidenceJob::dispatch($result->evidenceId);
            }

            return $result;
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
    }

    private function requiresTarget(EvidenceKind $kind): bool
    {
        return in_array($kind, [
            EvidenceKind::TransferScorePasses,
            EvidenceKind::TransferInvitation,
            EvidenceKind::TransferTargetKingdomRules,
        ], true);
    }

    private function exactDuplicate(
        string $allianceId,
        string $planId,
        string $participantId,
        EvidenceKind $kind,
        string $sha256,
        bool $lock = false,
    ): ?GameEvidence {
        $query = GameEvidence::query()
            ->where('alliance_id', $allianceId)
            ->where('transfer_plan_id', $planId)
            ->where('transfer_participant_id', $participantId)
            ->where('expected_kind', $kind->value)
            ->where('sha256', $sha256);
        if ($lock) {
            $query->lockForUpdate();
        }
        $evidence = $query->first();

        return $evidence instanceof GameEvidence ? $evidence : null;
    }

    /** @return array{0:?string,1:?int} */
    private function visualDuplicate(
        string $allianceId,
        string $planId,
        string $participantId,
        EvidenceKind $kind,
        ?string $hash,
    ): array {
        if ($hash === null) {
            return [null, null];
        }
        $threshold = max(0, min(64, (int) config('evidence.visual_duplicate_distance', 8)));
        $bestId = null;
        $bestDistance = null;
        $candidates = GameEvidence::query()
            ->select(['id', 'perceptual_hash'])
            ->where('alliance_id', $allianceId)
            ->where('transfer_plan_id', $planId)
            ->where('transfer_participant_id', $participantId)
            ->where('expected_kind', $kind->value)
            ->whereNotNull('perceptual_hash')
            ->get();
        foreach ($candidates as $candidate) {
            $distance = $this->hasher->distance($hash, (string) $candidate->perceptual_hash);
            if ($distance !== null && $distance <= $threshold && ($bestDistance === null || $distance < $bestDistance)) {
                $bestId = (string) $candidate->id;
                $bestDistance = $distance;
            }
        }

        return [$bestId, $bestDistance];
    }
}
