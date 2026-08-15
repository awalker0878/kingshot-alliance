<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Domain\Events\Catalog\KingShotEventMetricCatalog;
use App\Domain\Events\Catalog\KingShotEventTypeCatalog;
use App\Domain\Events\Enums\EventMetricSubject;
use App\Domain\Events\Enums\EventScope;
use PHPUnit\Framework\TestCase;

final class EventMetricCatalogTest extends TestCase
{
    public function test_every_supported_event_scope_resolves_a_valid_measurement_profile(): void
    {
        foreach (KingShotEventTypeCatalog::definitions() as $definition) {
            foreach ($definition['scopes'] as $scope) {
                $profile = KingShotEventMetricCatalog::profile(
                    $definition['slug'],
                    $scope['scope'],
                    $scope['capabilities'],
                );

                $keys = [];
                foreach ($profile['metrics'] as $metric) {
                    $identity = $metric['subject']->value.':'.$metric['key'];
                    self::assertNotContains($identity, $keys, $definition['slug'].':'.$scope['scope']->value.':'.$identity);
                    $keys[] = $identity;

                    self::assertStringStartsWith('events.metrics.', $metric['label_key']);
                    self::assertGreaterThanOrEqual(0, $metric['sort_order']);
                    self::assertTrue($metric['is_contribution_metric']);
                }

                if ($profile['score'] !== null) {
                    self::assertStringStartsWith('events.metrics.', $profile['score']['label_key']);
                }
            }
        }
    }

    public function test_bear_hunt_uses_damage_as_score_and_tracks_rally_contribution(): void
    {
        $profile = $this->profile('bear-hunt', EventScope::Alliance);
        $score = $profile['score'];
        self::assertNotNull($score);

        self::assertSame('events.metrics.damage', $score['label_key']);
        self::assertSame('damage', $score['unit']);
        self::assertSame(
            ['rallies_joined', 'rallies_led'],
            array_column($profile['metrics'], 'key'),
        );
    }

    public function test_viking_vengeance_tracks_wave_execution_without_duplicate_score_metric(): void
    {
        $profile = $this->profile('viking-vengeance', EventScope::Alliance);
        $score = $profile['score'];
        self::assertNotNull($score);

        self::assertSame('points', $score['unit']);
        self::assertSame(
            ['waves_defended', 'defense_failures'],
            array_column($profile['metrics'], 'key'),
        );
        self::assertFalse($profile['metrics'][1]['higher_is_better']);
    }

    public function test_battlefield_events_track_player_kills_capture_and_occupation_components(): void
    {
        foreach (['swordland-showdown', 'tri-alliance-clash', 'swordland-summit-league'] as $slug) {
            $profile = $this->profile($slug, EventScope::Alliance);

            self::assertSame(
                ['kills', 'objective_captures', 'objective_occupation_seconds'],
                array_column($profile['metrics'], 'key'),
                $slug,
            );
            self::assertSame('objective', $profile['metrics'][2]['dimension_kind']);
        }
    }

    public function test_castle_kingdom_scope_has_alliance_and_player_point_components(): void
    {
        $profile = $this->profile('castle-battle', EventScope::Kingdom);
        $bySubject = [];

        foreach ($profile['metrics'] as $metric) {
            $bySubject[$metric['subject']->value][] = $metric['key'];
        }

        self::assertSame(['objective_occupation_seconds'], $bySubject[EventMetricSubject::Event->value]);
        self::assertSame(
            ['carnage_points', 'occupation_points', 'casualty_points'],
            $bySubject[EventMetricSubject::Alliance->value],
        );
        self::assertSame(
            ['carnage_points', 'occupation_points', 'casualty_points'],
            $bySubject[EventMetricSubject::Player->value],
        );
    }

    public function test_kingdom_of_power_phase_points_are_dimensioned_for_event_alliance_and_player(): void
    {
        $profile = $this->profile('kingdom-of-power', EventScope::Kingdom);
        $score = $profile['score'];
        self::assertNotNull($score);

        self::assertSame('events.metrics.total_points', $score['label_key']);
        self::assertCount(3, $profile['metrics']);
        self::assertSame(
            ['event', 'alliance', 'player'],
            array_map(static fn (array $metric): string => $metric['subject']->value, $profile['metrics']),
        );

        foreach ($profile['metrics'] as $metric) {
            self::assertSame('phase_points', $metric['key']);
            self::assertSame('phase', $metric['dimension_kind']);
        }
    }

    public function test_custom_and_fishing_do_not_invent_component_metrics(): void
    {
        foreach ([
            ['custom', EventScope::Player],
            ['custom', EventScope::Alliance],
            ['custom', EventScope::Kingdom],
            ['fishing-tournament', EventScope::Player],
        ] as [$slug, $scope]) {
            $profile = $this->profile($slug, $scope);
            self::assertSame([], $profile['metrics'], $slug.':'.$scope->value);
        }
    }

    /** @return array{score:?array<string,mixed>,metrics:list<array<string,mixed>>} */
    private function profile(string $slug, EventScope $scope): array
    {
        foreach (KingShotEventTypeCatalog::definitions() as $definition) {
            if ($definition['slug'] !== $slug) {
                continue;
            }

            foreach ($definition['scopes'] as $configuration) {
                if ($configuration['scope'] !== $scope) {
                    continue;
                }

                return KingShotEventMetricCatalog::profile(
                    $slug,
                    $scope,
                    $configuration['capabilities'],
                );
            }
        }

        self::fail('Missing Event Type scope '.$slug.':'.$scope->value);
    }
}
