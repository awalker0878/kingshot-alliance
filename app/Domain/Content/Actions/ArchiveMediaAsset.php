<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Domain\Content\Enums\MediaLifecycleStatus;
use App\Domain\Content\Models\AllianceBrandingMedia;
use App\Domain\Content\Models\MediaAsset;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ArchiveMediaAsset
{
    public function __construct(
        private AllianceMutationAuthority $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $alliance, Player $actor, string $mediaId): MediaAsset
    {
        return DB::transaction(function () use ($alliance, $actor, $mediaId): MediaAsset {
            $context = $this->authority->require($actor, $alliance, PermissionKey::ContentManage);

            $asset = MediaAsset::query()
                ->where('id', $mediaId)
                ->where('alliance_id', $context->alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (AllianceBrandingMedia::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('media_id', $asset->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'media' => 'Remove this asset from alliance branding before archiving it.',
                ]);
            }

            $asset->forceFill(['lifecycle_status' => MediaLifecycleStatus::Archived])->save();

            $this->audit->record('content.media_archived', $context->actor, $asset, $context->alliance, [
                'sha256' => $asset->sha256,
            ]);
            $this->outbox->record('content.media_archived', (string) $context->alliance->id, $asset, [
                'media_id' => $asset->id,
                'sha256' => $asset->sha256,
            ]);

            return $asset->refresh();
        });
    }
}
