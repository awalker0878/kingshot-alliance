<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Content\Enums\MediaLifecycleStatus;
use App\Domain\Content\Enums\MediaScanStatus;
use App\Domain\Content\Models\AllianceBrandingMedia;
use App\Domain\Content\Models\AllianceProfile;
use App\Domain\Content\Models\MediaAsset;
use App\Domain\Content\Services\ContentOutbox;
use App\Domain\Content\Services\ContentSanitizer;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateAlliancePublicProfile
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private ContentSanitizer $sanitizer,
        private AuditRecorder $audit,
        private ContentOutbox $outbox,
    ) {}

    /**
     * @param array{
     *   name: string,
     *   kingdom?: string|null,
     *   language: string,
     *   timezone: string,
     *   description?: string|null,
     *   primary_color?: string|null,
     *   logo_media_id?: string|null,
     *   banner_media_id?: string|null
     * } $attributes
     */
    public function handle(Alliance $alliance, User $actor, array $attributes): Alliance
    {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::ContentManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $attributes): Alliance {
            $locked = Alliance::query()->lockForUpdate()->findOrFail($alliance->id);

            $locked->forceFill([
                'name' => $this->sanitizer->line($attributes['name']) ?? $locked->name,
                'kingdom' => $this->sanitizer->line($attributes['kingdom'] ?? null),
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

            $this->setBrandingSlot($locked, 'logo', $attributes['logo_media_id'] ?? null);
            $this->setBrandingSlot($locked, 'banner', $attributes['banner_media_id'] ?? null);

            $this->audit->record(
                event: 'alliance.public_profile_updated',
                actor: $actor,
                subject: $locked,
                alliance: $locked,
                metadata: [
                    'language' => $locked->language,
                    'timezone' => $locked->timezone,
                ],
            );

            $this->outbox->record('alliance.public_profile_updated', $locked, $locked, [
                'language' => $locked->language,
                'timezone' => $locked->timezone,
            ]);

            return $locked->refresh();
        });
    }

    private function setBrandingSlot(Alliance $alliance, string $slot, ?string $mediaId): void
    {
        if ($mediaId === null || trim($mediaId) === '') {
            AllianceBrandingMedia::query()
                ->where('alliance_id', $alliance->id)
                ->where('slot', $slot)
                ->delete();

            return;
        }

        $media = MediaAsset::query()
            ->where('id', $mediaId)
            ->where('alliance_id', $alliance->id)
            ->where('scan_status', MediaScanStatus::Clean->value)
            ->where('lifecycle_status', MediaLifecycleStatus::Active->value)
            ->first();

        if (! $media instanceof MediaAsset || ! str_starts_with((string) $media->mime_type, 'image/')) {
            throw ValidationException::withMessages([
                $slot.'_media_id' => 'Branding media must be an active, clean image from this alliance.',
            ]);
        }

        AllianceBrandingMedia::query()->updateOrCreate(
            ['alliance_id' => $alliance->id, 'slot' => $slot],
            ['media_id' => $media->id],
        );
    }
}
