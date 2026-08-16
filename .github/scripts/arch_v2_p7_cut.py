from __future__ import annotations

from pathlib import Path
import re
import shutil

repo = Path('.')
replacements: dict[str, str] = {}


def php_fqcn(path: Path, text: str) -> str:
    match = re.search(r'^namespace\s+([^;]+);', text, re.M)
    if not match:
        raise RuntimeError(f'No namespace in {path}')
    return match.group(1) + '\\' + path.stem


def move_php(src: Path, dst: Path) -> None:
    if not src.is_file():
        raise RuntimeError(f'Missing source: {src}')
    if dst.exists():
        raise RuntimeError(f'Destination already exists: {dst}')

    original = src.read_text(encoding='utf-8')
    old_fqcn = php_fqcn(src, original)
    old_ns = old_fqcn.rsplit('\\', 1)[0]
    rel_parent = dst.parent.relative_to(repo / 'app')
    new_ns = 'App\\' + str(rel_parent).replace('/', '\\')
    rewritten = original.replace(f'namespace {old_ns};', f'namespace {new_ns};', 1)

    dst.parent.mkdir(parents=True, exist_ok=True)
    dst.write_text(rewritten, encoding='utf-8')
    src.unlink()
    replacements[old_fqcn] = new_ns + '\\' + dst.stem


def move_tree(src_root: Path, dst_root: Path) -> None:
    if not src_root.is_dir():
        raise RuntimeError(f'Missing source tree: {src_root}')

    files = [path for path in src_root.rglob('*') if path.is_file()]
    for src in sorted(files):
        rel = src.relative_to(src_root)
        dst = dst_root / rel
        if src.suffix == '.php':
            move_php(src, dst)
        else:
            # V2 context READMEs are authoritative when a legacy root is merged
            # into an already-established destination context.
            if dst.exists() and src.name == 'README.md':
                src.unlink()
                continue
            if dst.exists():
                raise RuntimeError(f'Destination already exists: {dst}')
            dst.parent.mkdir(parents=True, exist_ok=True)
            shutil.move(str(src), str(dst))

    shutil.rmtree(src_root)


# P7 maps the remaining V1 integration/admin runtime into Platform. These are
# cohesive capability roots, so preserve their internal substructure while
# changing only their owner namespace.
move_tree(
    repo / 'app/Domain/Integrations',
    repo / 'app/Contexts/Platform/Integrations',
)
move_tree(
    repo / 'app/Domain/Platform',
    repo / 'app/Contexts/Platform',
)

# The live Notifications remainder is not one capability. Reminder delivery is
# Communications-owned, while contribution report scheduling mutates the
# Intelligence Contributions aggregate and belongs with that capability.
notification_moves = {
    'Actions/MarkEventReminderSent.php':
        'app/Contexts/Communications/Reminders/Actions/MarkEventReminderSent.php',
    'Actions/MarkKingPerkReminderSent.php':
        'app/Contexts/Communications/Reminders/Actions/MarkKingPerkReminderSent.php',
    'Actions/QueueDueEventReminders.php':
        'app/Contexts/Communications/Reminders/Actions/QueueDueEventReminders.php',
    'Actions/QueueDueKingPerkReminders.php':
        'app/Contexts/Communications/Reminders/Actions/QueueDueKingPerkReminders.php',
    'Services/EventReminderAudienceResolver.php':
        'app/Contexts/Communications/Reminders/Services/EventReminderAudienceResolver.php',
    'Actions/QueueDueContributionReports.php':
        'app/Contexts/Intelligence/Contributions/Actions/QueueDueContributionReports.php',
}
notifications = repo / 'app/Domain/Notifications'
if not notifications.is_dir():
    raise RuntimeError('Missing V1 Notifications tree.')
for relative, destination in notification_moves.items():
    move_php(notifications / relative, repo / destination)

remaining_notification_php = list(notifications.rglob('*.php'))
if remaining_notification_php:
    raise RuntimeError(
        'Unclassified V1 Notification PHP remains:\n' +
        '\n'.join(str(path) for path in remaining_notification_php)
    )
shutil.rmtree(notifications)

# Shared is a technical kernel, not a flat noun bucket. Re-home the already
# extracted Audit and Outbox packages under the explicit infrastructure boundary.
move_tree(
    repo / 'app/Shared/Audit',
    repo / 'app/Shared/Infrastructure/AuditTrail',
)
move_tree(
    repo / 'app/Shared/Messaging',
    repo / 'app/Shared/Infrastructure/Messaging/Outbox',
)

# Rewrite class references everywhere that can participate in runtime or tests.
# Prefix mappings cover strings/type references; exact class mappings cover the
# semantically split Notifications classes.
prefix_replacements = {
    'App\\Domain\\Integrations\\': 'App\\Contexts\\Platform\\Integrations\\',
    'App\\Domain\\Platform\\': 'App\\Contexts\\Platform\\',
    'App\\Shared\\Audit\\': 'App\\Shared\\Infrastructure\\AuditTrail\\',
    'App\\Shared\\Messaging\\': 'App\\Shared\\Infrastructure\\Messaging\\Outbox\\',
}

for root_name in ('app', 'routes', 'bootstrap', 'config', 'database', 'tests'):
    root = repo / root_name
    if not root.exists():
        continue
    for path in root.rglob('*.php'):
        text = path.read_text(encoding='utf-8')
        rewritten = text
        for old, new in sorted(replacements.items(), key=lambda item: len(item[0]), reverse=True):
            rewritten = rewritten.replace(old, new)
        for old, new in prefix_replacements.items():
            rewritten = rewritten.replace(old, new)
        if rewritten != text:
            path.write_text(rewritten, encoding='utf-8')

# Test ownership follows the bounded context as well; do not retain a top-level
# Integrations test domain after production ownership moves into Platform.
for suite in ('Feature', 'Unit'):
    old_tests = repo / f'tests/{suite}/Integrations'
    if not old_tests.exists():
        continue
    new_tests = repo / f'tests/{suite}/Platform/Integrations'
    if new_tests.exists():
        raise RuntimeError(f'Destination test tree already exists: {new_tests}')
    new_tests.parent.mkdir(parents=True, exist_ok=True)
    shutil.move(str(old_tests), str(new_tests))
    for path in new_tests.rglob('*.php'):
        text = path.read_text(encoding='utf-8')
        text = text.replace(
            f'namespace Tests\\{suite}\\Integrations;',
            f'namespace Tests\\{suite}\\Platform\\Integrations;',
        )
        path.write_text(text, encoding='utf-8')

# Encode P7 ownership permanently. This contract is intentionally filesystem +
# dependency oriented: policy behavior remains covered by existing feature tests.
architecture_test = repo / 'tests/Architecture/ArchitectureV2PlatformTest.php'
architecture_test.write_text(r'''<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ArchitectureV2PlatformTest extends TestCase
{
    public function test_p7_v1_runtime_roots_are_deleted(): void
    {
        foreach (['Notifications', 'Integrations', 'Platform'] as $legacy) {
            self::assertDirectoryDoesNotExist($this->root().'/app/Domain/'.$legacy);
        }

        self::assertDirectoryExists($this->root().'/app/Contexts/Communications/Reminders');
        self::assertDirectoryExists($this->root().'/app/Contexts/Platform/Integrations');
        self::assertDirectoryExists($this->root().'/app/Shared/Infrastructure/AuditTrail');
        self::assertDirectoryExists($this->root().'/app/Shared/Infrastructure/Messaging/Outbox');
        self::assertDirectoryDoesNotExist($this->root().'/app/Shared/Audit');
        self::assertDirectoryDoesNotExist($this->root().'/app/Shared/Messaging');
    }

    public function test_contribution_report_scheduling_is_intelligence_owned(): void
    {
        self::assertFileExists(
            $this->root().'/app/Contexts/Intelligence/Contributions/Actions/QueueDueContributionReports.php',
        );
        self::assertFileDoesNotExist(
            $this->root().'/app/Contexts/Communications/Reminders/Actions/QueueDueContributionReports.php',
        );
    }

    public function test_production_runtime_has_no_p7_legacy_namespace_references(): void
    {
        $forbidden = [
            'App\\Domain\\Notifications\\',
            'App\\Domain\\Integrations\\',
            'App\\Domain\\Platform\\',
            'App\\Shared\\Audit\\',
            'App\\Shared\\Messaging\\',
        ];

        foreach (['app', 'routes', 'bootstrap', 'config', 'database'] as $root) {
            foreach ($this->phpFiles($this->root().'/'.$root) as $file) {
                $source = (string) file_get_contents($file);
                foreach ($forbidden as $namespace) {
                    self::assertStringNotContainsString(
                        $namespace,
                        $source,
                        $file.' still references legacy P7 ownership '.$namespace,
                    );
                }
            }
        }
    }

    public function test_shared_infrastructure_does_not_import_business_owners(): void
    {
        foreach ($this->phpFiles($this->root().'/app/Shared/Infrastructure') as $file) {
            $source = (string) file_get_contents($file);
            foreach (['App\\Contexts\\', 'App\\Domain\\', 'App\\Workflows\\', 'App\\ReadModels\\'] as $forbidden) {
                self::assertStringNotContainsString(
                    $forbidden,
                    $source,
                    $file.' makes Shared infrastructure depend on a business owner.',
                );
            }
        }
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
''', encoding='utf-8')

# Clarify the shared-kernel physical boundary for future phases.
shared_readme = repo / 'app/Shared/README.md'
shared_readme.write_text(
    "# V2 shared kernel\n\n"
    "Only genuinely cross-cutting technical contracts and infrastructure belong here. "
    "Infrastructure packages live below `App\\Shared\\Infrastructure` (for example "
    "AuditTrail and Messaging/Outbox); access primitives and other tiny shared contracts may "
    "remain directly under Shared when they are not business capabilities.\n\n"
    "`Shared` must not import any business context, workflow, read model, or `App\\Domain\\*` "
    "class. No feature aggregate or gameplay/alliance policy belongs here.\n",
    encoding='utf-8',
)

# Hard-cut acceptance: no deleted root or namespace may survive in runtime/tests.
for legacy in ('Notifications', 'Integrations', 'Platform'):
    if (repo / f'app/Domain/{legacy}').exists():
        raise RuntimeError(f'P7 hard cut left app/Domain/{legacy}')
for legacy in ('Audit', 'Messaging'):
    if (repo / f'app/Shared/{legacy}').exists():
        raise RuntimeError(f'P7 hard cut left app/Shared/{legacy}')

for root_name in ('app', 'routes', 'bootstrap', 'config', 'database', 'tests'):
    root = repo / root_name
    if not root.exists():
        continue
    for path in root.rglob('*.php'):
        source = path.read_text(encoding='utf-8')
        for stale in (
            'App\\Domain\\Notifications\\',
            'App\\Domain\\Integrations\\',
            'App\\Domain\\Platform\\',
            'App\\Shared\\Audit\\',
            'App\\Shared\\Messaging\\',
        ):
            if stale in source:
                raise RuntimeError(f'{path}: stale P7 namespace {stale}')

print(f'P7 cut staged with {len(replacements)} class ownership rewrites.')
