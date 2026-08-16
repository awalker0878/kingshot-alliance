from __future__ import annotations

import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
TESTS = ROOT / 'tests'

DOMAIN_REPLACEMENTS = {
    r'App\Domain\Kingdoms\Models\TrackedKingdomAlliance': r'App\Workflows\KingdomTransfer\Models\TrackedKingdomAlliance',
    r'App\Domain\Kingdoms\Actions\AcceptKingdomIntelligenceShareInvitation': r'App\Contexts\Intelligence\Sharing\Actions\AcceptKingdomIntelligenceShareInvitation',
    r'App\Domain\Kingdoms\Actions\CreateKingdomIntelligenceShareInvitation': r'App\Contexts\Intelligence\Sharing\Actions\CreateKingdomIntelligenceShareInvitation',
}


def ensure_use(text: str, fqcn: str) -> str:
    line = f'use {fqcn};'
    if line in text:
        return text
    match = re.search(r'^namespace\s+[^;]+;', text, re.M)
    if match is None:
        raise SystemExit(f'No namespace while adding {fqcn}')
    return text[:match.end()] + '\n\n' + line + text[match.end():]


def parse_array(body: str) -> dict[str, str] | None:
    values: dict[str, str] = {}
    significant = [line for line in body.splitlines() if line.strip()]
    for line in significant:
        match = re.match(r"\s*'(\w+)'\s*=>\s*(.+),\s*$", line)
        if match is None:
            return None
        values[match.group(1)] = match.group(2).strip()
    return values


def owner_from_id(value: str) -> str | None:
    value = value.strip()
    if value == 'null':
        return None
    if value.endswith('->id'):
        return value[:-4]
    return ''


def rewrite_kingdom_creates(text: str) -> str:
    assignment = re.compile(
        r"(?P<i>^[ \t]*)(?:(?P<v>\$\w+)\s*=\s*|(?P<ret>return)\s+)Kingdom::query\(\)->create\(\[\s*\n"
        r"(?P<body>.*?)^(?P=i)\]\);",
        re.M | re.S,
    )

    def replace(match: re.Match[str]) -> str:
        values = parse_array(match['body'])
        if values is None or 'number' not in values:
            return match.group(0)
        i = match['i']
        var = match['v'] or '$kingdom'
        status = values.get('status')
        extra = set(values) - {'number', 'status'}
        if extra:
            return match.group(0)
        lines = [f"{i}{var} = app(ResolveKingdom::class)->handle({values['number']});"]
        if status not in (None, "'active'", 'KingdomStatus::Active'):
            lines += [
                f"{i}{var}->forceFill(['status' => {status}])->save();",
                f"{i}{var} = {var}->refresh();",
            ]
        if match['ret']:
            lines.append(f'{i}return {var};')
        return '\n'.join(lines)

    updated = assignment.sub(replace, text)

    first_or_create = re.compile(
        r"(?P<i>^[ \t]*)(?P<v>\$\w+)\s*=\s*Kingdom::query\(\)->firstOrCreate\(\s*\n"
        r"(?P=i)\s*\['number'\s*=>\s*(?P<n>[^\]]+)\],\s*\n"
        r"(?P=i)\s*\['status'\s*=>\s*(?:'active'|KingdomStatus::Active)\],\s*\n"
        r"(?P=i)\);",
        re.M,
    )
    updated = first_or_create.sub(
        lambda m: f"{m['i']}{m['v']} = app(ResolveKingdom::class)->handle({m['n'].strip()});",
        updated,
    )
    if updated != text and 'ResolveKingdom::class' in updated:
        updated = ensure_use(updated, r'App\Contexts\GameWorld\Actions\ResolveKingdom')
    return updated


def rewrite_player_creates(text: str) -> str:
    pattern = re.compile(
        r"(?P<i>^[ \t]*)(?:(?P<v>\$\w+)\s*=\s*|(?P<ret>return)\s+)Player::query\(\)->create\(\[\s*\n"
        r"(?P<body>.*?)^(?P=i)\]\);",
        re.M | re.S,
    )

    changed = False

    def replace(match: re.Match[str]) -> str:
        nonlocal changed
        values = parse_array(match['body'])
        required = {'current_kingdom_id', 'current_name'}
        if values is None or not required.issubset(values):
            return match.group(0)
        owner = owner_from_id(values.get('user_id', 'null'))
        if owner == '':
            return match.group(0)
        i = match['i']
        var = match['v'] or '$player'
        game_id = values.get('game_player_id', 'null')
        lines = [
            f"{i}{var} = app(PersistPlayerIdentity::class)->handle(",
            f"{i}    (string) {values['current_kingdom_id']},",
            f"{i}    {values['current_name']},",
            f"{i}    {game_id},",
            f"{i});",
        ]
        if owner is not None:
            lines.append(f'{i}{var} = app(ClaimPlayerAccount::class)->handle({var}, {owner});')

        extras = {
            key: value
            for key, value in values.items()
            if key not in {'user_id', 'current_kingdom_id', 'current_name', 'game_player_id'}
        }
        if extras:
            lines.append(f'{i}{var}->forceFill([')
            for key, value in extras.items():
                lines.append(f"{i}    '{key}' => {value},")
            lines += [f'{i}])->save();', f'{i}{var} = {var}->refresh();']
        if match['ret']:
            lines.append(f'{i}return {var};')
        changed = True
        return '\n'.join(lines)

    updated = pattern.sub(replace, text)
    if changed:
        updated = ensure_use(updated, r'App\Contexts\GameWorld\Actions\PersistPlayerIdentity')
        if 'ClaimPlayerAccount::class' in updated:
            updated = ensure_use(updated, r'App\Contexts\GameWorld\Actions\ClaimPlayerAccount')
    return updated


def rewrite_known_helper_shapes(text: str) -> str:
    text = re.sub(
        r"return\s+Kingdom::query\(\)->create\(\[\s*'number'\s*=>\s*\$number,\s*'status'\s*=>\s*'active',?\s*\]\);",
        "return app(ResolveKingdom::class)->handle($number);",
        text,
    )
    if 'ResolveKingdom::class' in text:
        text = ensure_use(text, r'App\Contexts\GameWorld\Actions\ResolveKingdom')
    return text


def rewrite_domain_namespaces(text: str) -> str:
    for old, new in DOMAIN_REPLACEMENTS.items():
        text = text.replace(old, new)
    for layer in ('Enums', 'Models', 'Queries'):
        text = text.replace(
            f'App\\Domain\\Kingdoms\\{layer}\\Transfer',
            f'App\\Workflows\\KingdomTransfer\\{layer}\\Transfer',
        )
    return text


def update_visual_entrypoint() -> None:
    package = ROOT / 'package.json'
    data = json.loads(package.read_text())
    scripts = data.get('scripts', {})
    command = scripts.get('test:visual')
    if isinstance(command, str):
        scripts['test:visual'] = command.replace(
            'tests/Visual/ux-p9.spec.ts',
            'tests/Visual/v2/ux-p9V2.spec.ts',
        )
        package.write_text(json.dumps(data, indent=4, ensure_ascii=False) + '\n')


def rewrite_files() -> None:
    for path in sorted(TESTS.rglob('*.php')):
        if not path.is_file() or 'Support' in path.parts or path.name == 'TestCase.php':
            continue
        text = rewrite_domain_namespaces(path.read_text())
        if 'Architecture' not in path.parts:
            text = rewrite_kingdom_creates(text)
            text = rewrite_known_helper_shapes(text)
            text = rewrite_player_creates(text)
        path.write_text(text)


def verify() -> None:
    failures: list[str] = []
    for path in sorted(TESTS.rglob('*Test.php')):
        rel = path.relative_to(TESTS)
        if rel == Path('TestCase.php') or 'Support' in rel.parts or 'Concerns' in rel.parts:
            continue
        if 'v2' not in rel.parts:
            failures.append(f'PHP test outside v2: {rel}')
        if not path.name.endswith('V2Test.php'):
            failures.append(f'PHP test missing V2 suffix: {rel}')
        text = path.read_text()
        if 'App\\Domain\\' in text:
            failures.append(f'V1 namespace remains: {rel}')
        if 'Architecture' not in rel.parts:
            if 'Player::query()->create(' in text:
                failures.append(f'Direct Player fixture remains: {rel}')
            if 'Kingdom::query()->create(' in text or 'Kingdom::query()->firstOrCreate(' in text:
                failures.append(f'Direct Kingdom fixture remains: {rel}')
    if (TESTS / 'RewriteInput').exists():
        failures.append('tests/RewriteInput still exists')
    if failures:
        raise SystemExit('\n'.join(failures))


rewrite_files()
update_visual_entrypoint()
verify()
print('ARCH-V2-TESTS: residual identity fixtures and stale imports corrected.')
