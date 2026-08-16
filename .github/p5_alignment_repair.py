from pathlib import Path
import re
import shutil
import subprocess


def run(*args: str) -> None:
    subprocess.run(args, check=True)


def git_mv(source: str, target: str) -> None:
    Path(target).parent.mkdir(parents=True, exist_ok=True)
    run('git', 'mv', source, target)


def remove_test_methods_containing(text: str, tokens: tuple[str, ...]) -> str:
    pattern = re.compile(r'\n    public function test_[A-Za-z0-9_]+\(\): void\n    \{')
    matches = list(pattern.finditer(text))
    for index in range(len(matches) - 1, -1, -1):
        start = matches[index].start()
        if index + 1 < len(matches):
            end = matches[index + 1].start()
        else:
            helper_candidates = [
                text.find('\n    private function ', start + 1),
                text.find('\n    protected function ', start + 1),
                text.find('\n    public static function ', start + 1),
            ]
            helper_candidates = [candidate for candidate in helper_candidates if candidate != -1]
            end = min(helper_candidates) if helper_candidates else text.rfind('\n}')
        segment = text[start:end]
        if any(token in segment for token in tokens):
            text = text[:start] + text[end:]
    return text


def add_import_after(text: str, anchor: str, import_line: str) -> str:
    if import_line in text:
        return text
    if anchor not in text:
        raise SystemExit(f'Import anchor not found: {anchor}')
    return text.replace(anchor, anchor + import_line, 1)


def roster_expression(alliance: str, name: str, game_player_id: str, indent: str, assignment: str) -> str:
    prefix = f'{indent}{assignment}' if assignment else indent
    continuation = indent + (' ' * (len(assignment) if assignment else 0))
    return (
        f"{prefix}AllianceRosterEntry::query()->create([\n"
        f"{continuation}    'alliance_id' => {alliance}->id,\n"
        f"{continuation}    'player_id' => Player::query()->where('game_player_id', {game_player_id})->sole()->id,\n"
        f"{continuation}    'observed_name' => {name},\n"
        f"{continuation}    'state' => RosterState::Active,\n"
        f"{continuation}    'joined_at' => now(),\n"
        f"{continuation}    'last_observed_at' => now(),\n"
        f"{continuation}    'source' => 'manual',\n"
        f"{continuation}]);"
    )


def rewrite_roster_fixtures(text: str) -> str:
    if 'SaveRosterEntry' not in text:
        return text

    service_vars = set(re.findall(
        r'\$(\w+)\s*=\s*\$this->app->make\(SaveRosterEntry::class\);',
        text,
    ))
    text = re.sub(
        r'^[ \t]*\$\w+\s*=\s*\$this->app->make\(SaveRosterEntry::class\);\n?',
        '',
        text,
        flags=re.MULTILINE,
    )

    direct = re.compile(
        r'(?P<indent>^[ \t]*)(?P<assignment>\$\w+\s*=\s*)?'
        r'\$this->app->make\(SaveRosterEntry::class\)->handle\(\s*'
        r'(?P<alliance>\$\w+)\s*,\s*\$\w+\s*,\s*\[\s*'
        r"'name'\s*=>\s*(?P<name>[^,\]]+)\s*,\s*"
        r"'game_player_id'\s*=>\s*(?P<gid>[^\]\n]+)\s*\]\s*\);",
        flags=re.MULTILINE | re.DOTALL,
    )

    def replace_direct(match: re.Match[str]) -> str:
        return roster_expression(
            match.group('alliance'),
            match.group('name').strip(),
            match.group('gid').strip(),
            match.group('indent'),
            match.group('assignment') or '',
        )

    text = direct.sub(replace_direct, text)

    for variable in service_vars:
        via_var = re.compile(
            rf'(?P<indent>^[ \t]*)(?P<assignment>\$\w+\s*=\s*)?\${re.escape(variable)}->handle\(\s*'
            r'(?P<alliance>\$\w+)\s*,\s*\$\w+\s*,\s*\[\s*'
            r"'name'\s*=>\s*(?P<name>[^,\]]+)\s*,\s*"
            r"'game_player_id'\s*=>\s*(?P<gid>[^\]\n]+)\s*\]\s*\);",
            flags=re.MULTILINE | re.DOTALL,
        )
        text = via_var.sub(replace_direct, text)

    text = text.replace('use App\\Domain\\Kingdoms\\Actions\\SaveRosterEntry;\n', '')
    text = add_import_after(
        text,
        'use App\\Contexts\\Alliance\\Core\\Actions\\CreateAlliance;\n',
        'use App\\Contexts\\Alliance\\Membership\\Enums\\RosterState;\n'
        'use App\\Contexts\\Alliance\\Membership\\Models\\AllianceRosterEntry;\n',
    )

    if 'SaveRosterEntry' in text:
        raise SystemExit('Unconverted SaveRosterEntry fixture remains')
    return text


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

mixed_event_tests = {
    'EventPhasePollTest.php',
    'EventParticipationTest.php',
    'EventRosterTest.php',
}
for source in mixed_event_tests:
    original = Path('tests/Feature/Events') / source
    target = Path('tests/RewriteInput/Communications/EventReminders') / source
    target.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(original, target)
    rewrite = target.read_text().replace(
        'namespace Tests\\Feature\\Events;',
        'namespace Tests\\RewriteInput\\Communications\\EventReminders;',
    )
    target.write_text(rewrite)
    run('git', 'add', str(target))

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
if Path('tests/Feature/Events').exists():
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

model_namespace_rewrites = {
    'App\\Contexts\\Operations\\EventCore\\Models\\EventPoll;': 'App\\Contexts\\Operations\\Polls\\Models\\EventPoll;',
    'App\\Contexts\\Operations\\EventCore\\Models\\EventPollVote;': 'App\\Contexts\\Operations\\Polls\\Models\\EventPollVote;',
    'App\\Contexts\\Operations\\EventCore\\Models\\EventAttendance;': 'App\\Contexts\\Operations\\Participation\\Models\\EventAttendance;',
    'App\\Contexts\\Operations\\EventCore\\Models\\EventPlayerContext;': 'App\\Contexts\\Operations\\Participation\\Models\\EventPlayerContext;',
    'App\\Contexts\\Operations\\EventCore\\Models\\EventRegistration;': 'App\\Contexts\\Operations\\Participation\\Models\\EventRegistration;',
    'App\\Contexts\\Operations\\EventCore\\Models\\EventResponse;': 'App\\Contexts\\Operations\\Participation\\Models\\EventResponse;',
    'App\\Contexts\\Operations\\EventCore\\Models\\EventRosterMember;': 'App\\Contexts\\Operations\\Rosters\\Models\\EventRosterMember;',
    'App\\Contexts\\Operations\\EventCore\\Models\\EventObjective;': 'App\\Contexts\\Operations\\BattlePlans\\Models\\EventObjective;',
    'App\\Contexts\\Operations\\EventCore\\Models\\EventObjectiveAssignment;': 'App\\Contexts\\Operations\\BattlePlans\\Models\\EventObjectiveAssignment;',
    'App\\Contexts\\Operations\\EventCore\\Models\\EventAllianceResultMetric;': 'App\\Contexts\\Operations\\Results\\Models\\EventAllianceResultMetric;',
    'App\\Contexts\\Operations\\EventCore\\Models\\EventMetricDefinition;': 'App\\Contexts\\Operations\\Results\\Models\\EventMetricDefinition;',
    'App\\Contexts\\Operations\\EventCore\\Models\\EventPlayerResultMetric;': 'App\\Contexts\\Operations\\Results\\Models\\EventPlayerResultMetric;',
    'App\\Contexts\\Operations\\EventCore\\Models\\EventResultMetric;': 'App\\Contexts\\Operations\\Results\\Models\\EventResultMetric;',
}

cross_context_tokens = (
    'QueueDueEventReminders',
    'MarkEventReminderSent',
    'EventReminderDelivery',
    'EventReminderInboxQuery',
)
cross_context_imports = (
    'use App\\Contexts\\Communications\\Reminders\\Models\\EventReminderDelivery;\n',
    'use App\\Domain\\Notifications\\Actions\\MarkEventReminderSent;\n',
    'use App\\Domain\\Notifications\\Actions\\QueueDueEventReminders;\n',
    'use App\\ReadModels\\EventCalendar\\Queries\\EventReminderInboxQuery;\n',
)

for capability, namespace in namespaces.items():
    directory = Path('tests/Feature/Operations') / capability
    if not directory.exists():
        continue
    for path in directory.glob('*.php'):
        text = path.read_text().replace('namespace Tests\\Feature\\Events;', f'namespace {namespace};')
        for old, new in model_namespace_rewrites.items():
            text = text.replace(old, new)
        if path.name in mixed_event_tests:
            text = remove_test_methods_containing(text, cross_context_tokens)
            for import_line in cross_context_imports:
                text = text.replace(import_line, '')
        text = rewrite_roster_fixtures(text)
        path.write_text(text)

for path in Path('tests/RewriteInput/Intelligence/EventAnalysis').glob('*.php'):
    path.write_text(path.read_text().replace(
        'namespace Tests\\Feature\\Events;',
        'namespace Tests\\RewriteInput\\Intelligence\\EventAnalysis;',
    ))

# King Perks: keep Operations scheduling/temporal behavior authoritative and stage
# the reminder-delivery behavior for the Communications phase.
Path('tests/RewriteInput/Communications/KingPerks').mkdir(parents=True, exist_ok=True)
source_operational = Path('tests/Feature/KingPerks/KingPerkOperationalContractTest.php')
rewrite_target = Path('tests/RewriteInput/Communications/KingPerks/KingPerkReminderDeliveryRewriteInputTest.php')
shutil.copyfile(source_operational, rewrite_target)
rewrite_text = rewrite_target.read_text().replace(
    'namespace Tests\\Feature\\KingPerks;',
    'namespace Tests\\RewriteInput\\Communications\\KingPerks;',
).replace(
    'final class KingPerkOperationalContractTest extends TestCase',
    'final class KingPerkReminderDeliveryRewriteInputTest extends TestCase',
)
rewrite_target.write_text(rewrite_text)
run('git', 'add', str(rewrite_target))

git_mv(str(source_operational), 'tests/Feature/Operations/KingPerks/KingPerkOperationalContractTest.php')
git_mv('tests/Feature/KingPerks/KingPerkTemporalGuardTest.php', 'tests/Feature/Operations/KingPerks/KingPerkTemporalGuardTest.php')
run('git', 'rm', 'tests/Feature/KingPerks/KingPerkCapabilityTest.php')

operational = Path('tests/Feature/Operations/KingPerks/KingPerkOperationalContractTest.php')
text = operational.read_text().replace(
    'namespace Tests\\Feature\\KingPerks;',
    'namespace Tests\\Feature\\Operations\\KingPerks;',
)
text = remove_test_methods_containing(text, (
    'QueueDueKingPerkReminders',
    'KingPerkReminderDelivery',
))
for import_line in (
    'use App\\Contexts\\Communications\\Reminders\\Models\\KingPerkReminderDelivery;\n',
    'use App\\Contexts\\Operations\\KingPerks\\Enums\\KingPerkReminderKind;\n',
    'use App\\Domain\\Notifications\\Actions\\QueueDueKingPerkReminders;\n',
    'use Illuminate\\Support\\Carbon;\n',
):
    text = text.replace(import_line, '')
operational.write_text(text)

temporal = Path('tests/Feature/Operations/KingPerks/KingPerkTemporalGuardTest.php')
temporal.write_text(temporal.read_text().replace(
    'namespace Tests\\Feature\\KingPerks;',
    'namespace Tests\\Feature\\Operations\\KingPerks;',
))

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
    source = path.read_text()
    if 'App\\Domain\\' in source:
        raise SystemExit(f'V1 namespace remains in Operations acceptance: {path}')
    if 'App\\Contexts\\Communications\\' in source:
        raise SystemExit(f'Communications dependency remains in Operations acceptance: {path}')
    if 'App\\ReadModels\\' in source:
        raise SystemExit(f'ReadModel dependency remains in Operations acceptance: {path}')
