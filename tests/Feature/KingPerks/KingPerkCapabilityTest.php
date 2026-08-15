<?php

declare(strict_types=1);

namespace Tests\Feature\KingPerks;

use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\EventType;
use App\Domain\Events\Services\EventCapabilityResolver;
use App\Domain\Events\Services\EventTypeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class KingPerkCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_kingdom_of_power_kingdom_scope_exposes_king_perks_capability(): void
    {
        $type = EventType::query()->where('slug', 'kingdom-of-power')->sole();
        $scope = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Kingdom);

        self::assertTrue(
            $this->app->make(EventCapabilityResolver::class)->supports($scope, EventCapability::KingPerks),
        );
    }
}
