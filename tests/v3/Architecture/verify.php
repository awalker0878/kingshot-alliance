<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$app = $root.'/app';
$failures = [];

$record = static function (string $rule, string $path, string $detail) use (&$failures, $root): void {
    $relative = str_starts_with($path, $root.'/') ? substr($path, strlen($root) + 1) : $path;
    $failures[] = [$rule, $relative, $detail];
};

$phpFiles = static function (string $dir): array {
    if (! is_dir($dir)) {
        return [];
    }
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    sort($files);

    return $files;
};

$contextFromPath = static function (string $path): ?string {
    if (preg_match('#/app/Contexts/([^/]+)/#', str_replace('\\', '/', $path), $m) === 1) {
        return $m[1];
    }

    return null;
};

$imports = static function (string $source): array {
    preg_match_all('/^use\s+([^;]+);/m', $source, $matches);

    return array_values(array_map('trim', $matches[1] ?? []));
};

$modelImportParts = static function (string $import): ?array {
    if (! str_starts_with($import, 'App\\Contexts\\')) {
        return null;
    }
    $parts = explode('\\', $import);
    $modelsIndex = array_search('Models', $parts, true);
    if ($modelsIndex === false || $modelsIndex < 3 || ! isset($parts[$modelsIndex + 1])) {
        return null;
    }

    return ['context' => $parts[2], 'short' => $parts[$modelsIndex + 1]];
};

// CA-P0/P1: exact business contexts and no technical-layer folders at Context root.
$expectedContexts = ['Accounts', 'Alliance', 'Communications', 'GameWorld', 'Intelligence', 'Operations', 'Platform'];
$actualContexts = array_values(array_filter(scandir($app.'/Contexts') ?: [], static fn (string $name): bool => $name !== '.' && $name !== '..' && is_dir($app.'/Contexts/'.$name)));
sort($actualContexts);
$sortedExpected = $expectedContexts;
sort($sortedExpected);
if ($actualContexts !== $sortedExpected) {
    $record('CONTEXT_SET', $app.'/Contexts', 'Expected exactly: '.implode(', ', $sortedExpected).'; found: '.implode(', ', $actualContexts));
}
foreach ($expectedContexts as $context) {
    foreach (['Actions', 'Models', 'Queries', 'Services', 'Policies', 'Http'] as $technical) {
        $path = $app.'/Contexts/'.$context.'/'.$technical;
        if (is_dir($path)) {
            $record('CONTEXT_ROOT_TECHNICAL_DIR', $path, 'Technical layers must live inside a capability.');
        }
    }
}

// Contexts cannot depend upward on workflows/read models. Workflows cannot import Eloquent models.
foreach ($phpFiles($app.'/Contexts') as $file) {
    $source = file_get_contents($file) ?: '';
    foreach ($imports($source) as $import) {
        if (str_starts_with($import, 'App\\Workflows\\')) {
            $record('CONTEXT_IMPORTS_WORKFLOW', $file, $import);
        }
        if (str_starts_with($import, 'App\\ReadModels\\')) {
            $record('CONTEXT_IMPORTS_READMODEL', $file, $import);
        }
    }
}

// Business contexts may not import another context's Eloquent models.
// The authenticated Accounts\Identity\User model is allowed only at an HTTP/authentication boundary.
foreach ($phpFiles($app.'/Contexts') as $file) {
    $source = file_get_contents($file) ?: '';
    $owner = $contextFromPath($file);
    $normalized = str_replace('\\', '/', $file);
    foreach ($imports($source) as $import) {
        $parts = $modelImportParts($import);
        if ($parts === null || $parts['context'] === $owner) {
            continue;
        }
        $isAuthenticatedUserBoundary = $import === 'App\\Contexts\\Accounts\\Identity\\Models\\User'
            && str_contains($normalized, '/Http/');
        if (! $isAuthenticatedUserBoundary) {
            $record('FOREIGN_CONTEXT_MODEL_IMPORT', $file, $import);
        }
    }
}
foreach ($phpFiles($app.'/Workflows') as $file) {
    $source = file_get_contents($file) ?: '';
    foreach ($imports($source) as $import) {
        if ($modelImportParts($import) !== null) {
            $record('WORKFLOW_IMPORTS_MODEL', $file, $import);
        }
    }
    if (preg_match('/\b(DB::transaction|lockForUpdate\s*\(|->save\s*\(|->update\s*\(|->delete\s*\(|::create\s*\()/m', $source) === 1) {
        $record('WORKFLOW_OWNS_WRITE', $file, 'Workflow contains transaction/direct persistence.');
    }
}

foreach (['Models', 'Repositories', 'Migrations', 'migrations'] as $forbiddenWorkflowLayer) {
    foreach (glob($app.'/Workflows/*/'.$forbiddenWorkflowLayer, GLOB_ONLYDIR) ?: [] as $path) {
        $record('WORKFLOW_OWNS_DOMAIN_LAYER', $path, 'Workflows coordinate contexts and may not own '.$forbiddenWorkflowLayer.'.');
    }
}
foreach ($phpFiles($app.'/Workflows') as $file) {
    $source = file_get_contents($file) ?: '';
    foreach ($imports($source) as $import) {
        if (str_starts_with($import, 'App\\Contexts\\') && (str_ends_with($import, 'Permission') || str_contains($import, '\\Permissions\\'))) {
            $record('WORKFLOW_INTERPRETS_PERMISSION', $file, $import);
        }
    }
}

// Request/security contexts must never expose Eloquent models downstream.
foreach ($phpFiles($app) as $file) {
    $normalized = str_replace('\\', '/', $file);
    $base = basename($file, '.php');
    $looksLikeContext = preg_match('/(Context|CurrentPlayer|ActivePlayer|CurrentAlliance|CurrentKingdom|AuthorityContext|SecurityContext|ActorContext|MembershipContext)$/', $base) === 1
        && ! str_ends_with($base, 'MutationContext')
        && ! str_contains($normalized, '/Models/')
        && ! str_contains($normalized, '/Http/Middleware/');
    if (! $looksLikeContext) {
        continue;
    }
    $source = file_get_contents($file) ?: '';
    foreach ($imports($source) as $import) {
        if ($modelImportParts($import) !== null || $import === 'Illuminate\\Database\\Eloquent\\Model') {
            $record('SECURITY_CONTEXT_MODEL', $file, $import);
        }
    }
}

// Public application write contracts must not accept Eloquent models. Internal persistence helpers are allowed.
$writePathPattern = '#/(Actions|Commands|Jobs|Listeners|Subscribers|Workflows)/#';
foreach ($phpFiles($app) as $file) {
    $normalized = str_replace('\\', '/', $file);
    if (preg_match($writePathPattern, $normalized) !== 1) {
        continue;
    }
    $source = file_get_contents($file) ?: '';
    $modelShortNames = [];
    foreach ($imports($source) as $import) {
        if (($parts = $modelImportParts($import)) !== null) {
            $modelShortNames[$parts['short']] = $import;
        }
    }
    if ($modelShortNames === []) {
        continue;
    }
    if (preg_match_all('/public\s+function\s+(handle|execute|__invoke)\s*\((.*?)\)\s*(?:\:\s*([^\{]+))?\{/s', $source, $methods, PREG_SET_ORDER) < 1) {
        continue;
    }
    foreach ($methods as $method) {
        $params = $method[2];
        $returnType = trim($method[3] ?? '');
        foreach ($modelShortNames as $short => $fqcn) {
            $typePattern = '/(?<![A-Za-z0-9_])'.preg_quote($short, '/').'(?=\s+[\$]|\s*[|&])/';
            if (preg_match($typePattern, $params) === 1) {
                $record('WRITE_CONTRACT_MODEL', $file, $method[1].'() accepts '.$fqcn);
            }
            $returnPattern = '/(?<![A-Za-z0-9_])'.preg_quote($short, '/').'(?![A-Za-z0-9_])/';
            if ($returnType !== '' && preg_match($returnPattern, $returnType) === 1) {
                $record('WRITE_CONTRACT_RETURNS_MODEL', $file, $method[1].'() returns '.$fqcn);
            }
        }
    }
}

// King Perks application write services are explicit boundary APIs, not persistence helpers.
// Keep this targeted until CA-P6 generalizes service-contract classification repository-wide.
foreach ([
    $app.'/Contexts/Operations/KingPerks/Services/KingPerkScheduler.php',
    $app.'/Contexts/Operations/KingPerks/Services/KingPerkRequestService.php',
    $app.'/Contexts/Operations/KingPerks/Services/KingPerkAutoScheduler.php',
] as $file) {
    if (! is_file($file)) {
        continue;
    }
    $source = file_get_contents($file) ?: '';
    $modelShortNames = [];
    foreach ($imports($source) as $import) {
        if (($parts = $modelImportParts($import)) !== null) {
            $modelShortNames[$parts['short']] = $import;
        }
    }
    if ($modelShortNames === []) {
        continue;
    }
    if (preg_match_all('/public\s+function\s+(\w+)\s*\((.*?)\)\s*(?::|\{)/s', $source, $methods, PREG_SET_ORDER) < 1) {
        continue;
    }
    foreach ($methods as $method) {
        foreach ($modelShortNames as $short => $fqcn) {
            $typePattern = '/(?<![A-Za-z0-9_])'.preg_quote($short, '/').'(?=\s+[\$]|\s*[|&])/';
            if (preg_match($typePattern, $method[2]) === 1) {
                $record('WRITE_SERVICE_CONTRACT_MODEL', $file, $method[1].'() accepts '.$fqcn);
            }
        }
    }
}

// Write actions may invoke owner-owned semantic authorization APIs, but may not interpret another context's permission vocabulary.
foreach ($phpFiles($app.'/Contexts') as $file) {
    $normalized = str_replace('\\', '/', $file);
    if (! str_contains($normalized, '/Actions/')) {
        continue;
    }
    $source = file_get_contents($file) ?: '';
    $owner = $contextFromPath($file);
    foreach ($imports($source) as $import) {
        if (! str_starts_with($import, 'App\\Contexts\\')) {
            continue;
        }
        $parts = explode('\\', $import);
        if (($parts[2] ?? null) === $owner) {
            continue;
        }
        if (str_ends_with($import, 'Permission') || str_contains($import, '\\Permissions\\')) {
            $record('WRITE_INTERPRETS_FOREIGN_PERMISSION', $file, $import);
        }
    }
}

// MutationAuthority was an interim abstraction and must not be reintroduced.
foreach ($phpFiles($app) as $file) {
    $source = file_get_contents($file) ?: '';
    if (str_contains(basename($file), 'MutationAuthority') || preg_match('/\b(?:class|interface|trait|enum)\s+\w*MutationAuthority\w*/', $source) === 1) {
        $record('MUTATION_AUTHORITY_REINTRODUCED', $file, 'Use owner write-state/action semantics instead.');
    }
}

// Removed V2/V3 bridge namespaces and deleted live-model authority accessors must remain absent.
$removedSymbols = [
    'App\\Contexts\\Intelligence\\EventAnalysis',
    'App\\Contexts\\Intelligence\\Roster\\Queries\\RosterQuery',
    'App\\Contexts\\Intelligence\\Roster\\Queries\\PlayerSnapshotQuery',
    'App\\Contexts\\Intelligence\\Roster\\Services\\RosterIntelligence',
];
foreach ($phpFiles($app) as $file) {
    $source = file_get_contents($file) ?: '';
    foreach ($removedSymbols as $symbol) {
        if (str_contains($source, $symbol)) {
            $record('REMOVED_BRIDGE_SYMBOL', $file, $symbol);
        }
    }
}
$allianceContextFile = $app.'/Contexts/Alliance/Lifecycle/Services/AllianceContext.php';
if (is_file($allianceContextFile)) {
    $source = file_get_contents($allianceContextFile) ?: '';
    foreach (['alliance', 'player'] as $method) {
        if (preg_match('/public\s+function\s+'.preg_quote($method, '/').'\s*\(/', $source) === 1) {
            $record('LIVE_MODEL_AUTHORITY_ACCESSOR', $allianceContextFile, $method.'() must not be restored.');
        }
    }
}

// Cross-context Eloquent relationship navigation is prohibited.
$relationTypes = '(BelongsTo|HasMany|HasOne|BelongsToMany|MorphTo|MorphMany|MorphOne|MorphToMany|MorphedByMany|HasManyThrough|HasOneThrough)';
foreach ($phpFiles($app.'/Contexts') as $file) {
    if (! str_contains(str_replace('\\', '/', $file), '/Models/')) {
        continue;
    }
    $source = file_get_contents($file) ?: '';
    $owner = $contextFromPath($file);
    foreach ($imports($source) as $import) {
        if (($parts = $modelImportParts($import)) === null) {
            continue;
        }
        if ($owner === $parts['context']) {
            continue;
        }
        $short = $parts['short'];
        if (preg_match('/function\s+\w+\s*\([^)]*\)\s*:\s*'.$relationTypes.'.*?(?:belongsTo|hasMany|hasOne|belongsToMany|morphTo|morphMany|morphOne|morphToMany|morphedByMany|hasManyThrough|hasOneThrough)\s*\(\s*'.preg_quote($short, '/').'::class/s', $source) === 1) {
            $record('CROSS_CONTEXT_RELATIONSHIP', $file, 'Relationship navigates to '.$import);
        }
    }
}

// Authorization/policy/access services interpret authority; they do not acquire DB locks/transactions.
foreach ($phpFiles($app.'/Contexts') as $file) {
    $base = basename($file, '.php');
    $normalized = str_replace('\\', '/', $file);
    if (preg_match('/(Authorization|Policy)$/', $base) !== 1 && ! str_contains($normalized, '/Policies/')) {
        continue;
    }
    $source = file_get_contents($file) ?: '';
    if (preg_match('/\bDB::transaction\s*\(|->lockForUpdate\s*\(/', $source) === 1) {
        $record('AUTHORIZATION_ACQUIRES_LOCK', $file, 'Move transaction/lock acquisition to owner write-state/action.');
    }
}

// HTTP adapters must not own transactions or direct persistence.
foreach ($phpFiles($app) as $file) {
    $normalized = str_replace('\\', '/', $file);
    if (! str_contains($normalized, '/Http/')) {
        continue;
    }
    $source = file_get_contents($file) ?: '';
    if (preg_match('/\bDB::transaction\s*\(|->lockForUpdate\s*\(|->save\s*\(|->delete\s*\(|::create\s*\(/', $source) === 1) {
        $record('HTTP_DIRECT_WRITE', $file, 'HTTP adapter contains transaction/direct persistence.');
    }
}

// ReadModels own no writes.
foreach ($phpFiles($app.'/ReadModels') as $file) {
    $source = file_get_contents($file) ?: '';
    if (preg_match('/\bDB::transaction\s*\(|->lockForUpdate\s*\(|->save\s*\(|->update\s*\(|->delete\s*\(|::create\s*\(/', $source) === 1) {
        $record('READMODEL_WRITES', $file, 'ReadModel contains mutation/transaction code.');
    }
}

// Communications is delivery-only and must not know Operations reminder semantics.
foreach ($phpFiles($app.'/Contexts/Communications') as $file) {
    $source = file_get_contents($file) ?: '';
    foreach ($imports($source) as $import) {
        if (str_starts_with($import, 'App\\Contexts\\Operations\\')) {
            $record('COMMUNICATIONS_IMPORTS_OPERATIONS', $file, $import);
        }
    }
    if (preg_match('/(EventReminder|KingPerkReminder|MarkEventReminderSent|MarkKingPerkReminderSent)/', $source) === 1) {
        $record('COMMUNICATIONS_OWNS_REMINDER_SEMANTICS', $file, 'Operations reminder meaning must remain in Operations.');
    }
}

// Only the intended cross-context workflows may exist.
if (is_dir($app.'/Workflows')) {
    $workflowDirs = array_values(array_filter(scandir($app.'/Workflows') ?: [], static fn (string $name): bool => $name !== '.' && $name !== '..' && is_dir($app.'/Workflows/'.$name)));
    sort($workflowDirs);
    $allowed = ['AccountOnboarding', 'ExternalEventParticipation', 'KingdomGovernance'];
    sort($allowed);
    if ($workflowDirs !== $allowed) {
        $record('WORKFLOW_SET', $app.'/Workflows', 'Expected '.implode(', ', $allowed).'; found '.implode(', ', $workflowDirs));
    }
}

if ($failures !== []) {
    usort($failures, static fn (array $a, array $b): int => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);
    $counts = [];
    foreach ($failures as [$rule]) {
        $counts[$rule] = ($counts[$rule] ?? 0) + 1;
    }
    ksort($counts);
    fwrite(STDERR, 'V3 architecture verification failed ('.count($failures)." violations):\n");
    foreach ($counts as $rule => $count) {
        fwrite(STDERR, sprintf("  %-44s %d\n", $rule, $count));
    }
    fwrite(STDERR, "\nDetails:\n");
    foreach ($failures as [$rule, $path, $detail]) {
        fwrite(STDERR, "[$rule] $path — $detail\n");
    }
    exit(1);
}

fwrite(STDOUT, "V3 architecture verification passed.\n");
