<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Contexts\Operations\EventCore\Enums\EventCapability;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use PHPUnit\Framework\TestCase;

final class EventArchitectureVocabularyTest extends TestCase
{
    public function test_event_scopes_are_player_alliance_and_kingdom(): void
    {
        self::assertSame(
            ['player', 'alliance', 'kingdom'],
            array_map(static fn (EventScope $scope): string => $scope->value, EventScope::cases()),
        );
    }

    public function test_initial_capability_vocabulary_is_explicit_and_reusable(): void
    {
        self::assertSame([
            'responses',
            'registration',
            'waitlist',
            'attendance',
            'phases',
            'polls',
            'rosters',
            'substitutes',
            'teams',
            'legions',
            'rally_guidance',
            'formations',
            'objectives',
            'scoring',
            'results',
        ], array_map(static fn (EventCapability $capability): string => $capability->value, EventCapability::cases()));
    }
}
