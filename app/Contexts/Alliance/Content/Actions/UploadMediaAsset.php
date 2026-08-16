<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Content\Enums\MediaLifecycleStatus;
use App\Contexts\Alliance\Content\Enums\MediaScanStatus;
use App\Contexts\Alliance\Content\Models\MediaAsset;
use App\Contexts\Alliance\Content\Services\MediaScanner;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Core\ValueObjects\TenantContextSnapshot;
use App\Contexts\Alliance\Policies\AllianceCapacityPolicy;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class UploadMediaAsset
{
    public function __construct(
        private AllianceAuthorization $readAuthorization,
        private AllianceWriteState $allianceWriteState,
        private AllianceAuthorization $mutationAuthority,
        private MediaScanner $scanner,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
        private AllianceCapacityPolicy $entitlements,
    ) {}

    public function handle(Alliance $alliance, Player $actor, UploadedFile $file): MediaAsset
    {
        // Cheap preflight only. The authoritative permission/quota decision is repeated
        // after external file work, inside the transaction that persists the asset.
        if (! $this->readAuthorization->allows($actor, $alliance, AlliancePermission::ContentManage)) {
            throw new AuthorizationException;
        }

        $disk = (string) config('content.media_disk', 'local');

        if ($disk === 'public') {
            throw ValidationException::withMessages([
                'media' => 'Content media must use a private storage disk.',
            ]);
        }

        $mimeType = (string) $file->getMimeType();
        $allowedMimes = array_values(array_filter(
            (array) config('content.media_mime_types', []),
            'is_string',
        ));
        $maxBytes = max(1, (int) config('content.media_max_kilobytes', 8192)) * 1024;
        $size = $file->getSize();

        if (! in_array($mimeType, $allowedMimes, true) || ! is_int($size) || $size < 1 || $size > $maxBytes) {
            throw ValidationException::withMessages([
                'media' => 'The uploaded media type or size is not permitted.',
            ]);
        }

        $scan = $this->scanner->scan($file);

        if (! $scan->clean) {
            $this->audit->record('content.media_rejected', $actor, null, $alliance, [
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $mimeType,
                'reason' => $scan->reason,
            ]);

            throw ValidationException::withMessages([
                'media' => $scan->reason ?? 'The uploaded file failed security screening.',
            ]);
        }

        $sha256 = hash_file('sha256', $file->getPathname());

        if (! is_string($sha256)) {
            throw ValidationException::withMessages([
                'media' => 'The uploaded media could not be checksummed.',
            ]);
        }

        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            default => throw ValidationException::withMessages(['media' => 'Unsupported media type.']),
        };

        $snapshot = new TenantContextSnapshot((string) $alliance->id, (string) $actor->id);
        $path = $snapshot->storagePath('media/'.Str::ulid().'.'.$extension);
        $stored = Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));

        if ($stored === false) {
            throw ValidationException::withMessages([
                'media' => 'The uploaded media could not be stored.',
            ]);
        }

        try {
            return DB::transaction(function () use ($alliance, $actor, $file, $disk, $path, $mimeType, $size, $sha256): MediaAsset {
                // Storage capacity is Alliance-wide, so creation takes the exclusive
                // authority variant to serialize the quota check with this insert.
                $context = $this->allianceWriteState->lockExclusiveScope($actor, $alliance);
                $this->mutationAuthority->authorizeContext($context, AlliancePermission::ContentManage);
                $this->entitlements->assertStorageCapacity($context->alliance, $size);

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
                    'uploaded_by_player_id' => $context->actor->id,
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

                return $asset;
            });
        } catch (Throwable $exception) {
            // The external object was written before the authoritative transaction;
            // compensate if permission, lifecycle, quota, or persistence now rejects it.
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
    }
}
