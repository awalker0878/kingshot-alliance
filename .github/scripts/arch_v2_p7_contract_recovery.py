from pathlib import Path


def replace(path: str, old: str, new: str, *, count: int | None = None) -> None:
    target = Path(path)
    if not target.is_file():
        raise RuntimeError(f'Missing contract file: {path}')
    source = target.read_text(encoding='utf-8')
    hits = source.count(old)
    expected = count if count is not None else 1
    if hits != expected:
        raise RuntimeError(f'{path}: expected {expected} occurrence(s), found {hits}: {old!r}')
    target.write_text(source.replace(old, new, expected), encoding='utf-8')


# P5 moved reminder policy into Operations and P7 moved delivery into
# Communications. Keep the architecture assertion on the real owners.
replace(
    'tests/Architecture/EventRosterArchitectureTest.php',
    "app/Domain/Notifications/Actions/CreateEventReminderRule.php",
    "app/Contexts/Operations/Reminders/Actions/CreateEventReminderRule.php",
)
replace(
    'tests/Architecture/EventRosterArchitectureTest.php',
    "app/Domain/Notifications/Services/EventReminderAudienceResolver.php",
    "app/Contexts/Communications/Reminders/Services/EventReminderAudienceResolver.php",
)

# Replace the obsolete pre-P6 Kingdom-roster structure contract with the final
# ownership rule. Transfers remain deliberately outside this assertion until P8.
Path('tests/Architecture/KingdomRosterStructureTest.php').write_text(r'''<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class KingdomRosterStructureTest extends TestCase
{
    public function test_roster_runtime_is_owned_by_game_world_and_intelligence(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileExists($root.'/app/Contexts/GameWorld/Models/Player.php');
        self::assertDirectoryExists($root.'/app/Contexts/Intelligence/Roster/Actions');
        self::assertDirectoryExists($root.'/app/Contexts/Intelligence/Roster/Models');
        self::assertDirectoryExists($root.'/app/Contexts/Intelligence/Roster/Queries');
        self::assertDirectoryExists($root.'/app/Contexts/Intelligence/Roster/Services');
        self::assertDirectoryExists($root.'/app/Contexts/Intelligence/Roster/Http');

        foreach ([
            'ResolvePlayer.php',
            'SaveRosterEntry.php',
            'MarkRosterEntryLeft.php',
            'RecordPlayerSnapshot.php',
            'PreviewRosterCsvImport.php',
            'CommitRosterCsvImport.php',
        ] as $action) {
            self::assertFileExists($root.'/app/Contexts/Intelligence/Roster/Actions/'.$action);
        }

        self::assertFileDoesNotExist($root.'/app/Domain/Kingdoms/Models/Player.php');
        self::assertFileDoesNotExist($root.'/app/Domain/Kingdoms/Actions/SaveRosterEntry.php');
    }

    public function test_unapproved_follow_on_kingdoms_runtime_is_not_reintroduced(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertDirectoryDoesNotExist($root.'/app/Domain/Kingdoms/Diplomacy');
        self::assertDirectoryDoesNotExist($root.'/app/Domain/Kingdoms/Ingestion');
        self::assertFileDoesNotExist($root.'/app/Domain/Kingdoms/Http/Controllers/KingdomApiController.php');
    }

    public function test_roster_and_intelligence_are_not_registered_as_public_api_contracts(): void
    {
        $root = dirname(__DIR__, 2);
        $apiRoutes = file_get_contents($root.'/routes/api.php');
        self::assertIsString($apiRoutes);

        self::assertStringNotContainsString('/kingdoms', $apiRoutes);
        self::assertStringNotContainsString('/roster', $apiRoutes);
        self::assertStringNotContainsString('kingdoms:', $apiRoutes);
    }

    public function test_internal_events_are_deny_by_default_for_webhook_fanout(): void
    {
        $root = dirname(__DIR__, 2);
        $fanout = file_get_contents($root.'/app/Contexts/Platform/Integrations/Actions/QueueWebhookDeliveries.php');
        $catalog = file_get_contents($root.'/app/Contexts/Platform/Integrations/Contracts/WebhookEventCatalog.php');
        self::assertIsString($fanout);
        self::assertIsString($catalog);

        self::assertStringContainsString('WebhookEventCatalog::isPublic($eventType)', $fanout);
        self::assertStringContainsString("'alliance.created'", $catalog);
        self::assertStringContainsString("'member.joined'", $catalog);
        self::assertStringNotContainsString("'kingdoms.", $catalog);
        self::assertStringNotContainsString("'intelligence.", $catalog);
    }
}
''', encoding='utf-8')

# P6 moved Contributions and observation tracking into Intelligence; recover the
# old tests onto those real V2 classes rather than adding aliases.
contribution_test = Path('tests/Feature/Contributions/AllianceContributionReportQueryTest.php')
source = contribution_test.read_text(encoding='utf-8')
source = source.replace(
    'App\\Domain\\Contributions\\',
    'App\\Contexts\\Intelligence\\Contributions\\',
)
contribution_test.write_text(source, encoding='utf-8')

for path in (
    'tests/Feature/Kingdoms/KingdomAllianceDiplomacyContactTest.php',
    'tests/Feature/Kingdoms/KingdomAllianceDiplomacyTest.php',
    'tests/Feature/Kingdoms/KingdomAllianceTrackingTest.php',
):
    target = Path(path)
    text = target.read_text(encoding='utf-8')
    text = text.replace(
        'App\\Domain\\Kingdoms\\Models\\TrackedKingdomAlliance',
        'App\\Contexts\\Intelligence\\Observations\\Models\\TrackedKingdomAlliance',
    )
    target.write_text(text, encoding='utf-8')

# Transfers are a P8 owner, but their remaining models must already point at the
# final GameWorld aggregate instead of a deleted Domain/Kingdoms Kingdom class.
replace(
    'app/Domain/Kingdoms/Models/TransferPlan.php',
    'use App\\Contexts\\Alliance\\Core\\Models\\Alliance;',
    'use App\\Contexts\\Alliance\\Core\\Models\\Alliance;\nuse App\\Contexts\\GameWorld\\Models\\Kingdom;',
)

# Recover the integration fixture to the one-Alliance-one-Kingdom invariant.
outbox_test = 'tests/Integration/Platform/OutboxPublisherTest.php'
replace(
    outbox_test,
    'use App\\Contexts\\Alliance\\Core\\Models\\Alliance;',
    'use App\\Contexts\\Alliance\\Core\\Models\\Alliance;\nuse App\\Contexts\\GameWorld\\Models\\Kingdom;',
)
replace(
    outbox_test,
    """        $creator = User::factory()->create();
        $alliance = Alliance::query()->create([
            'name' => 'Outbox Test Alliance',""",
    """        $creator = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 9901, 'status' => 'active']);
        $alliance = Alliance::query()->create([
            'kingdom_id' => $kingdom->id,
            'name' => 'Outbox Test Alliance',""",
)

print('Recovered P5/P6 architecture and behavior contracts exposed by P7.')
