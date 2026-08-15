<?php

declare(strict_types=1);

namespace App\Domain\Content\Http\Controllers;

use App\Domain\Alliances\Enums\AllianceStatus;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Content\Enums\MediaLifecycleStatus;
use App\Domain\Content\Enums\MediaScanStatus;
use App\Domain\Content\Models\AllianceBrandingMedia;
use App\Domain\Content\Models\MediaAsset;
use App\Shared\Http\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicBrandingMediaController extends Controller
{
    public function __invoke(string $slug, string $slot): StreamedResponse
    {
        abort_unless(in_array($slot, ['logo', 'banner'], true), 404);

        $alliance = Alliance::query()
            ->where('slug', $slug)
            ->where('status', AllianceStatus::Active->value)
            ->firstOrFail();

        $branding = AllianceBrandingMedia::query()
            ->where('alliance_id', $alliance->id)
            ->where('slot', $slot)
            ->firstOrFail();

        $media = MediaAsset::query()
            ->where('id', $branding->media_id)
            ->where('alliance_id', $alliance->id)
            ->where('scan_status', MediaScanStatus::Clean->value)
            ->where('lifecycle_status', MediaLifecycleStatus::Active->value)
            ->firstOrFail();

        abort_unless(str_starts_with((string) $media->mime_type, 'image/'), 404);

        return Storage::disk((string) $media->disk)->response(
            (string) $media->path,
            (string) $media->original_name,
            [
                'Content-Type' => (string) $media->mime_type,
                'Cache-Control' => 'public, max-age=3600',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
