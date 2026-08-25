<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Operations\Events;

use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeCapability;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Events\Services\EventCapabilityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\TestCase;

final class EventCapabilityResolverV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_keys_normalize_cast_capability_enums_to_string_values(): void
    {
        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas(
                'eventType',
                static fn ($query) => $query->where('slug', 'alliance-mobilization'),
            )
            ->firstOrFail();

        $expected = $configuration->capabilities()
            ->orderBy('capability')
            ->get()
            ->map(static fn (EventTypeCapability $row): string => $row->capabilityEnum()->value)
            ->values()
            ->all();

        self::assertNotEmpty($expected);
        self::assertSame($expected, app(EventCapabilityResolver::class)->keys($configuration));
    }
}
