<?php

declare(strict_types=1);

namespace Tests\ReadModels\PlatformAdministration\Fixtures;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Platform\Administration\Actions\ManagePlatformAdministrator;
use App\ReadModels\PlatformAdministration\PlatformCatalogueKind;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\ReadModels\PlatformAdministration\Support\PlatformCatalogueFixture;
use Tests\Support\ScenarioFactory;

final class PlatformCatalogueVisualFixture
{
    public static function seed(): void
    {
        $existing = DB::table('platform_administrators')->whereNull('revoked_at')->value('user_id');
        $authority = $existing === null ? null : app(AccountIdentityQuery::class)->require((int) $existing);
        foreach (['desktop', 'mobile'] as $project) {
            $user = User::factory()->create(['name' => 'Platform operator '.$project,
                'email' => 'platform-catalogues-'.$project.'@example.test', 'password' => Hash::make('password'),
                'email_verified_at' => now(), 'two_factor_confirmed_at' => now(), 'timezone' => 'UTC']);
            app(ManagePlatformAdministrator::class)->grant((int) $user->id, $authority);
            $authority ??= app(AccountIdentityQuery::class)->require((int) $user->id);
        }
        $factory = app(ScenarioFactory::class);
        $player = $factory->player($authority->userId, 61691, 'platform-catalogue-owner');
        $alliance = $factory->alliance($player);
        $ids = PlatformCatalogueFixture::seed(PlatformCatalogueKind::Alliances, $authority->userId, $alliance, $player->playerId);
        $selected = app(AllianceReferenceQuery::class)->require($ids[0]);
        foreach ([PlatformCatalogueKind::Features, PlatformCatalogueKind::OutboxFailures, PlatformCatalogueKind::LegalHolds] as $kind) {
            PlatformCatalogueFixture::seed($kind, $authority->userId, $selected, $player->playerId);
        }
    }
}
