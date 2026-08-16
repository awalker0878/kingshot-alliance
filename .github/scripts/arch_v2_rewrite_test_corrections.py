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


def kingdom_lines(indent: str, var: str, number: str, status: str | None) -> list[str]:
    lines = [f"{indent}{var} = app(ResolveKingdom::class)->handle({number.strip()});"]
    if status not in (None, "'active'", 'KingdomStatus::Active'):
        lines += [
            f"{indent}{var}->forceFill(['status' => {status.strip()}])->save();",
            f"{indent}{var} = {var}->refresh();",
        ]
    return lines


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
        extra = set(values) - {'number', 'status'}
        if extra:
            return match.group(0)
        i = match['i']
        var = match['v'] or '$kingdom'
        lines = kingdom_lines(i, var, values['number'], values.get('status'))
        if match['ret']:
            lines.append(f'{i}return {var};')
        return '\n'.join(lines)

    updated = assignment.sub(replace, text)

    compact = re.compile(
        r"(?P<i>^[ \t]*)(?:(?P<v>\$\w+)\s*=\s*|(?P<ret>return)\s+)"
        r"Kingdom::query\(\)->create\(\[\s*'number'\s*=>\s*(?P<n>[^\n]+?)"
        r"(?:,\s*'status'\s*=>\s*(?P<s>[^\n\]]+?))?,?\s*\]\);",
        re.M,
    )

    def replace_compact(match: re.Match[str]) -> str:
        i = match['i']
        var = match['v'] or '$kingdom'
        lines = kingdom_lines(i, var, match['n'], match['s'])
        if match['ret']:
            lines.append(f'{i}return {var};')
        return '\n'.join(lines)

    updated = compact.sub(replace_compact, updated)

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


def player_lines(indent: str, var: str, values: dict[str, str]) -> list[str] | None:
    required = {'current_kingdom_id', 'current_name'}
    if not required.issubset(values):
        return None
    owner = owner_from_id(values.get('user_id', 'null'))
    if owner == '':
        return None
    lines = [
        f"{indent}{var} = app(PersistPlayerIdentity::class)->handle(",
        f"{indent}    (string) {values['current_kingdom_id']},",
        f"{indent}    {values['current_name']},",
        f"{indent}    {values.get('game_player_id', 'null')},",
        f"{indent});",
    ]
    if owner is not None:
        lines.append(f'{indent}{var} = app(ClaimPlayerAccount::class)->handle({var}, {owner});')
    extras = {
        key: value
        for key, value in values.items()
        if key not in {'user_id', 'current_kingdom_id', 'current_name', 'game_player_id'}
    }
    if extras:
        lines.append(f'{indent}{var}->forceFill([')
        for key, value in extras.items():
            lines.append(f"{indent}    '{key}' => {value},")
        lines += [f'{indent}])->save();', f'{indent}{var} = {var}->refresh();']
    return lines


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
        if values is None:
            return match.group(0)
        i = match['i']
        var = match['v'] or '$player'
        lines = player_lines(i, var, values)
        if lines is None:
            return match.group(0)
        if match['ret']:
            lines.append(f'{i}return {var};')
        changed = True
        return '\n'.join(lines)

    updated = pattern.sub(replace, text)

    standalone = re.compile(
        r"(?P<i>^[ \t]*)Player::query\(\)->create\(\[\s*\n(?P<body>.*?)^(?P=i)\]\);",
        re.M | re.S,
    )

    def replace_standalone(match: re.Match[str]) -> str:
        nonlocal changed
        values = parse_array(match['body'])
        if values is None:
            return match.group(0)
        lines = player_lines(match['i'], '$fixturePlayer', values)
        if lines is None:
            return match.group(0)
        changed = True
        return '\n'.join(lines)

    updated = standalone.sub(replace_standalone, updated)
    if changed:
        updated = ensure_use(updated, r'App\Contexts\GameWorld\Actions\PersistPlayerIdentity')
        if 'ClaimPlayerAccount::class' in updated:
            updated = ensure_use(updated, r'App\Contexts\GameWorld\Actions\ClaimPlayerAccount')
    return updated


def rewrite_transfer_performance_player(text: str) -> str:
    old = """            $player = Player::query()->create([
                'current_kingdom_id' => $direction === TransferDirection::Incoming ? $source->id : $alliance->kingdom_id,
                'game_player_id' => $direction === TransferDirection::Incoming ? 'incoming-performance-'.$index : 'transfer-performance-'.$index,
                'current_name' => 'Transfer Player '.$index,
            ]);"""
    if old not in text:
        return text
    new = """            $player = app(PersistPlayerIdentity::class)->handle(
                (string) ($direction === TransferDirection::Incoming ? $source->id : $alliance->kingdom_id),
                'Transfer Player '.$index,
                $direction === TransferDirection::Incoming ? 'incoming-performance-'.$index : 'transfer-performance-'.$index,
            );"""
    text = text.replace(old, new)
    return ensure_use(text, r'App\Contexts\GameWorld\Actions\PersistPlayerIdentity')


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
            text = rewrite_transfer_performance_player(text)
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
