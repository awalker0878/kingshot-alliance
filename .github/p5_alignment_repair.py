from pathlib import Path
import shutil
import subprocess


def run(*args: str) -> None:
    subprocess.run(args, check=True)


def git_mv(source: str, target: str) -> None:
    Path(target).parent.mkdir(parents=True, exist_ok=True)
    run('git', 'mv', source, target)


operations_moves = {
    'EventBattlePlanTest.php': 'BattlePlans/EventBattlePlanTest.php',
    'EventMetricCaptureTest.php': 'Results/EventMetricCaptureTest.php',
    'EventParticipationTest.php': 'Participation/EventParticipationTest.php',
    'EventPhasePollTest.php': 'Polls/EventPhasePollTest.php',
    'EventRallyTest.php': 'Rallies/EventRallyTest.php',
    'EventRosterTest.php': 'Rosters/EventRosterTest.php',
    'EventSchedulingTest.php': 'EventCore/EventSchedulingTest.php',
    'EventScopedAuthorizationTest.php': 'Access/EventScopedAuthorizationTest.php',
    'EventTypeCataloguePersistenceTest.php': 'EventCore/EventTypeCataloguePersistenceTest.php',
}

for source, target in operations_moves.items():
    git_mv(f'tests/Feature/Events/{source}', f'tests/Feature/Operations/{target}')

intelligence_tests = [
    'EventContributionHistorySchemaTest.php',
    'EventContributionIntelligenceQueryTest.php',
    'EventHistoryPerformanceContractTest.php',
    'EventHistorySecurityTest.php',
    'EventLeaderboardQueryTest.php',
    'EventOrganizationEvidenceQueryTest.php',
    'EventOrganizationHistoryQueryTest.php',
    'EventPlayerHistoryQueryTest.php',
    'EventResultsIntelligenceTest.php',
    'EventTrendQueryTest.php',
]
for source in intelligence_tests:
    git_mv(f'tests/Feature/Events/{source}', f'tests/RewriteInput/Intelligence/EventAnalysis/{source}')
Path('tests/Feature/Events').rmdir()

namespaces = {
    'Access': 'Tests\\Feature\\Operations\\Access',
    'BattlePlans': 'Tests\\Feature\\Operations\\BattlePlans',
    'EventCore': 'Tests\\Feature\\Operations\\EventCore',
    'Participation': 'Tests\\Feature\\Operations\\Participation',
    'Polls': 'Tests\\Feature\\Operations\\Polls',
    'Rallies': 'Tests\\Feature\\Operations\\Rallies',
    'Results': 'Tests\\Feature\\Operations\\Results',
    'Rosters': 'Tests\\Feature\\Operations\\Rosters',
}
for capability, namespace in namespaces.items():
    directory = Path('tests/Feature/Operations') / capability
    if directory.exists():
        for path in directory.glob('*.php'):
            path.write_text(path.read_text().replace('namespace Tests\\Feature\\Events;', f'namespace {namespace};'))

for path in Path('tests/RewriteInput/Intelligence/EventAnalysis').glob('*.php'):
    path.write_text(path.read_text().replace(
        'namespace Tests\\Feature\\Events;',
        'namespace Tests\\RewriteInput\\Intelligence\\EventAnalysis;',
    ))

scoped = Path('tests/Feature/Operations/Access/EventScopedAuthorizationTest.php')
text = scoped.read_text()
text = text.replace(
    'use App\\Contexts\\Alliance\\Membership\\Models\\AllianceMembership;\n',
    'use App\\Contexts\\Alliance\\Membership\\Models\\AllianceMembership;\nuse App\\Contexts\\Alliance\\Membership\\Models\\AllianceRosterEntry;\n',
)
text = text.replace('use App\\Domain\\Kingdoms\\Actions\\SaveRosterEntry;\n', '')
old = """        $entry = $this->app->make(SaveRosterEntry::class)->handle($alliance, $ownerPlayer, [
            'name' => 'Player One',
            'game_player_id' => 'player-event-member',
        ]);
        $this->app->make(SaveRosterEntry::class)->handle($alliance, $ownerPlayer, [
            'name' => 'Peer',
            'game_player_id' => 'player-event-peer',
        ]);
"""
new = """        $entry = AllianceRosterEntry::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $memberPlayer->id,
            'observed_name' => 'Player One',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
        ]);
        AllianceRosterEntry::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $peerPlayer->id,
            'observed_name' => 'Peer',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
        ]);
"""
if old not in text:
    raise SystemExit('Expected SaveRosterEntry fixture block not found')
scoped.write_text(text.replace(old, new))

Path('tests/RewriteInput/Communications/KingPerks').mkdir(parents=True, exist_ok=True)
source_operational = Path('tests/Feature/KingPerks/KingPerkOperationalContractTest.php')
rewrite_target = Path('tests/RewriteInput/Communications/KingPerks/KingPerkReminderDeliveryRewriteInputTest.php')
shutil.copyfile(source_operational, rewrite_target)
run('git', 'add', str(rewrite_target))

git_mv(str(source_operational), 'tests/Feature/Operations/KingPerks/KingPerkOperationalContractTest.php')
git_mv('tests/Feature/KingPerks/KingPerkTemporalGuardTest.php', 'tests/Feature/Operations/KingPerks/KingPerkTemporalGuardTest.php')
run('git', 'rm', 'tests/Feature/KingPerks/KingPerkCapabilityTest.php')
Path('tests/Feature/KingPerks').rmdir()

operational = Path('tests/Feature/Operations/KingPerks/KingPerkOperationalContractTest.php')
text = operational.read_text().replace('namespace Tests\\Feature\\KingPerks;', 'namespace Tests\\Feature\\Operations\\KingPerks;')
for line in [
    'use App\\Contexts\\Communications\\Reminders\\Models\\KingPerkReminderDelivery;\n',
    'use App\\Contexts\\Operations\\KingPerks\\Enums\\KingPerkReminderKind;\n',
    'use App\\Domain\\Notifications\\Actions\\QueueDueKingPerkReminders;\n',
    'use Illuminate\\Support\\Carbon;\n',
]:
    text = text.replace(line, '')
start = text.find('    public function test_reminders_are_idempotent_and_resolve_current_kingdom_managers(): void')
end = text.find('    private function kingdom(int $number): Kingdom', start)
if start == -1 or end == -1:
    raise SystemExit('Could not isolate reminder-delivery test from King Perks operational contract')
operational.write_text(text[:start] + text[end:])

temporal = Path('tests/Feature/Operations/KingPerks/KingPerkTemporalGuardTest.php')
temporal.write_text(temporal.read_text().replace(
    'namespace Tests\\Feature\\KingPerks;',
    'namespace Tests\\Feature\\Operations\\KingPerks;',
))

rewrite_text = rewrite_target.read_text().replace(
    'namespace Tests\\Feature\\KingPerks;',
    'namespace Tests\\RewriteInput\\Communications\\KingPerks;',
).replace(
    'final class KingPerkOperationalContractTest extends TestCase',
    'final class KingPerkReminderDeliveryRewriteInputTest extends TestCase',
)
rewrite_target.write_text(rewrite_text)

architecture = Path('tests/Architecture/ArchitectureV2OperationsTest.php')
text = architecture.read_text()
marker = "    #[Test]\n    public function event_reminder_policy_does_not_navigate_into_delivery_state(): void\n"
addition = r'''    #[Test]
    public function kingdom_event_permissions_are_interpreted_by_operations_not_game_world(): void
    {
        $authorization = file_get_contents($this->root().'/app/Contexts/Operations/EventCore/Services/EventAuthorization.php');
        $creation = file_get_contents($this->root().'/app/Contexts/Operations/EventCore/Services/EventCreationMutationAuthority.php');
        $mutation = file_get_contents($this->root().'/app/Contexts/Operations/EventCore/Services/EventMutationAuthority.php');
        $operationsMutation = file_get_contents($this->root().'/app/Contexts/Operations/Access/Services/KingdomOperationsMutationAuthority.php');
        $gameWorldMutation = file_get_contents($this->root().'/app/Contexts/GameWorld/Governance/Services/KingdomMutationAuthority.php');

        foreach ([$authorization, $creation, $mutation, $operationsMutation, $gameWorldMutation] as $source) {
            self::assertIsString($source);
        }

        self::assertStringContainsString('KingdomOperationsAuthorization', $authorization);
        self::assertStringNotContainsString('GameWorld\\Governance\\Services\\KingdomAuthorization', $authorization);
        self::assertStringContainsString('KingdomOperationsMutationAuthority', $creation);
        self::assertStringContainsString('KingdomOperationsMutationAuthority', $mutation);
        self::assertStringNotContainsString('GameWorld\\Governance\\Services\\KingdomMutationAuthority', $creation);
        self::assertStringNotContainsString('GameWorld\\Governance\\Services\\KingdomMutationAuthority', $mutation);
        self::assertStringContainsString('acquireActiveScope', $operationsMutation);
        self::assertStringContainsString('public function acquireActiveScope', $gameWorldMutation);
    }

    #[Test]
    public function p5_acceptance_tests_are_owned_by_operations(): void
    {
        self::assertDirectoryDoesNotExist($this->root().'/tests/Feature/Events');
        self::assertDirectoryDoesNotExist($this->root().'/tests/Feature/KingPerks');
        self::assertDirectoryExists($this->root().'/tests/Feature/Operations/EventCore');
        self::assertDirectoryExists($this->root().'/tests/Feature/Operations/KingPerks');
    }

'''
if marker not in text:
    raise SystemExit('Architecture insertion marker not found')
architecture.write_text(text.replace(marker, addition + marker))

if Path('tests/Feature/Events').exists() or Path('tests/Feature/KingPerks').exists():
    raise SystemExit('Legacy P5 Feature test roots still exist')
for path in Path('tests/Feature/Operations').rglob('*.php'):
    if 'App\\Domain\\' in path.read_text():
        raise SystemExit(f'V1 namespace remains in Operations acceptance: {path}')
