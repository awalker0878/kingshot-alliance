<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Content\Enums\MediaLifecycleStatus;
use App\Contexts\Alliance\Content\Enums\MediaScanStatus;
use App\Contexts\Alliance\Content\Models\MediaAsset;
use App\Contexts\Alliance\Content\Policies\StorageCapacityPolicy;
use App\Contexts\Alliance\Lifecycle\ValueObjects\TenantContextSnapshot;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use App\Shared\Infrastructure\Uploads\Services\UploadScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class UploadMediaAsset
{
    public function __construct(
        private AllianceAuthorization $authority,
        private AllianceWriteState $allianceWriteState,
        private UploadScanner $scanner,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
        private StorageCapacityPolicy $capacity,
    ) {}

    public function handle(string $allianceId, string $actorPlayerId, UploadedFile $file): string
    {
        DB::transaction(function () use ($actorPlayerId, $allianceId): void {
            $context = $this->allianceWriteState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::ContentManage);
        });

        $disk = (string) config('content.media_disk', 'local');
        if ($disk === 'public') {
            throw ValidationException::withMessages(['media' => 'Content media must use a private storage disk.']);
        }

        $mimeType = (string) $file->getMimeType();
        $allowedMimes = array_values(array_filter((array) config('content.media_mime_types', []), 'is_string'));
        $maxBytes = max(1, (int) config('content.media_max_kilobytes', 8192)) * 1024;
        $size = $file->getSize();

        if (! in_array($mimeType, $allowedMimes, true) || ! is_int($size) || $size < 1 || $size > $maxBytes) {
            throw ValidationException::withMessages(['media' => 'The uploaded media type or size is not permitted.']);
        }

        $scan = $this->scanner->scan($file);
        if (! $scan->clean) {
            DB::transaction(function () use ($allianceId, $actorPlayerId, $file, $mimeType, $scan): void {
                $context = $this->allianceWriteState->lockActiveScope($actorPlayerId, $allianceId);
                $this->authority->authorizeContext($context, AlliancePermission::ContentManage);
                $this->audit->record('content.media_rejected', $context->actor, null, $context->alliance, [
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $mimeType,
                    'reason' => $scan->reason,
                ]);
            });

            throw ValidationException::withMessages(['media' => $scan->reason ?? 'The uploaded file failed security screening.']);
        }

        $sha256 = hash_file('sha256', $file->getPathname());
        if (! is_string($sha256)) {
            throw ValidationException::withMessages(['media' => 'The uploaded media could not be checksummed.']);
        }

        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            default => throw ValidationException::withMessages(['media' => 'Unsupported media type.']),
        };

        $snapshot = new TenantContextSnapshot($allianceId, $actorPlayerId);
        $path = $snapshot->storagePath('media/'.Str::ulid().'.'.$extension);
        $stored = Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));
        if ($stored === false) {
            throw ValidationException::withMessages(['media' => 'The uploaded media could not be stored.']);
        }

        try {
            return DB::transaction(function () use ($allianceId, $actorPlayerId, $file, $disk, $path, $mimeType, $size, $sha256): string {
                $context = $this->allianceWriteState->lockExclusiveScope($actorPlayerId, $allianceId);
                $this->authority->authorizeContext($context, AlliancePermission::ContentManage);
                $this->capacity->assertCapacity($context->alliance, $size);

                $asset = MediaAsset::query()->create([
                    'alliance_id' => $context->alliance->id,
                    'original_name' => $file->getClientOriginalName(),
                    'disk' => $disk,
                    'path' => $path,
                    'mime_type' => $mimeType,
                    'size_bytes' => $size,
                    'sha256' => $sha256,
                    'scan_status' => MediaScanStatus::Clean,
                    'lifecycle_status' => MediaLifecycleStatus::Active,
                    'uploaded_by_player_id' => $context->actor->playerId,
                    'scanned_at' => now(),
                ]);

                $this->audit->record('content.media_uploaded', $context->actor, $asset, $context->alliance, [
                    'mime_type' => $mimeType,
                    'size_bytes' => $size,
                    'sha256' => $sha256,
                ]);
                $this->outbox->record('content.media_uploaded', (string) $context->alliance->id, $asset, [
                    'media_id' => $asset->id,
                    'mime_type' => $mimeType,
                    'size_bytes' => $size,
                    'sha256' => $sha256,
                ]);

                return (string) $asset->id;
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
    }
}
