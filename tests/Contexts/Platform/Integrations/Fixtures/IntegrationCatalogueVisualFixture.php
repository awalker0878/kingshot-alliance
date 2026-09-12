<?php

declare(strict_types=1);

namespace Tests\Contexts\Platform\Integrations\Fixtures;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\Actions\CreateAlliance;
use Illuminate\Support\Facades\Hash;
use Tests\Contexts\Platform\Integrations\Support\IntegrationCatalogueFixture;
use Tests\Support\ScenarioFactory;

final class IntegrationCatalogueVisualFixture
{
    public static function seed(): void
    {
        foreach (['desktop', 'mobile'] as $offset => $project) {
            $factory = new ScenarioFactory;
            $user = User::factory()->create(['name' => 'Connections captain '.$project,
                'email' => 'integration-catalogues-'.$project.'@example.test', 'password' => Hash::make('password'),
                'email_verified_at' => now(), 'timezone' => 'UTC']);
            $actor = $factory->player((int) $user->id, 61681 + $offset, 'integration-catalogues-'.$project);
            $allianceId = app(CreateAlliance::class)->handle((int) $user->id, $actor->playerId,
                'Integration Catalogues '.$project, 'integration-catalogues-'.$project);
            IntegrationCatalogueFixture::seed($allianceId, $actor->playerId);
        }
    }
}
