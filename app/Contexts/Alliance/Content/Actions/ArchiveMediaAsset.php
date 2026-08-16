<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Content\Enums\MediaLifecycleStatus;
use App\Contexts\Alliance\Content\Models\AllianceBrandingMedia;
use App\Contexts\Alliance\Content\Models\MediaAsset;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
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
            $context = $this->authority->require($actor, $alliance, AlliancePermission::ContentManage);

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
