from pathlib import Path


def replace(path: str, old: str, new: str, *, count: int | None = None) -> None:
    file = Path(path)
    text = file.read_text()
    matches = text.count(old)
    if matches == 0:
        raise SystemExit(f'Missing expected pattern in {path}: {old[:100]!r}')
    if count is not None and matches != count:
        raise SystemExit(f'Expected {count} matches in {path}, found {matches}: {old[:100]!r}')
    file.write_text(text.replace(old, new))


# Defaulted Vue props are still represented as possibly undefined in generated template types.
replace(
    'resources/js/components/ui/AppButton.vue',
    ':class="classes[variant]"',
    ':class="classes[variant ?? \'primary\']"',
    count=1,
)

# exactOptionalPropertyTypes: normalize the optional page prop before passing it to IdentitySwitcher.
replace(
    'resources/js/layouts/AppLayout.vue',
    ':alliance-name="playerAllianceName"',
    ':alliance-name="playerAllianceName ?? null"',
    count=4,
)

# locales is a non-empty application constant; make that invariant explicit to noUncheckedIndexedAccess.
replace(
    'resources/js/localization/locales.ts',
    'return locales.find((locale) => locale.code === code) ?? locales[0];',
    'return locales.find((locale) => locale.code === code) ?? locales[0]!;',
    count=1,
)

# These reactive maps are constructed from the exact rows subsequently rendered by the template.
replace(
    'resources/js/pages/Alliance/Members/Manage.vue',
    'drafts[entry.id].',
    'drafts[entry.id]!.',
    count=5,
)
replace(
    'resources/js/pages/Alliance/Noticeboard/Manage.vue',
    'edits[item.id].',
    'edits[item.id]!.',
    count=9,
)
replace(
    'resources/js/pages/Operations/Events/Show.vue',
    'assignmentResponses[assignment.id].',
    'assignmentResponses[assignment.id]!.',
    count=2,
)

# split() can technically produce an undefined tuple member under noUncheckedIndexedAccess.
replace(
    'resources/js/pages/Intelligence/Roster/Dossiers.vue',
    "const [whole, fraction] = unsigned.split('.');",
    "const [whole = '', fraction] = unsigned.split('.');",
    count=1,
)
replace(
    'resources/js/pages/Operations/Events/Manage.vue',
    "const [label, ...rest] = line.split('|');\n      const value = rest.join('|').trim() || label.trim();\n      return { label: label.trim(), value };",
    "const [rawLabel = '', ...rest] = line.split('|');\n      const label = rawLabel.trim();\n      const value = rest.join('|').trim() || label;\n      return { label, value };",
    count=1,
)

# Transfer drafts are initialized one-for-one from groups/participants before rendering.
replace(
    'resources/js/pages/Kingdom/Transfer/Manage.vue',
    'groupDrafts[group.id].',
    'groupDrafts[group.id]!.',
)
replace(
    'resources/js/pages/Kingdom/Transfer/Manage.vue',
    'drafts[participant.id].',
    'drafts[participant.id]!.',
)

# Blocker drafts are initialized one-for-one from the participants collection.
replace(
    'resources/js/pages/Kingdom/Transfer/Readiness.vue',
    'const draft = blockerDrafts[participant.id];',
    'const draft = blockerDrafts[participant.id]!;',
    count=1,
)
replace(
    'resources/js/pages/Kingdom/Transfer/Readiness.vue',
    'blockerDrafts[participant.id].',
    'blockerDrafts[participant.id]!.',
    count=3,
)
