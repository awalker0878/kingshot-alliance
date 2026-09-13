<?php

declare(strict_types=1);

namespace Tests\ReadModels\KingdomGovernance\Fixtures;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\Actions\CreateAlliance;
use Illuminate\Support\Facades\Hash;
use Tests\ReadModels\KingdomGovernance\Support\GovernanceCatalogueFixture;
use Tests\Support\ScenarioFactory;

final class GovernanceCatalogueVisualFixture
{
    public static function seed(): void
    {
        $factory = app(ScenarioFactory::class);
        foreach (['desktop' => 846001, 'mobile' => 846002] as $project => $kingdom) {
            $user = User::factory()->create(['name' => 'Governance catalogue '.$project,
                'email' => 'governance-catalogues-'.$project.'@example.test', 'password' => Hash::make('password'),
                'email_verified_at' => now(), 'timezone' => 'UTC']);
            $player = $factory->player((int) $user->id, $kingdom, 'governance-catalogue-owner-'.$project);
            app(CreateAlliance::class)->handle((int) $user->id, $player->playerId, 'Governance catalogue '.$project, 'governance-catalogue-'.$project);
            GovernanceCatalogueFixture::seed($player);
        }
    }
}
