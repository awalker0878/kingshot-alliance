from pathlib import Path

EXECUTABLE_TEST_ROOTS = (
    Path('tests/Feature'),
    Path('tests/Integration'),
    Path('tests/Unit'),
    Path('tests/TenantIsolation'),
)

old = 'App\\Domain\\Contributions\\'
new = 'App\\Contexts\\Intelligence\\Contributions\\'
changed: list[str] = []

for root in EXECUTABLE_TEST_ROOTS:
    if not root.exists():
        continue
    for path in sorted(root.rglob('*.php')):
        source = path.read_text(encoding='utf-8')
        if old not in source:
            continue
        path.write_text(source.replace(old, new), encoding='utf-8')
        changed.append(str(path))

if not changed:
    raise RuntimeError('P8A expected at least one stale executable Contributions fixture to migrate.')

# Kingdoms is deliberately handled by the main P8A ownership cut. Any other V1
# namespace in an executable test is an earlier-phase fixture defect and must be
# surfaced together rather than weakened out of the final guard.
residual: list[str] = []
for root in EXECUTABLE_TEST_ROOTS:
    if not root.exists():
        continue
    for path in sorted(root.rglob('*.php')):
        source = path.read_text(encoding='utf-8')
        for line_number, line in enumerate(source.splitlines(), start=1):
            if 'App\\Domain\\' in line and 'App\\Domain\\Kingdoms\\' not in line:
                residual.append(f'{path}:{line_number}: {line.strip()}')

if residual:
    raise RuntimeError(
        'P8A found stale pre-P8 V1 namespaces in executable tests:\n' + '\n'.join(residual)
    )

print(f'Migrated {len(changed)} executable Contributions fixture(s) to Intelligence ownership.')
