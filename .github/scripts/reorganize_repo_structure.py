from __future__ import annotations

from collections import defaultdict
from pathlib import Path
import os
import re
import shutil

root = Path('.')


def move_file(old: str, new: str) -> None:
    source = Path(old)
    target = Path(new)
    if not source.exists():
        return
    target.parent.mkdir(parents=True, exist_ok=True)
    if target.exists():
        raise RuntimeError(f'Target already exists: {target}')
    shutil.move(str(source), str(target))


# Documentation: the implementation plan defines these five top-level groups.
if Path('docs/architecture').exists():
    if Path('docs/adr').exists():
        raise RuntimeError('docs/adr already exists before architecture migration')
    shutil.move('docs/architecture', 'docs/adr')

if Path('docs/runbooks').exists():
    Path('docs/operations').mkdir(parents=True, exist_ok=True)
    if Path('docs/operations/runbooks').exists():
        raise RuntimeError('docs/operations/runbooks already exists')
    shutil.move('docs/runbooks', 'docs/operations/runbooks')

root_doc_groups = {
    'domains': [
        'CONTENT_MANAGEMENT.md',
        'EVENTS_AND_RALLIES.md',
        'RECRUITMENT.md',
    ],
    'operations': [
        'BRANCH_PROTECTION.md',
        'RELEASE_CHECKLIST.md',
        'PHASE_1_MIGRATION_ROLLBACK.md',
        'PHASE_2_MIGRATION_ROLLBACK.md',
        'PHASE_2_OPERATIONS.md',
        'PHASE_3_OPERATIONS.md',
        'PHASE_4_MIGRATION_ROLLBACK.md',
        'PHASE_4_OPERATIONS.md',
    ],
    'product': [
        'DEFINITION_OF_DONE.md',
        'IMPLEMENTATION_PLAN.md',
        'PHASES_1_4_ALIGNMENT_AUDIT.md',
        'PHASE_0_EXIT_REPORT.md',
        'PHASE_1_ACCESSIBILITY_REVIEW.md',
        'PHASE_1_EXIT_REPORT.md',
        'PHASE_2_ACCESSIBILITY.md',
        'PHASE_2_EXIT_REPORT.md',
        'PHASE_3_ACCESSIBILITY.md',
        'PHASE_3_EXIT_REPORT.md',
        'PHASE_3_SCOPE.md',
        'PHASE_4_ACCESSIBILITY.md',
        'PHASE_4_EXIT_REPORT.md',
    ],
    'security': [
        'SECURITY_BASELINE.md',
        'PHASE_1_THREAT_MODEL.md',
        'PHASE_2_THREAT_MODEL.md',
        'PHASE_3_THREAT_MODEL.md',
        'PHASE_4_THREAT_MODEL.md',
    ],
}

for group, names in root_doc_groups.items():
    Path(f'docs/{group}').mkdir(parents=True, exist_ok=True)
    for name in names:
        move_file(f'docs/{name}', f'docs/{group}/{name}')

for required in ['adr', 'domains', 'operations', 'product', 'security']:
    Path(f'docs/{required}').mkdir(parents=True, exist_ok=True)

# Remove any obsolete empty documentation directories.
for legacy in ['docs/architecture', 'docs/runbooks']:
    path = Path(legacy)
    if path.exists() and not any(path.iterdir()):
        path.rmdir()

# Resolve the old layer-first source-tree guidance explicitly.
adr_readme = Path('docs/adr/README.md')
adr_readme.write_text('''# Architecture decision records

Kingshot Alliance is an enterprise modular monolith organized by explicit business domains. The canonical physical repository structure is defined by the implementation plan and ADR 0008.

## Decision records

- [ADR 0001 — Modular monolith](0001-modular-monolith.md)
- [ADR 0002 — Alliance-level tenancy](0002-alliance-level-tenancy.md)
- [ADR 0003 — First-party authentication](0003-first-party-authentication.md)
- [ADR 0004 — Queues and transactional outbox](0004-queues-and-transactional-outbox.md)
- [ADR 0005 — S3-compatible object storage](0005-s3-compatible-object-storage.md)
- [ADR 0006 — Observability and correlation](0006-observability-and-correlation.md)
- [ADR 0007 — Testing toolchain compatibility](0007-testing-toolchain-compatibility.md)
- [ADR 0008 — Domain-first source layout](0008-domain-first-source-layout.md)

Use [the ADR template](adr-template.md) for new material decisions.

## Canonical source structure

```text
app/
  Domain/
    Alliances/
    Audit/
    Authorization/
    Content/
    Contributions/
    Events/
    Identity/
    Integrations/
    Kingdoms/
    Memberships/
    Notifications/
    Platform/
    Rallies/
    Recruitment/
docs/
  adr/
  domains/
  operations/
  product/
  security/
resources/js/
tests/
  Architecture/
  Feature/
  Integration/
  Performance/
  TenantIsolation/
  Unit/
```

Runtime PHP is owned by a canonical `app/Domain/<Domain>` module. Internal organization such as `Actions`, `Queries`, `Services`, `Models`, `Http`, `Enums`, and `ValueObjects` lives inside the owning domain rather than in parallel top-level application layers.

Domains should communicate through intentional public actions, queries, services, value objects, or events. A cross-domain dependency must be part of the other domain's supported contract rather than an accidental dependency on its persistence internals.
''')

Path('docs/adr/0008-domain-first-source-layout.md').write_text('''# ADR 0008 — Domain-first source layout

- **Status:** Accepted
- **Date:** 2026-08-07
- **Related phases:** Phase 0 foundation; Phase 1–4 integration alignment

## Context

The implementation plan defines a domain-first repository layout with all runtime application code owned beneath `app/Domain/<Domain>`. During Phases 0–4, Laravel's default `app/Models` folder and parallel `app/Application`, `app/Http`, `app/Infrastructure`, and `app/Providers` trees accumulated beside a smaller `app/Domain` tree. That physical layout obscured ownership and allowed business capabilities such as alliances, memberships, authorization, reminders, and rallies to drift into broader buckets.

An older architecture README also showed the parallel layer-first folders. That example conflicted with the implementation plan's canonical repository structure.

## Decision

The implementation plan's domain-first tree is authoritative.

All runtime PHP under `app/` is owned beneath one of the canonical domains. Framework adapters and implementation details remain explicit, but they are nested inside the domain that owns them, for example `Domain/Content/Actions`, `Domain/Events/Http`, or `Domain/Platform/Providers`.

The canonical domains are Alliances, Audit, Authorization, Content, Contributions, Events, Identity, Integrations, Kingdoms, Memberships, Notifications, Platform, Rallies, and Recruitment. A future-phase domain may exist as documentation only, but must not contain runtime PHP before its approved phase.

Documentation and tests follow the corresponding canonical groups from the implementation plan. No compatibility aliases or duplicate legacy source trees are retained because the application is not yet in production.

## Consequences

Ownership is visible from the filesystem and namespace. Domain reviews can identify cross-domain dependencies directly. Laravel conventions that infer classes from namespaces, such as model factories, must be configured explicitly when the domain-first location changes the framework default.

Cross-domain calls are not prohibited, but they must use intentional supported contracts. Persistence-model imports across domain boundaries are treated as a review signal and should be removed when they expose another domain's private implementation.

## Validation

Architecture tests verify the canonical domain directories, the absence of the superseded top-level application layers, documentation/test group presence, and the absence of runtime PHP in future-phase domains. CI, PHPStan, PHPUnit, CodeQL, dependency review, staging, recovery, and image scanning remain required before merge.
''')

# Tests: populate the canonical groups with tests that actually belong there.
def move_test(old: Path, new: Path, old_namespace: str, new_namespace: str) -> None:
    if not old.exists():
        return
    new.parent.mkdir(parents=True, exist_ok=True)
    text = old.read_text()
    text = text.replace(f'namespace {old_namespace};', f'namespace {new_namespace};', 1)
    new.write_text(text)
    old.unlink()

phase_boundary = Path('tests/Feature/PhaseBoundaryTest.php')
move_test(phase_boundary, Path('tests/Architecture/PhaseBoundaryTest.php'), 'Tests\\Feature', 'Tests\\Architecture')

# Tenant-isolation tests are security boundary tests, not generic features.
for old in sorted(Path('tests/Feature').rglob('*IsolationTest.php')):
    relative = old.relative_to('tests/Feature')
    new = Path('tests/TenantIsolation') / relative
    suffix = relative.parent.as_posix().replace('/', '\\')
    old_ns = 'Tests\\Feature' + (f'\\{suffix}' if suffix != '.' else '')
    new_ns = 'Tests\\TenantIsolation' + (f'\\{suffix}' if suffix != '.' else '')
    move_test(old, new, old_ns, new_ns)

# Database rollback and outbox publication exercise multiple layers and belong to integration tests.
for old in sorted(Path('tests/Feature').rglob('*MigrationRollbackTest.php')):
    relative = old.relative_to('tests/Feature')
    new = Path('tests/Integration') / relative
    suffix = relative.parent.as_posix().replace('/', '\\')
    old_ns = 'Tests\\Feature' + (f'\\{suffix}' if suffix != '.' else '')
    new_ns = 'Tests\\Integration' + (f'\\{suffix}' if suffix != '.' else '')
    move_test(old, new, old_ns, new_ns)

outbox = Path('tests/Feature/Shared/OutboxPublisherTest.php')
move_test(
    outbox,
    Path('tests/Integration/Platform/OutboxPublisherTest.php'),
    'Tests\\Feature\\Shared',
    'Tests\\Integration\\Platform',
)

for required in ['Architecture', 'Feature', 'Integration', 'Performance', 'TenantIsolation', 'Unit']:
    Path(f'tests/{required}').mkdir(parents=True, exist_ok=True)

# Replace placeholder test-group README text with purposeful group descriptions.
Path('tests/Integration/README.md').write_text('''# Integration tests

Integration tests verify behavior spanning multiple domain/application boundaries or infrastructure concerns, including migration rollback and transactional outbox publication.
''')
Path('tests/Performance/README.md').write_text('''# Performance tests

Performance and capacity tests live here when a phase defines a measurable performance acceptance criterion. No synthetic threshold is introduced for Phases 0–4 where the accepted phase evidence did not define one.
''')
Path('tests/TenantIsolation/README.md').write_text('''# Tenant-isolation tests

Adversarial multi-alliance isolation tests live here. They verify that authorization, route resolution, queries, and persistence fail closed when records or memberships belong to another alliance.
''')

Path('tests/Architecture/RepositoryStructureTest.php').write_text(r'''<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class RepositoryStructureTest extends TestCase
{
    public function test_documentation_uses_the_implementation_plan_groups(): void
    {
        foreach (['adr', 'domains', 'operations', 'product', 'security'] as $directory) {
            self::assertDirectoryExists($this->root().'/docs/'.$directory);
        }

        self::assertDirectoryDoesNotExist($this->root().'/docs/architecture');
        self::assertDirectoryDoesNotExist($this->root().'/docs/runbooks');
    }

    public function test_test_suite_uses_the_implementation_plan_groups(): void
    {
        foreach (['Architecture', 'Feature', 'Integration', 'Performance', 'TenantIsolation', 'Unit'] as $directory) {
            self::assertDirectoryExists($this->root().'/tests/'.$directory);
        }
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
''')

# Update stable repository-path references everywhere.
path_replacements = {
    'docs/architecture/': 'docs/adr/',
    'docs/runbooks/': 'docs/operations/runbooks/',
}
for group, names in root_doc_groups.items():
    for name in names:
        path_replacements[f'docs/{name}'] = f'docs/{group}/{name}'

text_suffixes = {'.md', '.php', '.json', '.xml', '.neon', '.yml', '.yaml', '.txt'}
for path in root.rglob('*'):
    if not path.is_file() or path.suffix not in text_suffixes or '.git' in path.parts or 'vendor' in path.parts or 'node_modules' in path.parts:
        continue
    text = path.read_text(errors='replace')
    changed = text
    for old, new in path_replacements.items():
        changed = changed.replace(old, new)
    if changed != text:
        path.write_text(changed)

# Repair relative Markdown links between moved docs by unique filename when possible.
by_name: dict[str, list[Path]] = defaultdict(list)
for target in Path('docs').rglob('*'):
    if target.is_file():
        by_name[target.name].append(target)

link_pattern = re.compile(r'(?P<prefix>\]\()(?P<target>[^)\s]+)(?P<suffix>\))')
for path in Path('docs').rglob('*.md'):
    text = path.read_text()

    def repair(match: re.Match[str]) -> str:
        target = match.group('target')
        if target.startswith(('http://', 'https://', 'mailto:', '#')):
            return match.group(0)
        base, hashmark, anchor = target.partition('#')
        if base == '' or not base.lower().endswith('.md'):
            return match.group(0)
        resolved = (path.parent / base).resolve()
        if resolved.exists():
            return match.group(0)
        candidates = by_name.get(Path(base).name, [])
        if len(candidates) != 1:
            return match.group(0)
        relative = os.path.relpath(candidates[0], path.parent).replace(os.sep, '/')
        repaired = relative + (f'#{anchor}' if hashmark else '')
        return match.group('prefix') + repaired + match.group('suffix')

    repaired = link_pattern.sub(repair, text)
    if repaired != text:
        path.write_text(repaired)

# Fail if root documentation markdown or obsolete doc groups remain.
root_markdown = sorted(p.name for p in Path('docs').glob('*.md'))
if root_markdown:
    raise RuntimeError(f'Unclassified root documentation remains: {root_markdown}')

current_doc_dirs = sorted(p.name for p in Path('docs').iterdir() if p.is_dir())
expected_doc_dirs = ['adr', 'domains', 'operations', 'product', 'security']
if current_doc_dirs != expected_doc_dirs:
    raise RuntimeError(f'Documentation groups differ from canonical tree: {current_doc_dirs}')

current_test_dirs = sorted(p.name for p in Path('tests').iterdir() if p.is_dir())
expected_test_dirs = ['Architecture', 'Feature', 'Integration', 'Performance', 'TenantIsolation', 'Unit']
if current_test_dirs != expected_test_dirs:
    raise RuntimeError(f'Test groups differ from canonical tree: {current_test_dirs}')
