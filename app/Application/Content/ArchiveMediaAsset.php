<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Application\Identity\AllianceAuthorization;
use App\Application\Identity\AuditRecorder;
use App\Domain\Content\Enums\MediaLifecycleStatus;
use App\Domain\Identity\Authorization\PermissionKey;
use App\Models\Alliance;
use App\Models\AllianceBrandingMedia;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ArchiveMediaAsset
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private ContentOutbox $outbox,
    ) {}

    public function handle(Alliance $alliance, User $actor, string $mediaId): MediaAsset
    {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::ContentManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $mediaId): MediaAsset {
            $asset = MediaAsset::query()
                ->where('id', $mediaId)
                ->where('alliance_id', $alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (AllianceBrandingMedia::query()
                ->where('alliance_id', $alliance->id)
                ->where('media_id', $asset->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'media' => 'Remove this asset from alliance branding before archiving it.',
                ]);
            }

            $asset->forceFill(['lifecycle_status' => MediaLifecycleStatus::Archived])->save();

            $this->audit->record('content.media_archived', $actor, $asset, $alliance, [
                'sha256' => $asset->sha256,
            ]);
            $this->outbox->record('content.media_archived', $alliance, $asset, [
                'media_id' => $asset->id,
                'sha256' => $asset->sha256,
            ]);

            return $asset->refresh();
        });
    }
}
