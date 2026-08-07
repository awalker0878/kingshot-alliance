<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Application\Content\ArchiveMediaAsset;
use App\Application\Content\MediaScanner;
use App\Application\Content\MediaScanResult;
use App\Application\Content\UpdateAlliancePublicProfile;
use App\Application\Content\UploadMediaAsset;
use App\Application\Identity\CreateAlliance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ProfileMediaSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_tenant_media_can_be_used_for_branding_and_streamed_publicly(): void
    {
        Storage::fake('local');
        config()->set('content.media_disk', 'local');
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Brand Alliance', 'brand-alliance');
        $asset = $this->app->make(UploadMediaAsset::class)
            ->handle($alliance, $owner, UploadedFile::fake()->image('logo.png', 120, 120));

        Storage::disk('local')->assertExists($asset->path);
        self::assertStringStartsWith('alliances/'.$alliance->id.'/media/', $asset->path);

        $this->app->make(UpdateAlliancePublicProfile::class)->handle($alliance, $owner, [
            'name' => 'Brand Alliance',
            'kingdom' => '1001',
            'language' => 'pt-BR',
            'timezone' => 'America/Sao_Paulo',
            'description' => '<b>Public description</b>',
            'primary_color' => '#22d3ee',
            'logo_media_id' => $asset->id,
            'banner_media_id' => null,
        ]);

        $this->get('/alliances/brand-alliance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('alliance.description', 'Public description')
                ->where('alliance.recruitmentStatus', 'closed')
                ->where('alliance.primaryColor', '#22D3EE')
                ->where('alliance.logoUrl', route('public.alliances.branding', ['brand-alliance', 'logo'])));

        $this->get('/alliances/brand-alliance/branding/logo')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        try {
            $this->app->make(ArchiveMediaAsset::class)->handle($alliance, $owner, $asset->id);
            self::fail('Branding media must be detached before archival.');
        } catch (ValidationException) {
            self::assertTrue(true);
        }

        $this->app->make(UpdateAlliancePublicProfile::class)->handle($alliance, $owner, [
            'name' => 'Brand Alliance',
            'kingdom' => '1001',
            'language' => 'pt-BR',
            'timezone' => 'America/Sao_Paulo',
            'description' => 'Public description',
            'primary_color' => '#22D3EE',
            'logo_media_id' => null,
            'banner_media_id' => null,
        ]);
        $archived = $this->app->make(ArchiveMediaAsset::class)->handle($alliance, $owner, $asset->id);
        self::assertSame('archived', $archived->lifecycle_status->value);
        $this->get('/alliances/brand-alliance/branding/logo')->assertNotFound();
    }

    public function test_cross_alliance_media_cannot_be_attached_to_branding(): void
    {
        Storage::fake('local');
        config()->set('content.media_disk', 'local');
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstOwner, 'First Brand', 'first-brand');
        $second = $createAlliance->handle($secondOwner, 'Second Brand', 'second-brand');
        $secondAsset = $this->app->make(UploadMediaAsset::class)
            ->handle($second, $secondOwner, UploadedFile::fake()->image('other.png'));

        $this->expectException(ValidationException::class);
        $this->app->make(UpdateAlliancePublicProfile::class)->handle($first, $firstOwner, [
            'name' => 'First Brand',
            'kingdom' => null,
            'language' => 'en',
            'timezone' => 'UTC',
            'description' => null,
            'primary_color' => null,
            'logo_media_id' => $secondAsset->id,
            'banner_media_id' => null,
        ]);
    }

    public function test_failed_malware_screening_never_persists_or_stores_media(): void
    {
        Storage::fake('local');
        config()->set('content.media_disk', 'local');
        $this->app->instance(MediaScanner::class, new class implements MediaScanner
        {
            public function scan(UploadedFile $file): MediaScanResult
            {
                return new MediaScanResult(false, 'Rejected by test scanner.');
            }
        });

        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Scan Alliance', 'scan-alliance');

        try {
            $this->app->make(UploadMediaAsset::class)
                ->handle($alliance, $owner, UploadedFile::fake()->image('blocked.png'));
            self::fail('Rejected media must not be accepted.');
        } catch (ValidationException) {
            self::assertTrue(true);
        }

        $this->assertDatabaseCount('media_assets', 0);
        self::assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $alliance->id,
            'event' => 'content.media_rejected',
        ]);
    }
}
