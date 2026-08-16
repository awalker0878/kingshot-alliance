<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class EventRosterArchitectureTest extends TestCase
{
    public function test_roster_schema_uses_durable_player_identity_and_contextual_alliance_snapshot(): void
    {
        $migration = $this->read('database/migrations/2026_08_13_040000_create_event_roster_tables.php');

        self::assertStringContainsString('foreignUlid(\'player_id\')->constrained(\'players\')', $migration);
        self::assertStringContainsString('foreignUlid(\'alliance_id\')->nullable()->constrained(\'alliances\')', $migration);
        self::assertStringNotContainsString('membership_id', $migration);
        self::assertStringContainsString('foreign([\'parent_id\', \'occurrence_id\'])', $migration);
        self::assertStringContainsString('event_roster_members_active_slot_unique', $migration);
    }

    public function test_self_assignment_response_route_has_no_player_identity_parameter(): void
    {
        $routes = $this->read('routes/web.php');

        self::assertStringContainsString('/events/{occurrence}/roster-members/{member}/response', $routes);
        self::assertStringNotContainsString('/roster-members/{member}/players/{player}', $routes);
    }

    public function test_catalogue_defines_stable_roster_shapes_as_database_configuration(): void
    {
        $catalogue = $this->read('app/Domain/Events/Catalog/KingShotEventTypeCatalog.php');

        foreach ([
            "'key' => 'combatants'",
            "'key' => 'substitutes'",
            "'capacity' => 30",
            "'capacity' => 10",
            "'key' => 'legion-1'",
            "'key' => 'legion-2'",
            "'capacity' => 60",
            "'parent_key' => 'legion-1'",
            "'parent_key' => 'legion-2'",
        ] as $required) {
            self::assertStringContainsString($required, $catalogue, $required);
        }
    }

    public function test_rostered_reminders_are_capability_gated_and_resolved_from_active_assignments(): void
    {
        $create = $this->read('app/Contexts/Operations/Reminders/Actions/CreateEventReminderRule.php');
        $resolver = $this->read('app/Contexts/Communications/Reminders/Services/EventReminderAudienceResolver.php');

        self::assertStringContainsString('EventCapability::Rosters', $create);
        self::assertStringContainsString('EventRosterMember::query()', $resolver);
        self::assertStringContainsString('EventRosterMemberStatus::Assigned->value', $resolver);
        self::assertStringContainsString('EventRosterMemberStatus::Confirmed->value', $resolver);
    }

    public function test_event_pages_render_rosters_by_capability(): void
    {
        $show = $this->read('resources/js/pages/Events/Show.vue');
        $manage = $this->read('resources/js/pages/Events/Manage.vue');

        self::assertStringContainsString('event.capabilities.includes(\'rosters\')', $show);
        self::assertStringContainsString('event.capabilities.includes(\'rosters\')', $manage);
        self::assertStringContainsString('rosterOperations', $manage);
        self::assertStringContainsString('roster_confirmation', $this->read('resources/js/pages/Events/Index.vue'));
    }

    private function read(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($source, $path);

        return $source;
    }
}
