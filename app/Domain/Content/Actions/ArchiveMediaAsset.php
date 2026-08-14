<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Content\Enums\MediaLifecycleStatus;
use App\Domain\Content\Models\AllianceBrandingMedia;
use App\Domain\Content\Models\MediaAsset;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ArchiveMediaAsset
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $alliance, Player $actor, string $mediaId): MediaAsset
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
            $this->outbox->record('content.media_archived', (string) $alliance->id, $asset, [
                'media_id' => $asset->id,
                'sha256' => $asset->sha256,
            ]);

            return $asset->refresh();
        });
    }
}
