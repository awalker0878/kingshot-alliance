<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\ValueObjects\EvidenceUploadResult;
use App\Contexts\Operations\Results\Queries\BearHuntEvidenceTargetQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use App\Shared\Infrastructure\Uploads\Services\UploadScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class UploadGameEvidence
{
    public function __construct(
        private BearHuntEvidenceTargetQuery $targets,
        private PlayerReferenceQuery $players,
        private UploadScanner $scanner,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $actorPlayerId, string $occurrenceId, UploadedFile $file): EvidenceUploadResult
    {
        $target = $this->targets->authorizeManage($actorPlayerId, $occurrenceId);
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
        if (! is_array($imageInfo) || ! isset($imageInfo[0], $imageInfo[1], $imageInfo['mime'])
            || (string) $imageInfo['mime'] !== $mimeType || (int) $imageInfo[0] < 1 || (int) $imageInfo[1] < 1) {
            throw ValidationException::withMessages(['evidence' => 'The uploaded file is not a valid supported image.']);
        }

        $scan = $this->scanner->scan($file);
        if (! $scan->clean) {
            $this->audit->record('evidence.upload_rejected', $actor, null, $target->allianceId, [
                'mime_type' => $mimeType,
                'reason' => $scan->reason,
            ]);
            throw ValidationException::withMessages(['evidence' => $scan->reason ?? 'The screenshot failed security screening.']);
        }

        $sha256 = hash_file('sha256', $file->getPathname());
        if (! is_string($sha256)) {
            throw ValidationException::withMessages(['evidence' => 'The screenshot could not be checksummed.']);
        }

        $existing = GameEvidence::query()
            ->where('alliance_id', $target->allianceId)
            ->where('sha256', $sha256)
            ->first();
        if ($existing instanceof GameEvidence) {
            $this->audit->record('evidence.exact_duplicate_detected', $actor, $existing, $target->allianceId, [
                'occurrence_id' => $occurrenceId,
                'sha256' => $sha256,
            ]);
            return new EvidenceUploadResult((string) $existing->id, true);
        }

        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw ValidationException::withMessages(['evidence' => 'Unsupported screenshot type.']),
        };
        $path = 'evidence/'.$target->allianceId.'/'.Str::ulid().'.'.$extension;
        $stored = Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));
        if ($stored === false) {
            throw ValidationException::withMessages(['evidence' => 'The screenshot could not be stored.']);
        }

        try {
            $result = DB::transaction(function () use ($actorPlayerId, $occurrenceId, $file, $disk, $path, $mimeType, $size, $sha256, $imageInfo): EvidenceUploadResult {
                $target = $this->targets->authorizeManage($actorPlayerId, $occurrenceId);
                $actor = $this->players->lockCurrent($actorPlayerId);
                $duplicate = GameEvidence::query()
                    ->where('alliance_id', $target->allianceId)
                    ->where('sha256', $sha256)
                    ->lockForUpdate()
                    ->first();
                if ($duplicate instanceof GameEvidence) {
                    return new EvidenceUploadResult((string) $duplicate->id, true);
                }

                $evidence = GameEvidence::query()->create([
                    'alliance_id' => $target->allianceId,
                    'occurrence_id' => $target->occurrenceId,
                    'expected_kind' => EvidenceKind::BearHuntBattleReport,
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
                    'uploaded_by_player_id' => $actorPlayerId,
                    'scanned_at' => now(),
                ]);

                $metadata = [
                    'evidence_id' => (string) $evidence->id,
                    'occurrence_id' => $target->occurrenceId,
                    'mime_type' => $mimeType,
                    'size_bytes' => $size,
                    'sha256' => $sha256,
                    'width' => (int) $imageInfo[0],
                    'height' => (int) $imageInfo[1],
                ];
                $this->audit->record('evidence.uploaded', $actor, $evidence, $target->allianceId, $metadata);
                $this->outbox->record('evidence.uploaded', $target->allianceId, $evidence, $metadata);

                return new EvidenceUploadResult((string) $evidence->id, false);
            });

            if ($result->duplicate) {
                Storage::disk($disk)->delete($path);
            }

            return $result;
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
    }
}
