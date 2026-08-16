#!/usr/bin/env python3
from pathlib import Path
import shutil
import sys

ROOT = Path('.')


def move(src: str, dst: str) -> None:
    source = ROOT / src
    target = ROOT / dst
    if not source.exists():
        return
    target.parent.mkdir(parents=True, exist_ok=True)
    if target.exists():
        raise RuntimeError(f'target already exists: {dst}')
    shutil.move(str(source), str(target))


def remove(path: str) -> None:
    target = ROOT / path
    if target.is_dir():
        shutil.rmtree(target)
    elif target.exists():
        target.unlink()


def replace_file(path: Path, replacements: list[tuple[str, str]]) -> None:
    try:
        text = path.read_text()
    except UnicodeDecodeError:
        return
    updated = text
    for old, new in replacements:
        updated = updated.replace(old, new)
    if updated != text:
        path.write_text(updated)


# Physical moves whose implementation still exists only at the old path.
move('app/Contexts/Operations/EventCore', 'app/Contexts/Operations/Events')
move('app/Contexts/Platform/Http/Controllers/PlatformAdministrationController.php', 'app/Contexts/Platform/Administration/Http/Controllers/PlatformAdministrationController.php')
move('app/Contexts/Platform/Queries/PlatformAdministrationQuery.php', 'app/Contexts/Platform/Administration/Queries/PlatformAdministrationQuery.php')
move('app/Contexts/Platform/Services/ProductionLaunchReadiness.php', 'app/Contexts/Platform/Administration/Services/ProductionLaunchReadiness.php')
move('app/Contexts/Platform/Services/RuntimeConfigurationValidator.php', 'app/Shared/Infrastructure/Runtime/Services/RuntimeConfigurationValidator.php')
move('app/Contexts/Platform/Http/Controllers/ReadinessController.php', 'app/Shared/Infrastructure/Runtime/Http/Controllers/ReadinessController.php')
move('app/Contexts/Platform/Http/Middleware/AssignRequestContext.php', 'app/Shared/Infrastructure/Observability/Http/Middleware/AssignRequestContext.php')
move('app/Contexts/Platform/Http/Middleware/RecordRequestMetrics.php', 'app/Shared/Infrastructure/Observability/Http/Middleware/RecordRequestMetrics.php')
move('app/Contexts/Platform/Http/Middleware/SecurityHeaders.php', 'app/Shared/Infrastructure/Security/Http/Middleware/SecurityHeaders.php')
move('app/Contexts/Platform/Http/Middleware/HandleInertiaRequests.php', 'app/Contexts/GameWorld/Players/Http/Middleware/HandleInertiaRequests.php')
move('app/Shared/Access', 'app/Shared/Infrastructure/Access')
move('app/Shared/Http/Controller.php', 'app/Shared/Infrastructure/Http/Controller.php')

# Delete implementations already rebuilt at V3 owners. These are not retained as aliases.
for legacy in [
    'app/Contexts/Accounts/Actions', 'app/Contexts/Accounts/Http', 'app/Contexts/Accounts/Models', 'app/Contexts/Accounts/Services',
    'app/Contexts/GameWorld/Actions', 'app/Contexts/GameWorld/Enums', 'app/Contexts/GameWorld/Http', 'app/Contexts/GameWorld/Models', 'app/Contexts/GameWorld/Queries', 'app/Contexts/GameWorld/Services',
    'app/Contexts/Alliance/Core', 'app/Contexts/Alliance/Policies',
    'app/Contexts/Intelligence/Http', 'app/Contexts/Communications/Reminders',
    'app/Contexts/Platform/Access', 'app/Contexts/Platform/Actions', 'app/Contexts/Platform/Models', 'app/Contexts/Platform/Providers',
    'app/Shared/Providers', 'app/Shared/Http',
]:
    remove(legacy)

# Remaining Platform root buckets now contain only old copies or files moved above.
for legacy in ['app/Contexts/Platform/Http', 'app/Contexts/Platform/Queries', 'app/Contexts/Platform/Services']:
    remove(legacy)

R = []
def rep(old: str, new: str) -> None:
    R.append((old, new))

# Accounts
rep(r'App\Contexts\Accounts\Models\User', r'App\Contexts\Accounts\Identity\Models\User')
rep(r'App\Contexts\Accounts\Actions\RegisterUser', r'App\Contexts\Accounts\Registration\Actions\RegisterUser')
rep(r'App\Contexts\Accounts\Services\TotpService', r'App\Contexts\Accounts\MultiFactorAuthentication\Services\TotpService')
rep(r'App\Contexts\Accounts\Services\TwoFactorManager', r'App\Contexts\Accounts\MultiFactorAuthentication\Services\TwoFactorManager')
for name, capability in {
    'AuthenticatedSessionController': 'Authentication',
    'ConfirmPasswordController': 'Authentication',
    'ForgotPasswordController': 'Credentials',
    'ResetPasswordController': 'Credentials',
    'EmailVerificationNotificationController': 'EmailVerification',
    'EmailVerificationPromptController': 'EmailVerification',
    'VerifyEmailController': 'EmailVerification',
    'ProfileController': 'Profile',
    'TwoFactorChallengeController': 'MultiFactorAuthentication',
    'TwoFactorController': 'MultiFactorAuthentication',
}.items():
    rep(fr'App\Contexts\Accounts\Http\Controllers\{name}', fr'App\Contexts\Accounts\{capability}\Http\Controllers\{name}')

# GameWorld
for name in ['KingdomAlliance', 'Kingdom']:
    rep(fr'App\Contexts\GameWorld\Models\{name}', fr'App\Contexts\GameWorld\Kingdoms\Models\{name}')
rep(r'App\Contexts\GameWorld\Models\Player', r'App\Contexts\GameWorld\Players\Models\Player')
for name in ['ClaimPlayerAccount', 'PersistPlayerIdentity']:
    rep(fr'App\Contexts\GameWorld\Actions\{name}', fr'App\Contexts\GameWorld\Players\Actions\{name}')
for name in ['ResolveKingdomAlliance', 'ResolveKingdom']:
    rep(fr'App\Contexts\GameWorld\Actions\{name}', fr'App\Contexts\GameWorld\Kingdoms\Actions\{name}')
for name in ['KingdomAllianceStatus', 'KingdomStatus']:
    rep(fr'App\Contexts\GameWorld\Enums\{name}', fr'App\Contexts\GameWorld\Kingdoms\Enums\{name}')
rep(r'App\Contexts\GameWorld\Queries\PlayerOwnershipQuery', r'App\Contexts\GameWorld\Players\Queries\PlayerOwnershipQuery')
rep(r'App\Contexts\GameWorld\Services\PlayerContext', r'App\Contexts\GameWorld\Players\Services\PlayerContext')
rep(r'App\Contexts\GameWorld\Http\Middleware\ResolvePlayerContext', r'App\Contexts\GameWorld\Players\Http\Middleware\ResolvePlayerContext')

# Context/capability renames
rep(r'App\Contexts\Alliance\Core\', r'App\Contexts\Alliance\Lifecycle\')
rep(r'App\Contexts\Operations\EventCore\', r'App\Contexts\Operations\Events\')
rep(r'App\Contexts\Intelligence\Http\Controllers\KingdomAllianceIntelligenceController', r'App\Contexts\Intelligence\Diplomacy\Http\Controllers\KingdomAllianceIntelligenceController')
rep(r'App\Contexts\Communications\Reminders\Actions\MarkEventReminderSent', r'App\Contexts\Operations\Participation\Reminders\Actions\MarkEventReminderSent')
rep(r'App\Contexts\Communications\Reminders\Actions\MarkKingPerkReminderSent', r'App\Contexts\Operations\KingPerks\Reminders\Actions\MarkKingPerkReminderSent')

# Platform
rep(r'App\Contexts\Platform\Access\', r'App\Contexts\Platform\Administration\')
for name in ['ManagePlatformAdministrator']:
    rep(fr'App\Contexts\Platform\Actions\{name}', fr'App\Contexts\Platform\Administration\Actions\{name}')
for name in ['ConfigureAlliancePlatform', 'ManageAllianceLifecycle']:
    rep(fr'App\Contexts\Platform\Actions\{name}', fr'App\Contexts\Platform\AllianceAdministration\Actions\{name}')
for name in ['EnforcePlatformRetention', 'ProcessAccountDeletionRequests']:
    rep(fr'App\Contexts\Platform\Actions\{name}', fr'App\Contexts\Platform\DataGovernance\Actions\{name}')
for name in ['AlliancePlatformSetting', 'AllianceFeatureFlag', 'AllianceUsageSnapshot']:
    rep(fr'App\Contexts\Platform\Models\{name}', fr'App\Contexts\Platform\AllianceAdministration\Models\{name}')
for name in ['AccountDeletionRequest', 'LegalHold']:
    rep(fr'App\Contexts\Platform\Models\{name}', fr'App\Contexts\Platform\DataGovernance\Models\{name}')
for name in ['AllianceFeatureService', 'PlanEntitlementService', 'PlatformUsageService']:
    rep(fr'App\Contexts\Platform\Services\{name}', fr'App\Contexts\Platform\AllianceAdministration\Services\{name}')
for name in ['AllianceDataExportService', 'LegalHoldService']:
    rep(fr'App\Contexts\Platform\Services\{name}', fr'App\Contexts\Platform\DataGovernance\Services\{name}')
for name in ['PlatformAdministratorAuthorization', 'PlatformAdministratorBootstrapCoordinator', 'ProductionLaunchReadiness']:
    rep(fr'App\Contexts\Platform\Services\{name}', fr'App\Contexts\Platform\Administration\Services\{name}')
rep(r'App\Contexts\Platform\Services\RuntimeConfigurationValidator', r'App\Shared\Infrastructure\Runtime\Services\RuntimeConfigurationValidator')
rep(r'App\Contexts\Platform\Http\Controllers\PlatformAdministrationController', r'App\Contexts\Platform\Administration\Http\Controllers\PlatformAdministrationController')
rep(r'App\Contexts\Platform\Http\Controllers\ReadinessController', r'App\Shared\Infrastructure\Runtime\Http\Controllers\ReadinessController')
rep(r'App\Contexts\Platform\Http\Middleware\AssignRequestContext', r'App\Shared\Infrastructure\Observability\Http\Middleware\AssignRequestContext')
rep(r'App\Contexts\Platform\Http\Middleware\RecordRequestMetrics', r'App\Shared\Infrastructure\Observability\Http\Middleware\RecordRequestMetrics')
rep(r'App\Contexts\Platform\Http\Middleware\SecurityHeaders', r'App\Shared\Infrastructure\Security\Http\Middleware\SecurityHeaders')
rep(r'App\Contexts\Platform\Http\Middleware\HandleInertiaRequests', r'App\Contexts\GameWorld\Players\Http\Middleware\HandleInertiaRequests')
rep(r'App\Contexts\Platform\Http\Middleware\RequirePlatformAdministrator', r'App\Contexts\Platform\Administration\Http\Middleware\RequirePlatformAdministrator')
rep(r'App\Contexts\Platform\Queries\PlatformAdministrationQuery', r'App\Contexts\Platform\Administration\Queries\PlatformAdministrationQuery')

# Shared
rep(r'App\Shared\Access\', r'App\Shared\Infrastructure\Access\')
rep(r'App\Shared\Http\Controller', r'App\Shared\Infrastructure\Http\Controller')
rep(r'App\Shared\Providers\SharedServiceProvider', r'App\Shared\Infrastructure\Providers\InfrastructureServiceProvider')

for scan_root in [Path(p) for p in ['app', 'routes', 'config', 'database', 'bootstrap', 'bin'] if Path(p).exists()]:
    for path in scan_root.rglob('*'):
        if path.is_file() and path.suffix in {'.php', '.md', '.json', '.xml', '.yml', '.yaml'}:
            replace_file(path, R)

# Namespace declarations for files split out of broad Platform technical buckets.
namespace_fixes = {
    'app/Contexts/Platform/Administration/Http/Controllers/PlatformAdministrationController.php': (r'namespace App\Contexts\Platform\Http\Controllers;', r'namespace App\Contexts\Platform\Administration\Http\Controllers;'),
    'app/Contexts/Platform/Administration/Queries/PlatformAdministrationQuery.php': (r'namespace App\Contexts\Platform\Queries;', r'namespace App\Contexts\Platform\Administration\Queries;'),
    'app/Contexts/Platform/Administration/Services/ProductionLaunchReadiness.php': (r'namespace App\Contexts\Platform\Services;', r'namespace App\Contexts\Platform\Administration\Services;'),
    'app/Shared/Infrastructure/Runtime/Services/RuntimeConfigurationValidator.php': (r'namespace App\Contexts\Platform\Services;', r'namespace App\Shared\Infrastructure\Runtime\Services;'),
    'app/Shared/Infrastructure/Runtime/Http/Controllers/ReadinessController.php': (r'namespace App\Contexts\Platform\Http\Controllers;', r'namespace App\Shared\Infrastructure\Runtime\Http\Controllers;'),
    'app/Shared/Infrastructure/Observability/Http/Middleware/AssignRequestContext.php': (r'namespace App\Contexts\Platform\Http\Middleware;', r'namespace App\Shared\Infrastructure\Observability\Http\Middleware;'),
    'app/Shared/Infrastructure/Observability/Http/Middleware/RecordRequestMetrics.php': (r'namespace App\Contexts\Platform\Http\Middleware;', r'namespace App\Shared\Infrastructure\Observability\Http\Middleware;'),
    'app/Shared/Infrastructure/Security/Http/Middleware/SecurityHeaders.php': (r'namespace App\Contexts\Platform\Http\Middleware;', r'namespace App\Shared\Infrastructure\Security\Http\Middleware;'),
    'app/Contexts/GameWorld/Players/Http/Middleware/HandleInertiaRequests.php': (r'namespace App\Contexts\Platform\Http\Middleware;', r'namespace App\Contexts\GameWorld\Players\Http\Middleware;'),
}
for filename, pair in namespace_fixes.items():
    path = Path(filename)
    if not path.exists():
        raise RuntimeError(f'moved file missing: {filename}')
    replace_file(path, [pair])

# Additive V3 structural tests. Existing tests remain untouched.
test_dir = Path('tests/v3/Architecture')
test_dir.mkdir(parents=True, exist_ok=True)
(test_dir / 'CapabilityFirstSourceLayoutV3Test.php').write_text(r'''<?php

declare(strict_types=1);

namespace Tests\V3\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CapabilityFirstSourceLayoutV3Test extends TestCase
{
    #[Test]
    public function only_the_seven_business_context_roots_exist(): void
    {
        $root = dirname(__DIR__, 3).'/app/Contexts';
        $actual = array_values(array_map('basename', array_filter(glob($root.'/*') ?: [], 'is_dir')));
        sort($actual);
        $expected = ['Accounts', 'Alliance', 'Communications', 'GameWorld', 'Intelligence', 'Operations', 'Platform'];
        sort($expected);
        self::assertSame($expected, $actual);
    }

    #[Test]
    public function context_roots_do_not_expose_technical_layer_buckets(): void
    {
        $forbidden = ['Actions','Catalog','Contracts','Enums','Events','Http','Jobs','Listeners','Models','Policies','Providers','Queries','Services','ValueObjects'];
        foreach (glob(dirname(__DIR__, 3).'/app/Contexts/*', GLOB_ONLYDIR) ?: [] as $context) {
            foreach ($forbidden as $name) {
                self::assertDirectoryDoesNotExist($context.'/'.$name, basename($context).' exposes root technical bucket '.$name);
            }
        }
    }

    #[Test]
    public function removed_pre_v3_packages_do_not_exist(): void
    {
        $app = dirname(__DIR__, 3).'/app';
        foreach (['Contexts/Alliance/Core','Contexts/Alliance/Policies','Contexts/Operations/EventCore','Contexts/Intelligence/Http','Contexts/Communications/Reminders'] as $relative) {
            self::assertDirectoryDoesNotExist($app.'/'.$relative);
        }
    }

    #[Test]
    public function shared_has_only_the_infrastructure_package(): void
    {
        $root = dirname(__DIR__, 3).'/app/Shared';
        $directories = array_values(array_map('basename', array_filter(glob($root.'/*') ?: [], 'is_dir')));
        sort($directories);
        self::assertSame(['Infrastructure'], $directories);
    }
}
''')

(test_dir / 'NamespaceLocationV3Test.php').write_text(r'''<?php

declare(strict_types=1);

namespace Tests\V3\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class NamespaceLocationV3Test extends TestCase
{
    #[Test]
    public function app_php_files_do_not_reference_removed_architecture_namespaces(): void
    {
        $forbidden = [
            'App\\Contexts\\Accounts\\Models\\','App\\Contexts\\Accounts\\Actions\\','App\\Contexts\\Accounts\\Http\\','App\\Contexts\\Accounts\\Services\\',
            'App\\Contexts\\GameWorld\\Models\\','App\\Contexts\\GameWorld\\Actions\\','App\\Contexts\\GameWorld\\Enums\\','App\\Contexts\\GameWorld\\Queries\\','App\\Contexts\\GameWorld\\Services\\','App\\Contexts\\GameWorld\\Http\\',
            'App\\Contexts\\Alliance\\Core\\','App\\Contexts\\Alliance\\Policies\\','App\\Contexts\\Operations\\EventCore\\','App\\Contexts\\Intelligence\\Http\\','App\\Contexts\\Communications\\Reminders\\',
            'App\\Contexts\\Platform\\Access\\','App\\Contexts\\Platform\\Actions\\','App\\Contexts\\Platform\\Models\\','App\\Contexts\\Platform\\Services\\','App\\Contexts\\Platform\\Queries\\','App\\Contexts\\Platform\\Http\\','App\\Contexts\\Platform\\Providers\\',
            'App\\Shared\\Access\\','App\\Shared\\Http\\','App\\Shared\\Providers\\',
        ];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3).'/app'));
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') continue;
            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents);
            foreach ($forbidden as $namespace) {
                self::assertStringNotContainsString($namespace, $contents, $file->getPathname().' references '.$namespace);
            }
        }
    }
}
''')

# Fail closed if old architecture remains in runtime/source surfaces.
FORBIDDEN_NAMESPACES = [
    r'App\Contexts\Accounts\Models\', r'App\Contexts\Accounts\Actions\', r'App\Contexts\Accounts\Http\', r'App\Contexts\Accounts\Services\',
    r'App\Contexts\GameWorld\Models\', r'App\Contexts\GameWorld\Actions\', r'App\Contexts\GameWorld\Enums\', r'App\Contexts\GameWorld\Queries\', r'App\Contexts\GameWorld\Services\', r'App\Contexts\GameWorld\Http\',
    r'App\Contexts\Alliance\Core\', r'App\Contexts\Alliance\Policies\', r'App\Contexts\Operations\EventCore\', r'App\Contexts\Intelligence\Http\', r'App\Contexts\Communications\Reminders\',
    r'App\Contexts\Platform\Access\', r'App\Contexts\Platform\Actions\', r'App\Contexts\Platform\Models\', r'App\Contexts\Platform\Services\', r'App\Contexts\Platform\Queries\', r'App\Contexts\Platform\Http\', r'App\Contexts\Platform\Providers\',
    r'App\Shared\Access\', r'App\Shared\Http\', r'App\Shared\Providers\',
]
FORBIDDEN_PATHS = [
    'app/Contexts/Accounts/Actions','app/Contexts/Accounts/Http','app/Contexts/Accounts/Models','app/Contexts/Accounts/Services',
    'app/Contexts/GameWorld/Actions','app/Contexts/GameWorld/Enums','app/Contexts/GameWorld/Http','app/Contexts/GameWorld/Models','app/Contexts/GameWorld/Queries','app/Contexts/GameWorld/Services',
    'app/Contexts/Alliance/Core','app/Contexts/Alliance/Policies','app/Contexts/Operations/EventCore','app/Contexts/Intelligence/Http','app/Contexts/Communications/Reminders',
    'app/Contexts/Platform/Access','app/Contexts/Platform/Actions','app/Contexts/Platform/Http','app/Contexts/Platform/Models','app/Contexts/Platform/Providers','app/Contexts/Platform/Queries','app/Contexts/Platform/Services',
    'app/Shared/Access','app/Shared/Http','app/Shared/Providers',
]
offenders = []
for scan_root in [Path(p) for p in ['app','routes','config','database','bootstrap','bin'] if Path(p).exists()]:
    for path in scan_root.rglob('*'):
        if not path.is_file() or path.suffix not in {'.php','.md','.json','.xml','.yml','.yaml'}:
            continue
        try:
            text = path.read_text()
        except UnicodeDecodeError:
            continue
        for needle in FORBIDDEN_NAMESPACES:
            if needle in text:
                offenders.append(f'{path}: {needle}')
for path in FORBIDDEN_PATHS:
    if Path(path).exists():
        offenders.append(f'forbidden path remains: {path}')
if offenders:
    print('\n'.join(offenders))
    sys.exit(1)

print('CA-P1 mechanical rewrite checks passed.')
