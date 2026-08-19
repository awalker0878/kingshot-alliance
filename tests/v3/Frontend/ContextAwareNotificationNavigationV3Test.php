<?php

declare(strict_types=1);

namespace Tests\v3\Frontend;

use PHPUnit\Framework\TestCase;

final class ContextAwareNotificationNavigationV3Test extends TestCase
{
    public function test_notification_links_activate_the_target_governor_before_following_the_route(): void
    {
        $root = dirname(__DIR__, 3);
        $navigation = $this->source($root.'/resources/js/composables/useGovernorNavigation.ts');
        $link = $this->source($root.'/resources/js/components/navigation/GovernorContextLink.vue');
        $events = $this->source($root.'/resources/js/pages/Operations/Events/Index.vue');

        foreach ([
            'safeLocalPath',
            'governors.value.some',
            'governor.value?.id === intent.governorId',
            'beginContextTransition',
            '`/players/${intent.governorId}/activate`',
            '{ returnTo: destination }',
            'preserveState: false',
            'preserveScroll: false',
            'cancelContextTransition',
            'completeContextTransition',
        ] as $expected) {
            self::assertStringContainsString($expected, $navigation);
        }

        self::assertStringContainsString('useGovernorNavigation', $link);
        self::assertStringContainsString('@auxclick.prevent', $link);
        self::assertStringContainsString('event.preventDefault()', $link);
        self::assertStringContainsString('GovernorContextLink', $events);
        self::assertStringContainsString(':governor-id="reminder.playerId"', $events);
        self::assertStringContainsString(':href="reminder.href"', $events);
        self::assertStringContainsString(':governor-id="item.playerId"', $events);
    }

    public function test_notification_read_model_emits_scalar_governor_and_safe_route_intent(): void
    {
        $root = dirname(__DIR__, 3);
        $query = $this->source($root.'/app/ReadModels/EventCalendar/Queries/EventReminderInboxQuery.php');

        self::assertStringContainsString('PlayerReference', $query);
        self::assertStringNotContainsString('Players\\Models\\Player', $query);
        self::assertStringContainsString('$player->userId', $query);
        self::assertStringContainsString("'playerId' => (string) \$delivery->player_id", $query);
        self::assertStringContainsString("'href' => \$this->routeIntent", $query);
        self::assertStringContainsString("! str_starts_with(\$candidate, '/')", $query);
        self::assertStringContainsString("str_starts_with(\$candidate, '//')", $query);
    }

    private function source(string $path): string
    {
        self::assertFileExists($path);
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}
