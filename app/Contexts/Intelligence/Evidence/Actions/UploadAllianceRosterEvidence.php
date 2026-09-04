<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Models\AllianceRosterEvidence;
use App\Contexts\Intelligence\Evidence\Services\ImagePerceptualHasher;
use App\Contexts\Intelligence\Evidence\ValueObjects\EvidenceUploadResult;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use App\Shared\Infrastructure\Uploads\Services\UploadScanner;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class UploadAllianceRosterEvidence
{
    public function __construct(
        private AllianceIntelligenceAuthorization $authorization,
        private AllianceIntelligenceWriteState $writeState,
        private PlayerReferenceQuery $players,
        private UploadScanner $scanner,
        private ImagePerceptualHasher $hasher,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $actorPlayerId, string $allianceId, UploadedFile $file): EvidenceUploadResult
    {
        if (! $this->authorization->allows($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }
        $actor = $this->players->require($actorPlayerId);
        $disk = (string) config('evidence.disk', 'local');
        if ($disk === 'public') {
            throw ValidationException::withMessages(['evidence' => 'Alliance roster evidence must use a private storage disk.']);
        }

        $size = $file->getSize();
        $mimeType = (string) $file->getMimeType();
        $allowedMimes = array_values(array_filter((array) config('evidence.mime_types', []), 'is_string'));
        $maxBytes = max(1, (int) config('evidence.max_kilobytes', 12288)) * 1024;
        if (! is_int($size) || $size < 1 || $size > $maxBytes || ! in_array($mimeType, $allowedMimes, true)) {
            throw ValidationException::withMessages(['evidence' => 'The screenshot type or size is not permitted.']);
        }

        $imageInfo = @getimagesize($file->getPathname());
        if ($imageInfo === false || (string) $imageInfo['mime'] !== $mimeType || (int) $imageInfo[0] < 1 || (int) $imageInfo[1] < 1) {
            throw ValidationException::withMessages(['evidence' => 'The uploaded file is not a valid supported image.']);
        }

        $scan = $this->scanner->scan($file);
        if (! $scan->clean) {
            $this->audit->record('evidence.alliance_roster_upload_rejected', $actor, null, $allianceId, ['reason' => $scan->reason]);
            throw ValidationException::withMessages(['evidence' => $scan->reason ?? 'The screenshot failed security screening.']);
        }

        $sha256 = hash_file('sha256', $file->getPathname());
        if (! is_string($sha256)) {
            throw ValidationException::withMessages(['evidence' => 'The screenshot could not be checksummed.']);
        }
        $existing = AllianceRosterEvidence::query()->where('alliance_id', $allianceId)->where('sha256', $sha256)->first();
        if ($existing instanceof AllianceRosterEvidence) {
            return new EvidenceUploadResult((string) $existing->id, true);
        }

        $perceptualHash = $this->hasher->hashFile($file->getPathname());
        [$visualDuplicateId, $visualDistance] = $this->visualDuplicate($allianceId, $perceptualHash);
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw ValidationException::withMessages(['evidence' => 'Unsupported screenshot type.']),
        };
        $path = 'evidence/'.$allianceId.'/alliance-roster/'.Str::ulid().'.'.$extension;
        if (Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path)) === false) {
            throw ValidationException::withMessages(['evidence' => 'The screenshot could not be stored.']);
        }

        try {
            return DB::transaction(function () use ($actorPlayerId, $allianceId, $file, $disk, $path, $mimeType, $size, $sha256, $imageInfo, $perceptualHash, $visualDuplicateId, $visualDistance): EvidenceUploadResult {
                [, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);
                $duplicate = AllianceRosterEvidence::query()
                    ->where('alliance_id', $allianceId)
                    ->where('sha256', $sha256)
                    ->lockForUpdate()
                    ->first();
                if ($duplicate instanceof AllianceRosterEvidence) {
                    Storage::disk($disk)->delete($path);

                    return new EvidenceUploadResult((string) $duplicate->id, true);
                }

                $evidence = AllianceRosterEvidence::query()->create([
                    'alliance_id' => $allianceId,
                    'lifecycle_status' => EvidenceLifecycleStatus::NeedsReview,
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
                    'evidence_kind' => 'alliance_roster',
                    'has_visual_duplicate' => $visualDuplicateId !== null,
                    'mime_type' => $mimeType,
                    'size_bytes' => $size,
                    'width' => (int) $imageInfo[0],
                    'height' => (int) $imageInfo[1],
                ];
                $this->audit->record('evidence.alliance_roster_uploaded', $actor, $evidence, $allianceId, $metadata);
                $this->outbox->record('evidence.alliance_roster_uploaded', $allianceId, $evidence, $metadata);

                return new EvidenceUploadResult((string) $evidence->id, false);
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
    }

    /** @return array{0:?string,1:?int} */
    private function visualDuplicate(string $allianceId, ?string $hash): array
    {
        if ($hash === null) {
            return [null, null];
        }
        $threshold = max(0, min(64, (int) config('evidence.visual_duplicate_distance', 8)));
        $bestId = null;
        $bestDistance = null;
        foreach (AllianceRosterEvidence::query()->select(['id', 'perceptual_hash'])->where('alliance_id', $allianceId)->whereNotNull('perceptual_hash')->get() as $candidate) {
            $distance = $this->hasher->distance($hash, (string) $candidate->perceptual_hash);
            if ($distance !== null && $distance <= $threshold && ($bestDistance === null || $distance < $bestDistance)) {
                $bestId = (string) $candidate->id;
                $bestDistance = $distance;
            }
        }

        return [$bestId, $bestDistance];
    }
}
