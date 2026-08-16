<?php

declare(strict_types=1);

namespace Tests\Feature\Operations\Reminders;

use App\Contexts\Operations\Reminders\Actions\CreateEventReminderRule;
use App\Contexts\Operations\Reminders\Actions\DisableEventReminderRule;
use App\Contexts\Operations\Reminders\Enums\EventReminderAudience;
use App\Contexts\Operations\Reminders\Models\EventReminderRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\V2\ScenarioFactory;
use Tests\TestCase;

final class EventReminderRuleContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_creates_one_idempotent_in_app_rule_for_an_event_scope(): void
    {
        $scenario = (new ScenarioFactory)->allianceEvent(4410, 'custom', 'reminder-rule-v2');
        $create = app(CreateEventReminderRule::class);

        $first = $create->handle(
            $scenario['player'],
            $scenario['event'],
            30,
            EventReminderAudience::AllScopePlayers,
        );
        $second = $create->handle(
            $scenario['player'],
            $scenario['event'],
            30,
            EventReminderAudience::AllScopePlayers,
        );

        self::assertTrue($first->is($second));
        self::assertTrue($first->is_enabled);
        self::assertSame(1, EventReminderRule::query()->count());
        self::assertSame(1, DB::table('audit_events')->where('event', 'event.reminder.rule.created')->count());
        self::assertSame(1, DB::table('outbox_messages')->where('event_type', 'event.reminder.rule.created')->count());
    }

    public function test_reminder_policy_rejects_invalid_lead_time_channel_and_target_audience_for_alliance_event(): void
    {
        $scenario = (new ScenarioFactory)->allianceEvent(4411, 'custom', 'reminder-policy-v2');
        $create = app(CreateEventReminderRule::class);

        foreach ([
            [0, EventReminderAudience::AllScopePlayers, 'in_app'],
            [10081, EventReminderAudience::AllScopePlayers, 'in_app'],
            [30, EventReminderAudience::AllScopePlayers, 'email'],
            [30, EventReminderAudience::Target, 'in_app'],
        ] as [$minutes, $audience, $channel]) {
            try {
                $create->handle($scenario['player'], $scenario['event'], $minutes, $audience, $channel);
                self::fail('Invalid reminder definition must be rejected.');
            } catch (ValidationException) {
                self::assertTrue(true);
            }
        }

        self::assertSame(0, EventReminderRule::query()->count());
    }

    public function test_disable_is_idempotent_and_recreating_the_definition_reenables_the_same_rule(): void
    {
        $scenario = (new ScenarioFactory)->allianceEvent(4412, 'custom', 'reminder-reenable-v2');
        $create = app(CreateEventReminderRule::class);
        $disable = app(DisableEventReminderRule::class);
        $rule = $create->handle($scenario['player'], $scenario['event'], 45, EventReminderAudience::AllScopePlayers);

        $disabled = $disable->handle($scenario['player'], $scenario['event'], $rule);
        $again = $disable->handle($scenario['player'], $scenario['event'], $rule);
        self::assertFalse($disabled->is_enabled);
        self::assertTrue($disabled->is($again));
        self::assertSame(1, DB::table('audit_events')->where('event', 'event.reminder.rule.disabled')->count());

        $reenabled = $create->handle($scenario['player'], $scenario['event'], 45, EventReminderAudience::AllScopePlayers);
        self::assertTrue($rule->is($reenabled));
        self::assertTrue($reenabled->is_enabled);
        self::assertSame(1, DB::table('audit_events')->where('event', 'event.reminder.rule.enabled')->count());
    }
}
