<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Alliances\ValueObjects\TenantContextSnapshot;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Content\Enums\MediaLifecycleStatus;
use App\Domain\Content\Enums\MediaScanStatus;
use App\Domain\Content\Models\MediaAsset;
use App\Domain\Content\Services\MediaScanner;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use App\Domain\Platform\Services\PlanEntitlementService;
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
        private AllianceAuthorization $authorization,
        private MediaScanner $scanner,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
        private PlanEntitlementService $entitlements,
    ) {}

    public function handle(Alliance $alliance, Player $actor, UploadedFile $file): MediaAsset
    {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::ContentManage)) {
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

        $this->entitlements->assertStorageCapacity($alliance, $size);
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
                $asset = MediaAsset::query()->create([
                    'alliance_id' => $alliance->id,
                    'original_name' => $file->getClientOriginalName(),
                    'disk' => $disk,
                    'path' => $path,
                    'mime_type' => $mimeType,
                    'size_bytes' => $size,
                    'sha256' => $sha256,
                    'scan_status' => MediaScanStatus::Clean,
                    'lifecycle_status' => MediaLifecycleStatus::Active,
                    'uploaded_by_player_id' => $actor->id,
                    'scanned_at' => now(),
                ]);

                $this->audit->record('content.media_uploaded', $actor, $asset, $alliance, [
                    'mime_type' => $mimeType,
                    'size_bytes' => $size,
                    'sha256' => $sha256,
                ]);
                $this->outbox->record('content.media_uploaded', (string) $alliance->id, $asset, [
                    'media_id' => $asset->id,
                    'mime_type' => $mimeType,
                    'size_bytes' => $size,
                    'sha256' => $sha256,
                ]);

                return $asset;
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
    }
}
