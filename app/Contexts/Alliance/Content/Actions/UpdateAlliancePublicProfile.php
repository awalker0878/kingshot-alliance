<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Content\Enums\MediaLifecycleStatus;
use App\Contexts\Alliance\Content\Enums\MediaScanStatus;
use App\Contexts\Alliance\Content\Models\AllianceBrandingMedia;
use App\Contexts\Alliance\Content\Models\AllianceProfile;
use App\Contexts\Alliance\Content\Models\MediaAsset;
use App\Contexts\Alliance\Content\Services\ContentSanitizer;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateAlliancePublicProfile
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AllianceAuthorization $authority,
        private ContentSanitizer $sanitizer,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param array{
     *   name: string,
     *   language: string,
     *   timezone: string,
     *   description?: string|null,
     *   primary_color?: string|null,
     *   logo_media_id?: string|null,
     *   banner_media_id?: string|null
     * } $attributes
     */
    public function handle(string $allianceId, string $actorPlayerId, array $attributes): string
    {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $attributes): string {
            // This workflow changes the Alliance aggregate itself, so the exclusive
            // parent boundary is intentional rather than an ordinary child lock.
            $context = $this->allianceWriteState->lockExclusiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::ContentManage);
            $locked = $context->alliance;

            $locked->forceFill([
                'name' => $this->sanitizer->line($attributes['name']) ?? $locked->name,
                'language' => strtolower(trim($attributes['language'])),
                'timezone' => trim($attributes['timezone']),
            ])->save();

            AllianceProfile::query()->updateOrCreate(
                ['alliance_id' => $locked->id],
                [
                    'description' => $this->sanitizer->body((string) ($attributes['description'] ?? '')) ?: null,
                    'primary_color' => isset($attributes['primary_color']) && $attributes['primary_color'] !== ''
                        ? strtoupper((string) $attributes['primary_color'])
                        : null,
                ],
            );

            $this->setBrandingSlot((string) $locked->id, 'logo', $attributes['logo_media_id'] ?? null);
            $this->setBrandingSlot((string) $locked->id, 'banner', $attributes['banner_media_id'] ?? null);

            $this->audit->record(
                event: 'alliance.public_profile_updated',
                actor: $context->actor,
                subject: $locked,
                alliance: $locked,
                metadata: [
                    'language' => $locked->language,
                    'timezone' => $locked->timezone,
                ],
            );

            $this->outbox->record('alliance.public_profile_updated', (string) $locked->id, $locked, [
                'language' => $locked->language,
                'timezone' => $locked->timezone,
            ]);

            return (string) $locked->id;
        });
    }

    private function setBrandingSlot(string $allianceId, string $slot, ?string $mediaId): void
    {
        if ($mediaId === null || trim($mediaId) === '') {
            AllianceBrandingMedia::query()
                ->where('alliance_id', $allianceId)
                ->where('slot', $slot)
                ->delete();

            return;
        }

        $media = MediaAsset::query()
            ->where('id', $mediaId)
            ->where('alliance_id', $allianceId)
            ->where('scan_status', MediaScanStatus::Clean->value)
            ->where('lifecycle_status', MediaLifecycleStatus::Active->value)
            ->lockForUpdate()
            ->first();

        if (! $media instanceof MediaAsset || ! str_starts_with((string) $media->mime_type, 'image/')) {
            throw ValidationException::withMessages([
                $slot.'_media_id' => 'Branding media must be an active, clean image from this alliance.',
            ]);
        }

        AllianceBrandingMedia::query()->updateOrCreate(
            ['alliance_id' => $allianceId, 'slot' => $slot],
            ['media_id' => $media->id],
        );
    }
}
