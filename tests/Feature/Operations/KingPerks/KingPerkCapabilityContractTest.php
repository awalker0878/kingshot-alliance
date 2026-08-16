<?php

declare(strict_types=1);

namespace Tests\Feature\Operations\KingPerks;

use App\Contexts\Operations\EventCore\Enums\EventCapability;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Models\EventType;
use App\Contexts\Operations\EventCore\Services\EventCapabilityResolver;
use App\Contexts\Operations\EventCore\Services\EventTypeRegistry;
use App\Contexts\Operations\KingPerks\Catalog\KingPerkEventCapabilityCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class KingPerkCapabilityContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_kingdom_of_power_exposes_king_perks_as_an_operations_capability(): void
    {
        $type = EventType::query()->where('slug', KingPerkEventCapabilityCatalog::eventTypeSlug())->sole();
        $scope = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Kingdom);
        $resolver = $this->app->make(EventCapabilityResolver::class);

        self::assertTrue($resolver->supports($scope, EventCapability::KingPerks));
        self::assertSame(
            KingPerkEventCapabilityCatalog::configuration(),
            $resolver->configuration($scope, EventCapability::KingPerks),
        );
    }
}
