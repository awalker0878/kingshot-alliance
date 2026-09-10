<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$violations = [];
$record = static function (string $code, string $detail) use (&$violations): void {
    $violations[] = $code.': '.$detail;
};

require $root.'/scripts/verify-test-layout.php';

$legacyFiles = [];
$legacyRoot = $root.'/tests/v2';
if (is_dir($legacyRoot)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($legacyRoot));
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $legacyFiles[] = str_replace($root.'/', '', $file->getPathname());
        }
    }
}
if ($legacyFiles !== []) {
    $record('LEGACY_V2_TESTS', implode(', ', $legacyFiles));
}

$requiredBehaviorTests = [
    'tests/Contexts/Accounts/Registration/Feature/AccountRegistrationBehaviorV3Test.php',
    'tests/Feature/Contexts/GameWorld/Players/PlayerIdentityBehaviorV3Test.php',
    'tests/Feature/Contexts/GameWorld/Players/PlayerContextSelectionBehaviorV3Test.php',
    'tests/Feature/Contexts/GameWorld/Governance/KingdomGovernanceBehaviorV3Test.php',
    'tests/Contexts/Alliance/Access/Feature/AllianceAuthorityIsolationV3Test.php',
    'tests/Contexts/Alliance/Lifecycle/Feature/AllianceLifecycleBehaviorV3Test.php',
    'tests/Contexts/Alliance/Membership/Feature/MemberCapacityPolicyBehaviorV3Test.php',
    'tests/Unit/Contexts/Operations/Events/RecurrencePolicyBehaviorV3Test.php',
    'tests/Unit/Contexts/Operations/KingPerks/KingPerkPolicyBehaviorV3Test.php',
    'tests/Feature/Contexts/Platform/Administration/PlatformAdministrationBehaviorV3Test.php',
    'tests/Feature/Contexts/Platform/Administration/PlatformAdministratorIsolationV3Test.php',
    'tests/Integration/Shared/Infrastructure/InfrastructureBehaviorV3Test.php',
];
foreach ($requiredBehaviorTests as $relative) {
    if (! is_file($root.'/'.$relative)) {
        $record('MISSING_V3_BEHAVIOR_TEST', $relative);
    }
}

$forbiddenSymbols = [
    'Tests\\v2',
    'App\\Contexts\\GameWorld\\Actions\\',
    'App\\Contexts\\GameWorld\\Models\\',
    'App\\Contexts\\Alliance\\Core\\',
    'App\\Contexts\\Alliance\\Policies\\',
    'App\\Contexts\\Operations\\EventCore\\',
    'App\\Contexts\\Platform\\Access\\',
    'App\\Workflows\\PlayerContext\\',
    'App\\Workflows\\Registration\\',
];

$v3Root = $root.'/tests';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($v3Root));
foreach ($iterator as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $source = file_get_contents($file->getPathname()) ?: '';
    foreach ($forbiddenSymbols as $symbol) {
        if (str_contains($source, $symbol)) {
            $record('V3_TEST_LEGACY_SYMBOL', str_replace($root.'/', '', $file->getPathname()).' -> '.$symbol);
        }
    }
}

$contracts = [
    'app/Contexts/Accounts/Registration/Actions/RegisterUser.php' => [
        '): RegisteredAccount',
    ],
    'app/Contexts/GameWorld/Players/Actions/ClaimPlayerAccount.php' => [
        'public function handle(string $playerId, int $userId): PlayerReference',
    ],
    'app/Contexts/GameWorld/Players/Services/PlayerContext.php' => [
        'private ?PlayerReference $player = null;',
        'public function activate(PlayerReference $player, int $authenticatedUserId): void',
        'public function player(): PlayerReference',
    ],
    'app/Contexts/Alliance/Lifecycle/Actions/CreateAlliance.php' => [
        'public function handle(int $actorUserId, string $ownerPlayerId, string $name, string $slug, string $language = \'en\', string $timezone = \'UTC\'): string',
    ],
    'app/Contexts/GameWorld/Governance/Actions/BootstrapKingdomAdministrator.php' => [
        'public function handle(string $kingdomId, string $targetPlayerId): KingdomAdministratorBootstrap',
    ],
    'app/Contexts/Platform/Administration/ValueObjects/PlatformMutationContext.php' => [
        'public AccountIdentity $actor,',
        'public string $grantId,',
    ],
];
foreach ($contracts as $relative => $needles) {
    $source = file_get_contents($root.'/'.$relative) ?: '';
    foreach ($needles as $needle) {
        if (! str_contains($source, $needle)) {
            $record('BEHAVIOR_CONTRACT_DRIFT', $relative.' missing `'.$needle.'`');
        }
    }
}

if ($violations !== []) {
    fwrite(STDERR, "V3 behavior contract verification failed:\n - ".implode("\n - ", $violations)."\n");
    exit(1);
}

fwrite(STDOUT, "V3 behavior contract verification passed.\n");
