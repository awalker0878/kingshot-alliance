<?php

declare(strict_types=1);

namespace Tests\Feature\Alliance\Content\v2;

use App\Contexts\Alliance\Content\Actions\ArchiveMediaAsset;
use App\Contexts\Alliance\Content\Actions\UpdateAlliancePublicProfile;
use App\Contexts\Alliance\Content\Actions\UploadMediaAsset;
use App\Contexts\Alliance\Content\Services\MediaScanner;
use App\Contexts\Alliance\Content\ValueObjects\MediaScanResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\V2\ScenarioFactory;
use Tests\TestCase;

final class ProfileMediaSecurityV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_clean_tenant_media_can_brand_alliance_and_stream_publicly_only_until_archived(): void
    {
        Storage::fake('local');
        config()->set('content.media_disk', 'local');
        $scenario = (new ScenarioFactory)->alliance(4590, 'Brand Owner', 'Brand V2', 'brand-v2-4590');
        $asset = app(UploadMediaAsset::class)->handle(
            $scenario['alliance'],
            $scenario['player'],
            UploadedFile::fake()->image('logo.png', 120, 120),
        );

        Storage::disk('local')->assertExists($asset->path);
        self::assertStringStartsWith('alliances/'.$scenario['alliance']->id.'/media/', $asset->path);

        app(UpdateAlliancePublicProfile::class)->handle($scenario['alliance'], $scenario['player'], [
            'name' => 'Brand V2',
            'language' => 'pt-BR',
            'timezone' => 'America/Sao_Paulo',
            'description' => '<b>Public description</b>',
            'primary_color' => '#22d3ee',
            'logo_media_id' => $asset->id,
            'banner_media_id' => null,
        ]);

        $this->get('/alliances/brand-v2-4590')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('alliance.kingdom', '4590')
                ->where('alliance.description', 'Public description')
                ->where('alliance.recruitmentStatus', 'closed')
                ->where('alliance.primaryColor', '#22D3EE')
                ->where('alliance.logoUrl', route('public.alliances.branding', ['brand-v2-4590', 'logo'])));

        $this->get('/alliances/brand-v2-4590/branding/logo')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        try {
            app(ArchiveMediaAsset::class)->handle($scenario['alliance'], $scenario['player'], $asset->id);
            self::fail('Branding media must be detached before archival.');
        } catch (ValidationException) {
            self::assertTrue(true);
        }

        app(UpdateAlliancePublicProfile::class)->handle($scenario['alliance'], $scenario['player'], [
            'name' => 'Brand V2',
            'language' => 'pt-BR',
            'timezone' => 'America/Sao_Paulo',
            'description' => 'Public description',
            'primary_color' => '#22D3EE',
            'logo_media_id' => null,
            'banner_media_id' => null,
        ]);
        $archived = app(ArchiveMediaAsset::class)->handle($scenario['alliance'], $scenario['player'], $asset->id);
        self::assertSame('archived', $archived->lifecycle_status->value);
        $this->get('/alliances/brand-v2-4590/branding/logo')->assertNotFound();
    }

    public function test_cross_alliance_media_cannot_be_attached_to_branding(): void
    {
        Storage::fake('local');
        config()->set('content.media_disk', 'local');
        $factory = new ScenarioFactory;
        $first = $factory->alliance(4591, 'First Brand Owner', 'First Brand V2', 'first-brand-v2-4591');
        $second = $factory->alliance(4592, 'Second Brand Owner', 'Second Brand V2', 'second-brand-v2-4592');
        $secondAsset = app(UploadMediaAsset::class)->handle(
            $second['alliance'],
            $second['player'],
            UploadedFile::fake()->image('other.png'),
        );

        $this->expectException(ValidationException::class);
        app(UpdateAlliancePublicProfile::class)->handle($first['alliance'], $first['player'], [
            'name' => 'First Brand V2',
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
        app()->instance(MediaScanner::class, new class implements MediaScanner
        {
            public function scan(UploadedFile $file): MediaScanResult
            {
                return new MediaScanResult(false, 'Rejected by V2 test scanner.');
            }
        });
        $scenario = (new ScenarioFactory)->alliance(4593, 'Scan Owner', 'Scan V2', 'scan-v2-4593');

        try {
            app(UploadMediaAsset::class)->handle(
                $scenario['alliance'],
                $scenario['player'],
                UploadedFile::fake()->image('blocked.png'),
            );
            self::fail('Rejected media must not be accepted.');
        } catch (ValidationException) {
            self::assertTrue(true);
        }

        $this->assertDatabaseCount('media_assets', 0);
        self::assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $scenario['alliance']->id,
            'event' => 'content.media_rejected',
        ]);
    }
}
