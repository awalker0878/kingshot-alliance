<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class EventUxSecurityArchitectureTest extends TestCase
{
    public function test_event_navigation_and_calendar_controls_are_permission_and_locale_aware(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = file_get_contents($root.'/app/Domain/Events/Http/Controllers/EventCalendarController.php');
        $index = file_get_contents($root.'/resources/js/pages/Events/Index.vue');

        self::assertIsString($controller);
        self::assertIsString($index);
        self::assertStringContainsString("'canCreate' => \$creationContexts->forUser(\$user) !== []", $controller);
        self::assertStringContainsString('v-if="props.canCreate"', $index);
        self::assertStringContainsString(':aria-pressed="scope === value"', $index);
        self::assertStringContainsString(':aria-pressed="view === \'calendar\'"', $index);
        self::assertStringContainsString("formatDate(date, { weekday: 'short' })", $index);
        self::assertStringNotContainsString("['Sun','Mon','Tue','Wed','Thu','Fri','Sat']", $index);
    }

    public function test_alliance_overview_uses_the_scoped_event_calendar_query(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = file_get_contents($root.'/app/Domain/Alliances/Http/Controllers/AllianceOverviewController.php');

        self::assertIsString($controller);
        self::assertFileExists($root.'/app/Domain/Events/Queries/EventCalendarQuery.php');
        self::assertStringContainsString('use App\\Domain\\Events\\Queries\\EventCalendarQuery;', $controller);
        self::assertStringContainsString('$eventQuery->forAlliance($user, $alliance', $controller);
    }

    public function test_event_target_and_status_presentation_is_localized(): void
    {
        $root = dirname(__DIR__, 2);
        $targetResolver = file_get_contents($root.'/app/Domain/Events/Services/EventTargetResolver.php');
        $creationContexts = file_get_contents($root.'/app/Domain/Events/Services/EventCreationContextResolver.php');
        $create = file_get_contents($root.'/resources/js/pages/Events/Create.vue');
        $show = file_get_contents($root.'/resources/js/pages/Events/Show.vue');
        $manage = file_get_contents($root.'/resources/js/pages/Events/Manage.vue');
        $english = file_get_contents($root.'/resources/js/localization/messages/events/en.ts');

        self::assertIsString($targetResolver);
        self::assertIsString($creationContexts);
        self::assertIsString($create);
        self::assertIsString($show);
        self::assertIsString($manage);
        self::assertIsString($english);
        self::assertStringNotContainsString('Kingdom #', $targetResolver);
        self::assertStringNotContainsString('Kingdom #', $creationContexts);
        self::assertStringContainsString("t('events.scope.kingdom')", $create);
        self::assertStringContainsString('t(`events.scope.${event.scope}`)', $show);
        self::assertStringContainsString('t(`events.attendanceStatuses.${event.participation.attendance.status}`)', $show);
        self::assertStringContainsString('t(`events.occurrenceStatuses.${occurrence.status}`)', $manage);
        self::assertStringContainsString('t(`events.phaseStatuses.${phase.status}`)', $manage);
        self::assertStringContainsString('t(`events.pollStatuses.${poll.status}`)', $manage);
        self::assertStringContainsString('occurrenceStatuses:', $english);
        self::assertStringContainsString('phaseStatuses:', $english);
        self::assertStringContainsString('pollStatuses:', $english);
    }

    public function test_player_switcher_uses_owned_server_context(): void
    {
        $root = dirname(__DIR__, 2);
        $layout = file_get_contents($root.'/resources/js/layouts/AppLayout.vue');

        self::assertIsString($layout);
        self::assertStringContainsString('sharedPlayerContext.players', $layout);
        self::assertStringContainsString('sharedPlayerContext.activePlayerId', $layout);
        self::assertStringContainsString(':aria-pressed="player.id === sharedPlayerContext.activePlayerId"', $layout);
        self::assertStringContainsString('router.post(`/players/${playerId}/activate`', $layout);
    }
}
