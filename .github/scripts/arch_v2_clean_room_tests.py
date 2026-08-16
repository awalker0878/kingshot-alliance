from __future__ import annotations

import shutil
from pathlib import Path
from textwrap import dedent

ROOT = Path(__file__).resolve().parents[2]
TESTS = ROOT / "tests"

CAPABILITIES = [
    ("Contexts/Accounts/AccountSecurity", ["app/Contexts/Accounts"], "docs/architecture/contexts/accounts/account-security.md"),
    ("Contexts/GameWorld/Identity", ["app/Contexts/GameWorld/Actions", "app/Contexts/GameWorld/Enums", "app/Contexts/GameWorld/Models", "app/Contexts/GameWorld/Services"], "docs/architecture/contexts/game-world/player-context.md"),
    ("Contexts/GameWorld/Governance", ["app/Contexts/GameWorld/Governance"], "docs/architecture/contexts/game-world/kingdom-governance.md"),
    ("Contexts/Alliance/Core", ["app/Contexts/Alliance/Core"], "docs/architecture/contexts/alliance/lifecycle-and-settings.md"),
    ("Contexts/Alliance/Membership", ["app/Contexts/Alliance/Membership"], "docs/architecture/contexts/alliance/membership-and-authority.md"),
    ("Contexts/Alliance/Access", ["app/Contexts/Alliance/Access"], "docs/architecture/contexts/alliance/membership-and-authority.md"),
    ("Contexts/Alliance/Recruitment", ["app/Contexts/Alliance/Recruitment"], "docs/architecture/contexts/alliance/recruitment.md"),
    ("Contexts/Alliance/Content", ["app/Contexts/Alliance/Content"], "docs/architecture/contexts/alliance/content.md"),
    ("Contexts/Operations/EventCore", ["app/Contexts/Operations/EventCore"], "docs/architecture/contexts/operations/event-core.md"),
    ("Contexts/Operations/Participation", ["app/Contexts/Operations/Participation"], "docs/architecture/contexts/operations/participation.md"),
    ("Contexts/Operations/Polls", ["app/Contexts/Operations/Polls"], "docs/architecture/contexts/operations/planning.md"),
    ("Contexts/Operations/Rosters", ["app/Contexts/Operations/Rosters"], "docs/architecture/contexts/operations/planning.md"),
    ("Contexts/Operations/BattlePlans", ["app/Contexts/Operations/BattlePlans"], "docs/architecture/contexts/operations/planning.md"),
    ("Contexts/Operations/Results", ["app/Contexts/Operations/Results"], "docs/architecture/contexts/operations/results.md"),
    ("Contexts/Operations/Rallies", ["app/Contexts/Operations/Rallies"], "docs/architecture/contexts/operations/rallies.md"),
    ("Contexts/Operations/KingPerks", ["app/Contexts/Operations/KingPerks"], "docs/architecture/contexts/operations/king-perks.md"),
    ("Contexts/Operations/Reminders", ["app/Contexts/Operations/Reminders"], "docs/architecture/contexts/operations/reminders.md"),
    ("Contexts/Intelligence/Observations", ["app/Contexts/Intelligence/Observations"], "docs/architecture/contexts/intelligence/observations-and-ingestion.md"),
    ("Contexts/Intelligence/Ingestion", ["app/Contexts/Intelligence/Ingestion"], "docs/architecture/contexts/intelligence/observations-and-ingestion.md"),
    ("Contexts/Intelligence/Roster", ["app/Contexts/Intelligence/Roster"], "docs/architecture/contexts/intelligence/roster-and-contributions.md"),
    ("Contexts/Intelligence/Contributions", ["app/Contexts/Intelligence/Contributions"], "docs/architecture/contexts/intelligence/roster-and-contributions.md"),
    ("Contexts/Intelligence/EventAnalysis", ["app/Contexts/Intelligence/EventAnalysis"], "docs/architecture/contexts/intelligence/event-analysis.md"),
    ("Contexts/Intelligence/Diplomacy", ["app/Contexts/Intelligence/Diplomacy"], "docs/architecture/contexts/intelligence/diplomacy-and-sharing.md"),
    ("Contexts/Intelligence/Sharing", ["app/Contexts/Intelligence/Sharing"], "docs/architecture/contexts/intelligence/diplomacy-and-sharing.md"),
    ("Contexts/Communications/Reminders", ["app/Contexts/Communications/Reminders"], "docs/architecture/contexts/communications/reminder-delivery.md"),
    ("Contexts/Platform/Access", ["app/Contexts/Platform/Access"], "docs/architecture/contexts/platform/administration-and-lifecycle.md"),
    ("Contexts/Platform/EventAdministration", ["app/Contexts/Platform/EventAdministration"], "docs/architecture/contexts/platform/event-administration.md"),
    ("Contexts/Platform/Integrations", ["app/Contexts/Platform/Integrations"], "docs/architecture/contexts/platform/integrations.md"),
    ("Contexts/Platform/Lifecycle", ["app/Contexts/Platform/Actions", "app/Contexts/Platform/Services"], "docs/architecture/contexts/platform/administration-and-lifecycle.md"),
    ("Workflows/Registration", ["app/Workflows/Registration"], "docs/codebase/module-map.md"),
    ("Workflows/PlayerContext", ["app/Workflows/PlayerContext"], "docs/architecture/contexts/game-world/player-context.md"),
    ("Workflows/KingdomGovernance", ["app/Workflows/KingdomGovernance"], "docs/architecture/contexts/game-world/kingdom-governance.md"),
    ("Workflows/KingdomTransfer", ["app/Workflows/KingdomTransfer"], "docs/codebase/module-map.md"),
    ("ReadModels/AllianceDashboard", ["app/ReadModels/AllianceDashboard"], "docs/codebase/module-map.md"),
    ("ReadModels/EventCalendar", ["app/ReadModels/EventCalendar"], "docs/codebase/module-map.md"),
    ("ReadModels/EventHistory", ["app/ReadModels/EventHistory"], "docs/codebase/module-map.md"),
    ("ReadModels/EventManagement", ["app/ReadModels/EventManagement"], "docs/codebase/module-map.md"),
    ("ReadModels/KingdomIntelligence", ["app/ReadModels/KingdomIntelligence"], "docs/codebase/module-map.md"),
    ("ReadModels/KingdomSettings", ["app/ReadModels/KingdomSettings"], "docs/codebase/module-map.md"),
    ("ReadModels/SharedKingdomIntelligence", ["app/ReadModels/SharedKingdomIntelligence"], "docs/codebase/module-map.md"),
    ("Shared/AuditTrail", ["app/Shared/Infrastructure/AuditTrail"], "docs/architecture/integration-model.md"),
    ("Shared/Outbox", ["app/Shared/Infrastructure/Messaging/Outbox"], "docs/architecture/integration-model.md"),
]


def write(path: str | Path, content: str) -> None:
    target = ROOT / path
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(dedent(content).lstrip())


def php_namespace(relative_dir: str) -> str:
    return "Tests\\v2\\" + relative_dir.replace("/", "\\")


def reset_tests() -> None:
    if TESTS.exists():
        shutil.rmtree(TESTS)
    TESTS.mkdir(parents=True)


def write_test_kernel() -> None:
    write(
        "tests/v2/TestCase.php",
        r'''
        <?php

        declare(strict_types=1);

        namespace Tests\v2;

        use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;

        abstract class TestCase extends LaravelTestCase
        {
            protected function setUp(): void
            {
                parent::setUp();

                $this->withoutVite();
            }
        }
        ''',
    )

    write(
        "tests/v2/Support/CapabilitySurfaceTestCase.php",
        r'''
        <?php

        declare(strict_types=1);

        namespace Tests\v2\Support;

        use Illuminate\Database\Eloquent\Model;
        use Illuminate\Support\Facades\Schema;
        use RecursiveDirectoryIterator;
        use RecursiveIteratorIterator;
        use ReflectionClass;
        use SplFileInfo;
        use Tests\v2\TestCase;

        abstract class CapabilitySurfaceTestCase extends TestCase
        {
            protected const CAPABILITY = '';

            /** @var list<string> */
            protected const SOURCES = [];

            protected const DOCUMENTATION = '';

            public function test_documented_capability_and_source_surface_exist(): void
            {
                self::assertNotSame('', static::CAPABILITY);
                self::assertNotSame([], static::SOURCES);
                self::assertNotSame('', static::DOCUMENTATION);

                $documentation = base_path(static::DOCUMENTATION);
                self::assertFileExists($documentation, static::CAPABILITY.' documentation is missing.');
                self::assertStringContainsString('Status: Current', (string) file_get_contents($documentation));

                foreach (static::SOURCES as $source) {
                    self::assertDirectoryExists(base_path($source), static::CAPABILITY.' source is missing: '.$source);
                }

                self::assertNotSame([], $this->phpFiles(), static::CAPABILITY.' has no PHP implementation surface.');
            }

            public function test_every_php_symbol_in_the_capability_autoloads(): void
            {
                foreach ($this->phpFiles() as $file) {
                    $symbol = $this->symbolFor($file);
                    $loaded = class_exists($symbol)
                        || interface_exists($symbol)
                        || trait_exists($symbol)
                        || enum_exists($symbol);

                    self::assertTrue($loaded, static::CAPABILITY.' symbol does not autoload: '.$symbol);
                }
            }

            public function test_capability_models_map_to_the_fresh_schema(): void
            {
                foreach ($this->phpFiles() as $file) {
                    if (! str_contains(str_replace('\\', '/', $file), '/Models/')) {
                        continue;
                    }

                    $symbol = $this->symbolFor($file);
                    if (! class_exists($symbol) || ! is_subclass_of($symbol, Model::class)) {
                        continue;
                    }

                    $reflection = new ReflectionClass($symbol);
                    if (! $reflection->isInstantiable()) {
                        continue;
                    }

                    /** @var Model $model */
                    $model = $reflection->newInstance();
                    self::assertTrue(
                        Schema::hasTable($model->getTable()),
                        static::CAPABILITY.' model has no fresh-schema table: '.$symbol.' -> '.$model->getTable(),
                    );
                }
            }

            public function test_actions_services_queries_and_http_classes_expose_public_contracts(): void
            {
                foreach ($this->phpFiles() as $file) {
                    $normalized = str_replace('\\', '/', $file);
                    if (! preg_match('#/(Actions|Services|Queries|Http)/#', $normalized)) {
                        continue;
                    }

                    $symbol = $this->symbolFor($file);
                    if (! class_exists($symbol)) {
                        continue;
                    }

                    $reflection = new ReflectionClass($symbol);
                    if (! $reflection->isInstantiable()) {
                        continue;
                    }

                    $public = array_filter(
                        $reflection->getMethods(ReflectionClass::IS_PUBLIC),
                        static fn ($method): bool => $method->getDeclaringClass()->getName() === $symbol
                            && ! str_starts_with($method->getName(), '__'),
                    );

                    self::assertNotSame([], array_values($public), static::CAPABILITY.' public surface is empty: '.$symbol);
                }
            }

            /** @return list<string> */
            private function phpFiles(): array
            {
                $files = [];

                foreach (static::SOURCES as $source) {
                    $root = base_path($source);
                    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

                    /** @var SplFileInfo $entry */
                    foreach ($iterator as $entry) {
                        if (! $entry->isFile() || $entry->getExtension() !== 'php') {
                            continue;
                        }
                        $files[] = $entry->getPathname();
                    }
                }

                sort($files);

                return array_values(array_unique($files));
            }

            private function symbolFor(string $file): string
            {
                $app = str_replace('\\', '/', base_path('app')).'/';
                $normalized = str_replace('\\', '/', $file);
                self::assertStringStartsWith($app, $normalized);
                $relative = substr($normalized, strlen($app), -4);

                return 'App\\'.str_replace('/', '\\', $relative);
            }
        }
        ''',
    )

    write(
        "tests/v2/Support/ScenarioFactory.php",
        r'''
        <?php

        declare(strict_types=1);

        namespace Tests\v2\Support;

        use App\Contexts\Accounts\Actions\RegisterUser;
        use App\Contexts\Accounts\Models\User;
        use App\Contexts\Alliance\Core\Actions\CreateAlliance;
        use App\Contexts\Alliance\Core\Models\Alliance;
        use App\Contexts\GameWorld\Actions\ClaimPlayerAccount;
        use App\Contexts\GameWorld\Actions\PersistPlayerIdentity;
        use App\Contexts\GameWorld\Actions\ResolveKingdom;
        use App\Contexts\GameWorld\Models\Kingdom;
        use App\Contexts\GameWorld\Models\Player;
        use RuntimeException;

        final class ScenarioFactory
        {
            private static int $sequence = 0;

            public function user(?string $email = null): User
            {
                $id = ++self::$sequence;

                return app(RegisterUser::class)->handle(
                    'V2 User '.$id,
                    $email ?? 'v2-user-'.$id.'@example.test',
                    'Correct-Horse-Battery-Staple-'.$id.'!',
                    'UTC',
                );
            }

            public function kingdom(?int $number = null): Kingdom
            {
                $number ??= 100000 + (++self::$sequence);
                $kingdom = app(ResolveKingdom::class)->handle($number);

                return $kingdom ?? throw new RuntimeException('Expected a Kingdom.');
            }

            public function unclaimedPlayer(?int $kingdomNumber = null, ?string $stableId = null): Player
            {
                $id = ++self::$sequence;
                $kingdom = $this->kingdom($kingdomNumber);

                return app(PersistPlayerIdentity::class)->handle(
                    (string) $kingdom->id,
                    'V2 Player '.$id,
                    $stableId ?? 'v2-player-'.$id,
                );
            }

            public function player(User $owner, ?int $kingdomNumber = null, ?string $stableId = null): Player
            {
                $player = $this->unclaimedPlayer($kingdomNumber, $stableId);

                return app(ClaimPlayerAccount::class)->handle($player, $owner);
            }

            public function alliance(Player $owner): Alliance
            {
                $id = ++self::$sequence;

                return app(CreateAlliance::class)->handle(
                    $owner,
                    'V2 Alliance '.$id,
                    'v2-alliance-'.$id,
                    'en',
                    'UTC',
                );
            }
        }
        ''',
    )


def write_capability_surface_tests() -> None:
    for relative, sources, documentation in CAPABILITIES:
        namespace = php_namespace(relative)
        source_php = ",\n                ".join(f"'{source}'" for source in sources)
        write(
            f"tests/v2/{relative}/CapabilitySurfaceV2Test.php",
            f'''<?php

declare(strict_types=1);

namespace {namespace};

use Tests\\v2\\Support\\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{{
    protected const CAPABILITY = '{relative}';

    protected const SOURCES = [
                {source_php},
    ];

    protected const DOCUMENTATION = '{documentation}';
}}
''',
        )


def write_architecture_tests() -> None:
    manifest_rows = []
    for relative, sources, documentation in CAPABILITIES:
        test_path = f"tests/v2/{relative}/CapabilitySurfaceV2Test.php"
        source_list = ", ".join(f"'{source}'" for source in sources)
        manifest_rows.append(
            f"            ['capability' => '{relative}', 'sources' => [{source_list}], 'documentation' => '{documentation}', 'test' => '{test_path}'],"
        )
    manifest = "\n".join(manifest_rows)

    write(
        "tests/v2/Architecture/CapabilityCoverageV2Test.php",
        f'''<?php

declare(strict_types=1);

namespace Tests\\v2\\Architecture;

use Tests\\v2\\TestCase;

final class CapabilityCoverageV2Test extends TestCase
{{
    public function test_every_documented_v2_capability_has_a_dedicated_new_contract(): void
    {{
        $capabilities = [
{manifest}
        ];

        self::assertCount(42, $capabilities);

        foreach ($capabilities as $capability) {{
            self::assertFileExists(base_path($capability['documentation']), $capability['capability'].' documentation missing.');
            self::assertFileExists(base_path($capability['test']), $capability['capability'].' V2 contract missing.');
            foreach ($capability['sources'] as $source) {{
                self::assertDirectoryExists(base_path($source), $capability['capability'].' source missing: '.$source);
            }}
        }}
    }}
}}
''',
    )

    write(
        "tests/v2/Architecture/ArchitectureBoundariesV2Test.php",
        r'''
        <?php

        declare(strict_types=1);

        namespace Tests\v2\Architecture;

        use RecursiveDirectoryIterator;
        use RecursiveIteratorIterator;
        use SplFileInfo;
        use Tests\v2\TestCase;

        final class ArchitectureBoundariesV2Test extends TestCase
        {
            public function test_only_the_seven_documented_business_contexts_exist(): void
            {
                $directories = array_values(array_filter(
                    scandir(base_path('app/Contexts')) ?: [],
                    static fn (string $entry): bool => ! in_array($entry, ['.', '..', 'README.md'], true),
                ));
                sort($directories);

                self::assertSame(
                    ['Accounts', 'Alliance', 'Communications', 'GameWorld', 'Intelligence', 'Operations', 'Platform'],
                    $directories,
                );
                self::assertDirectoryDoesNotExist(base_path('app/Domain'));
            }

            public function test_runtime_contains_no_v1_domain_namespace(): void
            {
                foreach ($this->phpFiles(['app', 'bootstrap', 'config', 'database', 'routes']) as $file) {
                    self::assertStringNotContainsString('App\\Domain\\', (string) file_get_contents($file), $file);
                }
            }

            public function test_business_contexts_do_not_depend_on_composition_layers(): void
            {
                foreach ($this->phpFiles(['app/Contexts']) as $file) {
                    $source = (string) file_get_contents($file);
                    self::assertStringNotContainsString('use App\\Workflows\\', $source, $file);
                    self::assertStringNotContainsString('use App\\ReadModels\\', $source, $file);
                }
            }

            public function test_read_models_remain_read_only_composition(): void
            {
                foreach ($this->phpFiles(['app/ReadModels']) as $file) {
                    $source = (string) file_get_contents($file);
                    self::assertDoesNotMatchRegularExpression('/::query\(\)->(?:create|update|delete)\s*\(/', $source, $file);
                    self::assertDoesNotMatchRegularExpression('/->(?:save|delete)\s*\(/', $source, $file);
                    self::assertStringNotContainsString('DB::transaction(', $source, $file);
                }
            }

            public function test_player_persistence_is_owned_by_game_world(): void
            {
                foreach ($this->phpFiles(['app/Contexts/Alliance', 'app/Contexts/Operations', 'app/Contexts/Intelligence', 'app/Contexts/Communications', 'app/Contexts/Platform', 'app/Workflows']) as $file) {
                    $source = (string) file_get_contents($file);
                    self::assertStringNotContainsString('Player::query()->create(', $source, $file);
                }
            }

            public function test_operations_and_intelligence_interpret_alliance_authority_locally(): void
            {
                foreach ($this->phpFiles(['app/Contexts/Operations', 'app/Contexts/Intelligence']) as $file) {
                    $normalized = str_replace('\\', '/', $file);
                    if (str_contains($normalized, '/Access/Services/')) {
                        continue;
                    }
                    $source = (string) file_get_contents($file);
                    self::assertStringNotContainsString('App\\Contexts\\Alliance\\Access\\Services\\AllianceAuthorization', $source, $file);
                    self::assertStringNotContainsString('App\\Contexts\\Alliance\\Access\\Services\\AllianceMutationAuthority', $source, $file);
                }
            }

            public function test_kingdom_transfer_owns_its_permission_vocabulary(): void
            {
                foreach ($this->phpFiles(['app/Workflows/KingdomTransfer']) as $file) {
                    $source = (string) file_get_contents($file);
                    self::assertStringNotContainsString('IntelligencePermission::', $source, $file);
                    self::assertStringNotContainsString('App\\Contexts\\Intelligence\\Access\\', $source, $file);
                }

                self::assertFileExists(base_path('app/Workflows/KingdomTransfer/Access/Enums/TransferPermission.php'));
                self::assertFileExists(base_path('app/Workflows/KingdomTransfer/Access/Services/TransferAuthorization.php'));
                self::assertFileExists(base_path('app/Workflows/KingdomTransfer/Access/Services/TransferMutationAuthority.php'));
            }

            public function test_the_test_tree_contains_only_clean_room_v2_tests(): void
            {
                $entries = array_values(array_filter(
                    scandir(base_path('tests')) ?: [],
                    static fn (string $entry): bool => ! in_array($entry, ['.', '..'], true),
                ));
                self::assertSame(['v2'], $entries);

                foreach ($this->phpFiles(['tests/v2']) as $file) {
                    $normalized = str_replace('\\', '/', $file);
                    if (str_contains($normalized, '/Support/') || str_ends_with($normalized, '/TestCase.php')) {
                        continue;
                    }
                    self::assertStringEndsWith('V2Test.php', $normalized, $file);
                    self::assertStringNotContainsString('App\\Domain\\', (string) file_get_contents($file), $file);
                }
            }

            /** @param list<string> $roots @return list<string> */
            private function phpFiles(array $roots): array
            {
                $files = [];
                foreach ($roots as $root) {
                    $path = base_path($root);
                    if (! is_dir($path)) {
                        continue;
                    }
                    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
                    /** @var SplFileInfo $entry */
                    foreach ($iterator as $entry) {
                        if ($entry->isFile() && $entry->getExtension() === 'php') {
                            $files[] = $entry->getPathname();
                        }
                    }
                }
                sort($files);
                return $files;
            }
        }
        ''',
    )

    write(
        "tests/v2/Architecture/MutationSafetyV2Test.php",
        r'''
        <?php

        declare(strict_types=1);

        namespace Tests\v2\Architecture;

        use Tests\v2\TestCase;

        final class MutationSafetyV2Test extends TestCase
        {
            public function test_high_risk_mutations_keep_transaction_and_locking_contracts(): void
            {
                $contracts = [
                    'app/Contexts/Alliance/Core/Actions/CreateAlliance.php' => ['DB::transaction', 'lockForUpdate'],
                    'app/Contexts/Alliance/Membership/Actions/TransferAllianceLeadership.php' => ['DB::transaction', 'lockForUpdate'],
                    'app/Contexts/GameWorld/Governance/Actions/AssignKingdomRole.php' => ['DB::transaction', 'lockForUpdate'],
                    'app/Contexts/Operations/EventCore/Actions/CreateEvent.php' => ['DB::transaction', 'requireCreate'],
                    'app/Contexts/Operations/KingPerks/Services/KingPerkScheduler.php' => ['DB::transaction', 'lockForUpdate'],
                    'app/Contexts/Platform/Access/Services/PlatformMutationAuthority.php' => ['DB::transactionLevel()', 'lockForUpdate'],
                    'app/Workflows/KingdomTransfer/Access/Services/TransferMutationAuthority.php' => ['acquireActiveScope', 'acquireExclusiveScope'],
                ];

                foreach ($contracts as $file => $needles) {
                    $source = (string) file_get_contents(base_path($file));
                    foreach ($needles as $needle) {
                        self::assertStringContainsString($needle, $source, $file.' must retain '.$needle);
                    }
                }
            }
        }
        ''',
    )


def write_behavior_tests() -> None:
    write(
        "tests/v2/Contexts/Accounts/AccountSecurity/AccountSecurityBehaviorV2Test.php",
        r'''
        <?php

        declare(strict_types=1);

        namespace Tests\v2\Contexts\Accounts\AccountSecurity;

        use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
        use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
        use Illuminate\Foundation\Testing\RefreshDatabase;
        use Illuminate\Support\Facades\Hash;
        use Tests\v2\Support\ScenarioFactory;
        use Tests\v2\TestCase;

        final class AccountSecurityBehaviorV2Test extends TestCase
        {
            use RefreshDatabase;

            public function test_registration_canonicalizes_identity_and_commits_audit_and_outbox(): void
            {
                $user = (new ScenarioFactory)->user('V2.USER@Example.Test');

                self::assertSame('v2.user@example.test', $user->email);
                self::assertTrue(Hash::check('Correct-Horse-Battery-Staple-1!', $user->password));
                self::assertTrue(AuditEvent::query()->where('event', 'user.registered')->where('actor_user_id', $user->id)->exists());
                self::assertTrue(OutboxMessage::query()->where('event_type', 'user.registered')->where('aggregate_id', (string) $user->id)->exists());
            }
        }
        ''',
    )

    write(
        "tests/v2/Contexts/GameWorld/Identity/PlayerIdentityBehaviorV2Test.php",
        r'''
        <?php

        declare(strict_types=1);

        namespace Tests\v2\Contexts\GameWorld\Identity;

        use App\Contexts\GameWorld\Actions\ClaimPlayerAccount;
        use App\Contexts\GameWorld\Actions\PersistPlayerIdentity;
        use App\Contexts\GameWorld\Actions\ResolveKingdom;
        use App\Contexts\GameWorld\Enums\KingdomStatus;
        use App\Contexts\GameWorld\Services\PlayerContext;
        use Illuminate\Foundation\Testing\RefreshDatabase;
        use Illuminate\Validation\ValidationException;
        use LogicException;
        use Tests\v2\Support\ScenarioFactory;
        use Tests\v2\TestCase;

        final class PlayerIdentityBehaviorV2Test extends TestCase
        {
            use RefreshDatabase;

            public function test_stable_game_identity_is_reused_and_account_claim_is_exclusive(): void
            {
                $factory = new ScenarioFactory;
                $kingdom = $factory->kingdom(12001);
                $first = app(PersistPlayerIdentity::class)->handle((string) $kingdom->id, 'First Name', 'stable-12001');
                $again = app(PersistPlayerIdentity::class)->handle((string) $kingdom->id, 'Renamed', 'stable-12001');

                self::assertSame((string) $first->id, (string) $again->id);
                self::assertSame('Renamed', $again->current_name);

                $owner = $factory->user();
                $other = $factory->user();
                $claimed = app(ClaimPlayerAccount::class)->handle($again, $owner);
                self::assertSame((int) $owner->id, (int) $claimed->user_id);

                $this->expectException(ValidationException::class);
                app(ClaimPlayerAccount::class)->handle($claimed, $other);
            }

            public function test_player_context_fails_closed_and_never_accepts_another_users_player(): void
            {
                $factory = new ScenarioFactory;
                $owner = $factory->user();
                $other = $factory->user();
                $player = $factory->player($owner, 12002);
                $context = app(PlayerContext::class);

                try {
                    $context->player();
                    self::fail('Unresolved PlayerContext must fail closed.');
                } catch (LogicException) {
                    self::assertNull($context->playerOrNull());
                }

                $context->activate($player, $owner);
                self::assertSame((string) $player->id, (string) $context->player()->id);
                $context->clear();

                $this->expectException(LogicException::class);
                $context->activate($player, $other);
            }

            public function test_archived_and_invalid_kingdoms_cannot_be_resolved_for_active_use(): void
            {
                $factory = new ScenarioFactory;
                $kingdom = $factory->kingdom(12003);
                $kingdom->forceFill(['status' => KingdomStatus::Archived])->save();

                try {
                    app(ResolveKingdom::class)->handle('not-a-number');
                    self::fail('Invalid kingdom should fail.');
                } catch (ValidationException) {
                    self::assertTrue(true);
                }

                $this->expectException(ValidationException::class);
                app(ResolveKingdom::class)->handle(12003);
            }
        }
        ''',
    )

    write(
        "tests/v2/Contexts/GameWorld/Governance/KingdomGovernanceBehaviorV2Test.php",
        r'''
        <?php

        declare(strict_types=1);

        namespace Tests\v2\Contexts\GameWorld\Governance;

        use App\Contexts\GameWorld\Governance\Actions\BootstrapKingdomAdministrator;
        use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
        use Illuminate\Foundation\Testing\RefreshDatabase;
        use Illuminate\Validation\ValidationException;
        use Tests\v2\Support\ScenarioFactory;
        use Tests\v2\TestCase;

        final class KingdomGovernanceBehaviorV2Test extends TestCase
        {
            use RefreshDatabase;

            public function test_administrator_bootstrap_is_single_kingdom_scoped_and_durable(): void
            {
                $factory = new ScenarioFactory;
                $owner = $factory->user();
                $player = $factory->player($owner, 13001);
                $kingdom = $player->kingdom()->firstOrFail();

                $assignment = app(BootstrapKingdomAdministrator::class)->handle($kingdom, $player);
                self::assertSame((string) $kingdom->id, (string) $assignment->kingdom_id);
                self::assertSame((string) $player->id, (string) $assignment->player_id);
                self::assertTrue(OutboxMessage::query()->where('event_type', 'kingdom.role_bootstrapped')->where('aggregate_id', (string) $assignment->id)->exists());

                $this->expectException(ValidationException::class);
                app(BootstrapKingdomAdministrator::class)->handle($kingdom, $player);
            }

            public function test_bootstrap_rejects_player_from_another_kingdom(): void
            {
                $factory = new ScenarioFactory;
                $owner = $factory->user();
                $targetKingdom = $factory->kingdom(13002);
                $other = $factory->player($owner, 13003);

                $this->expectException(ValidationException::class);
                app(BootstrapKingdomAdministrator::class)->handle($targetKingdom, $other);
            }
        }
        ''',
    )

    write(
        "tests/v2/Contexts/Alliance/Core/AllianceLifecycleBehaviorV2Test.php",
        r'''
        <?php

        declare(strict_types=1);

        namespace Tests\v2\Contexts\Alliance\Core;

        use App\Contexts\Alliance\Core\Actions\CreateAlliance;
        use App\Contexts\Alliance\Membership\Enums\AllianceRank;
        use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
        use App\Contexts\Alliance\Membership\Models\AllianceMembership;
        use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
        use Illuminate\Foundation\Testing\RefreshDatabase;
        use Illuminate\Validation\ValidationException;
        use Tests\v2\Support\ScenarioFactory;
        use Tests\v2\TestCase;

        final class AllianceLifecycleBehaviorV2Test extends TestCase
        {
            use RefreshDatabase;

            public function test_claimed_player_creates_alliance_and_bootstraps_r5_membership(): void
            {
                $factory = new ScenarioFactory;
                $user = $factory->user();
                $player = $factory->player($user, 14001);
                $alliance = $factory->alliance($player);
                $membership = AllianceMembership::query()->where('alliance_id', $alliance->id)->where('player_id', $player->id)->firstOrFail();

                self::assertSame(MembershipStatus::Active, $membership->status);
                self::assertSame(AllianceRank::R5, $membership->rank);
                self::assertSame((string) $player->current_kingdom_id, (string) $alliance->kingdom_id);
                self::assertTrue(OutboxMessage::query()->where('event_type', 'alliance.created')->where('aggregate_id', (string) $alliance->id)->exists());

                $this->expectException(ValidationException::class);
                app(CreateAlliance::class)->handle($player, 'Second Alliance', 'second-alliance');
            }

            public function test_unclaimed_player_cannot_create_alliance(): void
            {
                $player = (new ScenarioFactory)->unclaimedPlayer(14002);

                $this->expectException(ValidationException::class);
                app(CreateAlliance::class)->handle($player, 'Invalid Alliance', 'invalid-alliance');
            }
        }
        ''',
    )

    write(
        "tests/v2/Contexts/Operations/EventCore/OperationsPolicyBehaviorV2Test.php",
        r'''
        <?php

        declare(strict_types=1);

        namespace Tests\v2\Contexts\Operations\EventCore;

        use App\Contexts\Operations\EventCore\Enums\RecurrenceFrequency;
        use App\Contexts\Operations\EventCore\Services\RecurrenceCalculator;
        use Carbon\CarbonImmutable;
        use InvalidArgumentException;
        use Tests\v2\TestCase;

        final class OperationsPolicyBehaviorV2Test extends TestCase
        {
            public function test_recurrence_calculation_preserves_local_start_and_interval(): void
            {
                $first = CarbonImmutable::parse('2026-08-16 18:00:00', 'America/Toronto');
                $occurrences = app(RecurrenceCalculator::class)->calculate(
                    $first,
                    RecurrenceFrequency::Weekly,
                    2,
                    $first->addWeeks(4),
                );

                self::assertCount(3, $occurrences);
                self::assertSame($first->toIso8601String(), $occurrences[0]->toIso8601String());
                self::assertSame($first->addWeeks(2)->toIso8601String(), $occurrences[1]->toIso8601String());
                self::assertSame($first->addWeeks(4)->toIso8601String(), $occurrences[2]->toIso8601String());
            }

            public function test_invalid_recurrence_interval_is_rejected(): void
            {
                $this->expectException(InvalidArgumentException::class);
                app(RecurrenceCalculator::class)->calculate(
                    CarbonImmutable::parse('2026-08-16 18:00:00', 'UTC'),
                    RecurrenceFrequency::Daily,
                    0,
                );
            }
        }
        ''',
    )

    write(
        "tests/v2/Contexts/Operations/KingPerks/KingPerkPolicyBehaviorV2Test.php",
        r'''
        <?php

        declare(strict_types=1);

        namespace Tests\v2\Contexts\Operations\KingPerks;

        use App\Contexts\Operations\KingPerks\Services\KingPerkPreparationPresetCatalog;
        use Carbon\CarbonImmutable;
        use Tests\v2\TestCase;

        final class KingPerkPolicyBehaviorV2Test extends TestCase
        {
            public function test_preparation_presets_cover_each_day_without_exceeding_the_event_window(): void
            {
                $start = CarbonImmutable::parse('2026-08-16 00:00:00', 'UTC');
                $end = $start->addDays(6);
                $days = app(KingPerkPreparationPresetCatalog::class)->forWindow($start, $end);

                self::assertCount(6, $days);
                self::assertSame('construction', $days[0]['focus']);
                self::assertSame('research', $days[1]['focus']);
                self::assertSame('training', $days[3]['focus']);
                self::assertSame('healing', $days[5]['focus']);
                self::assertSame($start->toIso8601String(), $days[0]['startsAt']);
                self::assertSame($end->toIso8601String(), $days[5]['endsAt']);
            }
        }
        ''',
    )

    write(
        "tests/v2/Contexts/Platform/Access/PlatformAdministrationBehaviorV2Test.php",
        r'''
        <?php

        declare(strict_types=1);

        namespace Tests\v2\Contexts\Platform\Access;

        use App\Contexts\Platform\Access\Models\PlatformAdministrator;
        use App\Contexts\Platform\Access\Services\PlatformMutationAuthority;
        use App\Contexts\Platform\Actions\ManagePlatformAdministrator;
        use Illuminate\Auth\Access\AuthorizationException;
        use Illuminate\Foundation\Testing\RefreshDatabase;
        use Illuminate\Support\Facades\DB;
        use InvalidArgumentException;
        use LogicException;
        use Tests\v2\Support\ScenarioFactory;
        use Tests\v2\TestCase;

        final class PlatformAdministrationBehaviorV2Test extends TestCase
        {
            use RefreshDatabase;

            public function test_platform_administrator_bootstrap_grant_and_revoke_are_explicit(): void
            {
                $factory = new ScenarioFactory;
                $first = $factory->user();
                $second = $factory->user();
                $manager = app(ManagePlatformAdministrator::class);

                $firstGrant = $manager->grant($first);
                self::assertTrue(PlatformAdministrator::activeFor($first));
                self::assertNull($firstGrant->granted_by_user_id);

                $secondGrant = $manager->grant($second, $first);
                self::assertTrue(PlatformAdministrator::activeFor($second));
                $revoked = $manager->revoke($first, $secondGrant);
                self::assertNotNull($revoked->revoked_at);
                self::assertFalse(PlatformAdministrator::activeFor($second));

                $this->expectException(InvalidArgumentException::class);
                $manager->revoke($first, $firstGrant);
            }

            public function test_platform_mutation_authority_requires_transaction_and_active_grant(): void
            {
                $user = (new ScenarioFactory)->user();
                $authority = app(PlatformMutationAuthority::class);

                try {
                    $authority->require($user);
                    self::fail('Authority outside transaction must fail.');
                } catch (LogicException) {
                    self::assertTrue(true);
                }

                $this->expectException(AuthorizationException::class);
                DB::transaction(static fn () => $authority->require($user));
            }
        }
        ''',
    )

    write(
        "tests/v2/Shared/InfrastructureBehaviorV2Test.php",
        r'''
        <?php

        declare(strict_types=1);

        namespace Tests\v2\Shared;

        use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
        use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
        use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
        use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
        use Illuminate\Foundation\Testing\RefreshDatabase;
        use Tests\v2\Support\ScenarioFactory;
        use Tests\v2\TestCase;

        final class InfrastructureBehaviorV2Test extends TestCase
        {
            use RefreshDatabase;

            public function test_audit_and_outbox_are_neutral_durable_infrastructure(): void
            {
                $user = (new ScenarioFactory)->user();
                $audit = app(AuditRecorder::class)->record('v2.test.audit', $user, $user, null, ['source' => 'clean-room']);
                $outbox = app(OutboxRecorder::class)->record(
                    'v2.test.outbox',
                    null,
                    $user,
                    ['source' => 'clean-room'],
                    'v2.test.outbox:'.$user->id,
                );

                self::assertTrue(AuditEvent::query()->whereKey($audit->id)->where('actor_user_id', $user->id)->exists());
                self::assertTrue(OutboxMessage::query()->whereKey($outbox->id)->where('idempotency_key', 'v2.test.outbox:'.$user->id)->exists());
                self::assertSame('v2.test.outbox', $outbox->event_type);
            }
        }
        ''',
    )


def write_visual_test() -> None:
    write(
        "tests/v2/Visual/ApplicationShellV2.spec.ts",
        r'''
        import { expect, test } from '@playwright/test';

        test('V2 authentication shell renders without horizontal overflow', async ({ page }) => {
          const response = await page.goto('/login');
          expect(response?.ok()).toBeTruthy();
          await expect(page.locator('body')).toBeVisible();
          const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
          expect(overflow).toBeFalsy();
        });
        ''',
    )


def update_repository_test_configuration() -> None:
    write(
        "phpunit.xml",
        r'''
        <?xml version="1.0" encoding="UTF-8"?>
        <phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                 xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
                 bootstrap="vendor/autoload.php"
                 colors="true"
                 cacheDirectory=".phpunit.cache">
            <testsuites>
                <testsuite name="Architecture V2">
                    <directory>tests/v2</directory>
                </testsuite>
            </testsuites>
            <source>
                <include>
                    <directory>app</directory>
                </include>
            </source>
            <php>
                <env name="APP_ENV" value="testing"/>
                <env name="APP_KEY" value="base64:Wb1PHh03m1vXbmyRxlI+Y96TqgK7Vyt8H/lkQ8o1SP0="/>
                <env name="BCRYPT_ROUNDS" value="4"/>
                <env name="CACHE_STORE" value="array"/>
                <env name="DB_CONNECTION" value="pgsql"/>
                <env name="DB_HOST" value="127.0.0.1"/>
                <env name="DB_PORT" value="5432"/>
                <env name="DB_DATABASE" value="kingshot_test"/>
                <env name="DB_USERNAME" value="kingshot"/>
                <env name="DB_PASSWORD" value="kingshot"/>
                <env name="MAIL_MAILER" value="array"/>
                <env name="PULSE_ENABLED" value="false"/>
                <env name="QUEUE_CONNECTION" value="sync"/>
                <env name="SESSION_DRIVER" value="array"/>
                <env name="SECURITY_CSP_ENABLED" value="false"/>
            </php>
        </phpunit>
        ''',
    )

    write(
        "playwright.config.ts",
        r'''
        import { defineConfig } from '@playwright/test';

        const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';

        export default defineConfig({
          testDir: './tests/v2/Visual',
          snapshotPathTemplate: '{testDir}/__screenshots__/{testFilePath}/{projectName}/{arg}{ext}',
          fullyParallel: false,
          forbidOnly: Boolean(process.env.CI),
          retries: process.env.CI ? 1 : 0,
          workers: 1,
          reporter: process.env.CI ? [['line'], ['html', { open: 'never' }]] : 'list',
          expect: {
            toHaveScreenshot: {
              animations: 'disabled',
              caret: 'hide',
              scale: 'css',
              maxDiffPixelRatio: 0.005,
            },
          },
          use: {
            baseURL,
            browserName: 'chromium',
            colorScheme: 'dark',
            reducedMotion: 'reduce',
            locale: 'en-CA',
            timezoneId: 'UTC',
            screenshot: 'only-on-failure',
            trace: 'retain-on-failure',
          },
          projects: [
            {
              name: 'desktop',
              use: { viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1 },
            },
            {
              name: 'mobile',
              use: { viewport: { width: 390, height: 844 }, deviceScaleFactor: 1, hasTouch: true, isMobile: true },
            },
          ],
          outputDir: 'test-results/visual',
        });
        ''',
    )

    write(
        "docs/codebase/testing.md",
        r'''
        # Testing

        Status: Current

        Architecture V2 uses a **clean-room test suite** under `tests/v2`. The previous Architecture/Feature/Integration/Performance/TenantIsolation/Unit taxonomy is not retained as an acceptance model.

        ## Sources of truth

        New tests are authored from the current code/database constraints and `/docs`, in this order:

        1. executable code and database constraints for exact runtime behavior;
        2. architecture documentation for ownership, invariants and supported collaboration;
        3. codebase documentation for physical implementation locations;
        4. product documentation for user outcomes;
        5. governance/reference/operations documentation for cross-cutting requirements.

        Historical tests are not specifications and are not migrated into V2.

        ## Structure

        - all executable PHP tests live below `tests/v2`;
        - every PHP test file/class ends in `V2Test`;
        - support code lives under `tests/v2/Support` and is not a test;
        - visual tests live under `tests/v2/Visual` and use `V2` in their spec names;
        - `phpunit.xml` exposes one `Architecture V2` suite rooted at `tests/v2`.

        ## Coverage model

        Every documented capability has a dedicated V2 surface contract that verifies current documentation, autoloadable implementation symbols, persistence mappings and public application surfaces. Separate behavior tests protect high-risk business/security invariants.

        Protected mutations must cover authorization failure, scope isolation and transaction/locking behavior where relevant. Cross-context behavior must use supported context contracts, Workflows, ReadModels or durable messaging rather than direct persistence reach-through.

        ## Required verification

        The V2 gate uses PostgreSQL, `migrate:fresh`, Pint, Larastan and the complete `tests/v2` suite. It also verifies that `tests` contains no legacy test tree and that `App\\Domain` cannot return in runtime or tests.
        ''',
    )

    write(
        ".github/workflows/architecture-v2-verification.yml",
        r'''
        name: Architecture V2 Verification

        on:
          pull_request:
            branches: [main]
            paths:
              - 'app/**'
              - 'bootstrap/**'
              - 'config/**'
              - 'database/**'
              - 'docs/**'
              - 'routes/**'
              - 'tests/v2/**'
              - 'phpunit.xml'
              - 'playwright.config.ts'
              - '.github/workflows/architecture-v2-verification.yml'
          workflow_dispatch:

        permissions:
          contents: read

        concurrency:
          group: architecture-v2-${{ github.event.pull_request.number || github.ref }}
          cancel-in-progress: true

        jobs:
          architecture-contract:
            name: Clean-room V2 architecture and capability contracts
            runs-on: ubuntu-24.04
            timeout-minutes: 50

            services:
              postgres:
                image: postgres:18
                env:
                  POSTGRES_DB: kingshot_test
                  POSTGRES_USER: kingshot
                  POSTGRES_PASSWORD: kingshot
                ports:
                  - 5432:5432
                options: >-
                  --health-cmd "pg_isready -U kingshot -d kingshot_test"
                  --health-interval 5s
                  --health-timeout 5s
                  --health-retries 10

            env:
              APP_ENV: testing
              APP_KEY: base64:Wb1PHh03m1vXbmyRxlI+Y96TqgK7Vyt8H/lkQ8o1SP0=
              DB_CONNECTION: pgsql
              DB_HOST: 127.0.0.1
              DB_PORT: 5432
              DB_DATABASE: kingshot_test
              DB_USERNAME: kingshot
              DB_PASSWORD: kingshot
              CACHE_STORE: array
              SESSION_DRIVER: array
              QUEUE_CONNECTION: sync

            steps:
              - name: Check out repository
                uses: actions/checkout@d23441a48e516b6c34aea4fa41551a30e30af803

              - name: Verify clean-room test hard cut
                shell: bash
                run: |
                  set -euo pipefail
                  test ! -d app/Domain
                  test -d tests/v2
                  test "$(find tests -mindepth 1 -maxdepth 1 -printf '%f\n' | sort)" = "v2"
                  bad="$(find tests/v2 -type f -name '*Test.php' ! -name '*V2Test.php' ! -path 'tests/v2/TestCase.php' ! -path 'tests/v2/Support/*' -print)"
                  test -z "$bad" || { echo "$bad"; exit 1; }
                  legacy="$(grep -R -n --include='*.php' 'App\\Domain\\' app tests/v2 routes bootstrap config database 2>/dev/null || true)"
                  test -z "$legacy" || { echo "$legacy"; exit 1; }
                  grep -q '<directory>tests/v2</directory>' phpunit.xml
                  ! grep -q '<directory>tests/Feature</directory>' phpunit.xml

              - name: Configure PHP
                uses: shivammathur/setup-php@f3e473d116dcccaddc5834248c87452386958240
                with:
                  php-version: '8.5'
                  extensions: dom, intl, mbstring, pcntl, pdo_pgsql, posix, redis, simplexml, xml, xmlwriter, zip
                  coverage: none
                  tools: composer:v2

              - name: Install locked PHP dependencies
                run: composer install --no-interaction --no-progress --prefer-dist

              - name: Verify PostgreSQL 18 fresh schema
                run: php artisan migrate:fresh --force

              - name: Check clean-room V2 formatting
                run: vendor/bin/pint --test tests/v2 app/Contexts app/Shared app/Workflows app/ReadModels bootstrap routes

              - name: Run complete clean-room V2 suite
                run: php artisan test tests/v2

              - name: Larastan all final V2 owners
                run: >-
                  vendor/bin/phpstan analyse --memory-limit=1536M
                  app/Contexts
                  app/Shared
                  app/Workflows
                  app/ReadModels
                  bootstrap/app.php
                  bootstrap/providers.php
                  routes

              - name: Verify Playwright points only at V2 visual tests
                shell: bash
                run: |
                  set -euo pipefail
                  grep -q "testDir: './tests/v2/Visual'" playwright.config.ts
                  test -f tests/v2/Visual/ApplicationShellV2.spec.ts
        ''',
    )


def main() -> None:
    reset_tests()
    write_test_kernel()
    write_capability_surface_tests()
    write_architecture_tests()
    write_behavior_tests()
    write_visual_test()
    update_repository_test_configuration()
    print(f"ARCH-V2-TESTS: generated clean-room suite for {len(CAPABILITIES)} documented capabilities.")


if __name__ == '__main__':
    main()
