<?php

declare(strict_types=1);

namespace Tests\Feature\Intelligence\Observations\V2;

use App\Contexts\Intelligence\Observations\Actions\InvalidateKingdomAllianceObservation;
use App\Contexts\Intelligence\Observations\Actions\RecordKingdomAllianceObservation;
use App\Contexts\Intelligence\Observations\Actions\StartTrackingKingdomAlliance;
use App\Contexts\Intelligence\Observations\Models\KingdomAllianceObservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\V2\ScenarioFactory;
use Tests\TestCase;

final class ObservationLifecycleV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_tracking_is_owned_by_intelligence_and_is_unique_per_active_game_alliance(): void
    {
        $scenario = (new ScenarioFactory)->alliance(4301, 'Observer', 'Observation Owners', 'observation-owners');
        $action = app(StartTrackingKingdomAlliance::class);

        $tracking = $action->handle($scenario['alliance'], $scenario['player'], [
            'current_name' => 'Northern Watch',
            'current_tag' => 'NW',
            'game_alliance_id' => 'ks-4301-nw',
            'manager_notes' => 'priority target',
        ]);

        self::assertSame($scenario['alliance']->id, $tracking->alliance_id);
        self::assertSame($scenario['kingdom']->id, $tracking->kingdom_id);
        self::assertSame('Northern Watch', $tracking->kingdomAlliance->current_name);
        self::assertSame(1, DB::table('audit_events')->where('event', 'kingdoms.alliance_intelligence_tracking_started')->count());
        self::assertSame(1, DB::table('outbox_messages')->where('event_type', 'kingdoms.alliance_intelligence_tracking_started')->count());

        $this->expectException(ValidationException::class);
        $action->handle($scenario['alliance'], $scenario['player'], [
            'current_name' => 'Northern Watch',
            'current_tag' => 'NW',
            'game_alliance_id' => 'ks-4301-nw',
        ]);
    }

    public function test_manual_observation_retry_is_idempotent_and_updates_the_neutral_reference(): void
    {
        $scenario = (new ScenarioFactory)->alliance(4302, 'Observer', 'Observation Retry', 'observation-retry');
        $tracking = app(StartTrackingKingdomAlliance::class)->handle($scenario['alliance'], $scenario['player'], [
            'current_name' => 'Retry Target',
            'game_alliance_id' => 'ks-4302-retry',
        ]);
        $payload = [
            'observed_name' => 'Retry Target Current',
            'observed_tag' => 'RTC',
            'power' => '123456789',
            'member_count' => 99,
            'captured_at' => now()->subMinute()->toIso8601String(),
        ];

        $record = app(RecordKingdomAllianceObservation::class);
        $first = $record->handle($scenario['alliance'], $scenario['player'], (string) $tracking->id, $payload);
        $second = $record->handle($scenario['alliance'], $scenario['player'], (string) $tracking->id, $payload);

        self::assertTrue($first->is($second));
        self::assertSame(1, KingdomAllianceObservation::query()->count());
        self::assertSame('Retry Target Current', $tracking->kingdomAlliance->refresh()->current_name);
        self::assertSame('RTC', $tracking->kingdomAlliance->current_tag);
        self::assertSame(1, DB::table('audit_events')->where('event', 'kingdoms.alliance_intelligence_observation_recorded')->count());
        self::assertSame(1, DB::table('outbox_messages')->where('event_type', 'kingdoms.alliance_intelligence_observation_recorded')->count());
    }

    public function test_zero_metrics_are_distinct_from_missing_metrics_and_future_or_overflow_values_are_rejected(): void
    {
        $scenario = (new ScenarioFactory)->alliance(4303, 'Observer', 'Observation Bounds', 'observation-bounds');
        $tracking = app(StartTrackingKingdomAlliance::class)->handle($scenario['alliance'], $scenario['player'], [
            'current_name' => 'Bounds Target',
            'game_alliance_id' => 'ks-4303-bounds',
        ]);
        $record = app(RecordKingdomAllianceObservation::class);

        $zero = $record->handle($scenario['alliance'], $scenario['player'], (string) $tracking->id, [
            'observed_name' => 'Bounds Target',
            'power' => '0',
            'member_count' => 0,
            'captured_at' => now()->subMinute()->toIso8601String(),
        ]);
        $missing = $record->handle($scenario['alliance'], $scenario['player'], (string) $tracking->id, [
            'observed_name' => 'Bounds Target',
            'captured_at' => now()->toIso8601String(),
        ]);

        self::assertSame(0, $zero->power);
        self::assertSame(0, $zero->member_count);
        self::assertNull($missing->power);
        self::assertNull($missing->member_count);

        foreach ([
            ['power' => '9223372036854775808', 'captured_at' => now()->toIso8601String()],
            ['power' => '1', 'captured_at' => now()->addMinutes(6)->toIso8601String()],
        ] as $invalid) {
            try {
                $record->handle($scenario['alliance'], $scenario['player'], (string) $tracking->id, [
                    'observed_name' => 'Invalid',
                    ...$invalid,
                ]);
                self::fail('Expected invalid observation to be rejected.');
            } catch (ValidationException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_invalidation_is_idempotent_restores_previous_reference_and_keeps_reason_private_from_events(): void
    {
        $scenario = (new ScenarioFactory)->alliance(4304, 'Observer', 'Observation Invalid', 'observation-invalid');
        $tracking = app(StartTrackingKingdomAlliance::class)->handle($scenario['alliance'], $scenario['player'], [
            'current_name' => 'Invalidation Target',
            'game_alliance_id' => 'ks-4304-invalid',
        ]);
        $record = app(RecordKingdomAllianceObservation::class);
        $record->handle($scenario['alliance'], $scenario['player'], (string) $tracking->id, [
            'observed_name' => 'Accepted Name',
            'power' => '100',
            'captured_at' => now()->subHour()->toIso8601String(),
        ]);
        $latest = $record->handle($scenario['alliance'], $scenario['player'], (string) $tracking->id, [
            'observed_name' => 'Bad Name',
            'power' => '200',
            'captured_at' => now()->subMinute()->toIso8601String(),
        ]);
        $secret = 'private analyst correction';
        $invalidate = app(InvalidateKingdomAllianceObservation::class);

        $first = $invalidate->handle($scenario['alliance'], $scenario['player'], (string) $tracking->id, (string) $latest->id, $secret);
        $second = $invalidate->handle($scenario['alliance'], $scenario['player'], (string) $tracking->id, (string) $latest->id, 'ignored retry');

        self::assertTrue($first->is($second));
        self::assertNotNull($first->invalidated_at);
        self::assertSame($secret, $first->invalidation_reason);
        self::assertSame('Accepted Name', $tracking->kingdomAlliance->refresh()->current_name);
        self::assertSame(1, DB::table('audit_events')->where('event', 'kingdoms.alliance_intelligence_observation_invalidated')->count());
        self::assertSame(1, DB::table('outbox_messages')->where('event_type', 'kingdoms.alliance_intelligence_observation_invalidated')->count());
        self::assertStringNotContainsString($secret, DB::table('audit_events')->where('event', 'kingdoms.alliance_intelligence_observation_invalidated')->pluck('metadata')->implode(' '));
        self::assertStringNotContainsString($secret, DB::table('outbox_messages')->where('event_type', 'kingdoms.alliance_intelligence_observation_invalidated')->pluck('payload')->implode(' '));
    }
}
