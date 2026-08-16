from pathlib import Path
import re

repo = Path('.')
domain = repo / 'app/Domain/Kingdoms'
replacements: dict[str, str] = {}


def fqcn(path: Path, text: str) -> str:
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
    old_fqcn = fqcn(src, original)
    old_ns = old_fqcn.rsplit('\\', 1)[0]
    rel_parent = dst.parent.relative_to(repo / 'app')
    new_ns = 'App\\' + str(rel_parent).replace('/', '\\')
    rewritten = original.replace(f'namespace {old_ns};', f'namespace {new_ns};', 1)
    dst.parent.mkdir(parents=True, exist_ok=True)
    dst.write_text(rewritten, encoding='utf-8')
    src.unlink()
    replacements[old_fqcn] = new_ns + '\\' + dst.stem


# Neutral GameWorld resolution is foundation behavior. Player roster resolution is not:
# it reads Alliance membership/roster constraints and stays downstream in Intelligence.
move_php(
    domain / 'Actions/ResolveKingdom.php',
    repo / 'app/Contexts/GameWorld/Actions/ResolveKingdom.php',
)
move_php(
    domain / 'Actions/ResolvePlayer.php',
    repo / 'app/Contexts/Intelligence/Roster/Actions/ResolvePlayer.php',
)

ingestion_files: list[Path] = []
roster_files: list[Path] = []
for src in sorted(domain.rglob('*.php')):
    name = src.stem
    if 'KingdomIngestion' in name:
        ingestion_files.append(src)
        continue
    if ('Roster' in name or 'PlayerSnapshot' in name or name == 'PowerMath') and 'Transfer' not in name:
        roster_files.append(src)

if not ingestion_files:
    raise RuntimeError('No V1 KingdomIngestion files found for P6 cut.')
if not roster_files:
    raise RuntimeError('No V1 roster/player-snapshot files found for P6 cut.')

for src in ingestion_files:
    rel = src.relative_to(domain)
    move_php(src, repo / 'app/Contexts/Intelligence/Ingestion' / rel)

for src in roster_files:
    rel = src.relative_to(domain)
    move_php(src, repo / 'app/Contexts/Intelligence/Roster' / rel)

# Rewrite stale V1/P2 model references to their actual P6 owners.
model_owner: dict[str, str] = {}
for path in (repo / 'app/Contexts/Intelligence/Ingestion/Models').glob('*.php'):
    model_owner[path.stem] = f'App\\Contexts\\Intelligence\\Ingestion\\Models\\{path.stem}'
for path in (repo / 'app/Contexts/Intelligence/Roster/Models').glob('*.php'):
    model_owner[path.stem] = f'App\\Contexts\\Intelligence\\Roster\\Models\\{path.stem}'
model_owner['KingdomAllianceObservation'] = 'App\\Contexts\\Intelligence\\Observations\\Models\\KingdomAllianceObservation'
model_owner['TrackedKingdomAlliance'] = 'App\\Contexts\\Intelligence\\Observations\\Models\\TrackedKingdomAlliance'
for name, new in model_owner.items():
    replacements[f'App\\Contexts\\GameWorld\\Models\\{name}'] = new

roots = ['app', 'routes', 'bootstrap', 'config', 'database', 'tests']
for root_name in roots:
    root = repo / root_name
    if not root.exists():
        continue
    for path in root.rglob('*.php'):
        text = path.read_text(encoding='utf-8')
        rewritten = text
        for old, new in replacements.items():
            rewritten = rewritten.replace(old, new)
        if rewritten != text:
            path.write_text(rewritten, encoding='utf-8')

# Imports inherited from the old noun-domain namespace must now be explicit.
def add_use(path: Path, use_line: str) -> None:
    text = path.read_text(encoding='utf-8')
    if use_line in text:
        return
    marker = re.search(r'^(namespace [^;]+;\n)', text, re.M)
    if not marker:
        raise RuntimeError(f'No namespace marker in {path}')
    insert_at = marker.end()
    path.write_text(text[:insert_at] + f'\n{use_line}\n' + text[insert_at:], encoding='utf-8')

add_use(
    repo / 'app/Contexts/Intelligence/Ingestion/Actions/PromoteKingdomIngestionAllianceObservation.php',
    'use App\\Contexts\\Intelligence\\Observations\\Actions\\RecordKingdomAllianceObservation;',
)
add_use(
    repo / 'app/Contexts/Intelligence/Ingestion/Actions/PromoteKingdomIngestionPlayerSnapshot.php',
    'use App\\Contexts\\Intelligence\\Roster\\Actions\\RecordPlayerSnapshot;',
)
add_use(
    repo / 'app/Contexts/Intelligence/Roster/Models/PlayerSnapshot.php',
    'use App\\Contexts\\Alliance\\Membership\\Models\\AllianceRosterEntry;',
)

for capability in ('Ingestion', 'Roster'):
    models = repo / f'app/Contexts/Intelligence/{capability}/Models'
    if not models.exists():
        continue
    for path in models.glob('*.php'):
        text = path.read_text(encoding='utf-8')
        if 'Kingdom::class' in text and 'use App\\Contexts\\GameWorld\\Models\\Kingdom;' not in text:
            add_use(path, 'use App\\Contexts\\GameWorld\\Models\\Kingdom;')

# P4 semantic ownership repair: Intelligence permissions must be interpreted by
# Intelligence authorization, never by AllianceAuthorization.
for capability in ('Ingestion', 'Roster'):
    root = repo / f'app/Contexts/Intelligence/{capability}'
    for path in root.rglob('*.php'):
        text = path.read_text(encoding='utf-8')
        if (
            'use App\\Contexts\\Alliance\\Access\\Services\\AllianceAuthorization;' in text
            and 'IntelligencePermission' in text
            and 'AlliancePermission' not in text
        ):
            text = text.replace(
                'use App\\Contexts\\Alliance\\Access\\Services\\AllianceAuthorization;',
                'use App\\Contexts\\Intelligence\\Access\\Services\\AllianceIntelligenceAuthorization;',
            )
            text = text.replace('AllianceAuthorization $authorization', 'AllianceIntelligenceAuthorization $authorization')
            path.write_text(text, encoding='utf-8')

# Roster CSV has two different policies: member export is Alliance-owned; private
# management export and import administration are Intelligence-owned.
roster_csv = repo / 'app/Contexts/Intelligence/Roster/Http/Controllers/RosterCsvController.php'
text = roster_csv.read_text(encoding='utf-8')
if 'use App\\Contexts\\Intelligence\\Access\\Services\\AllianceIntelligenceAuthorization;' not in text:
    text = text.replace(
        'use App\\Contexts\\Intelligence\\Access\\Enums\\IntelligencePermission;',
        'use App\\Contexts\\Intelligence\\Access\\Enums\\IntelligencePermission;\nuse App\\Contexts\\Intelligence\\Access\\Services\\AllianceIntelligenceAuthorization;',
    )
text = text.replace(
    'AllianceAuthorization $authorization,\n    ): Response {',
    'AllianceIntelligenceAuthorization $authorization,\n    ): Response {',
    2,
)
text = text.replace(
    'AllianceAuthorization $authorization,\n        RosterCsvExporter $exporter,',
    'AllianceAuthorization $allianceAuthorization,\n        AllianceIntelligenceAuthorization $intelligenceAuthorization,\n        RosterCsvExporter $exporter,',
)
old = '''        $includePrivate = ($validated['scope'] ?? 'member') === 'management';
        $permission = $includePrivate ? IntelligencePermission::KingdomManage : AlliancePermission::View;

        if (! $authorization->allows($context->player(), $alliance, $permission)) {
            throw new AuthorizationException;
        }
'''
new = '''        $includePrivate = ($validated['scope'] ?? 'member') === 'management';
        $allowed = $includePrivate
            ? $intelligenceAuthorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage)
            : $allianceAuthorization->allows($context->player(), $alliance, AlliancePermission::View);

        if (! $allowed) {
            throw new AuthorizationException;
        }
'''
if old not in text:
    raise RuntimeError('RosterCsvController export authorization block changed unexpectedly.')
text = text.replace(old, new)
text = text.replace(
    'AllianceAuthorization $authorization,\n        Player $actor,',
    'AllianceIntelligenceAuthorization $authorization,\n        Player $actor,',
)
roster_csv.write_text(text, encoding='utf-8')

# Do not restore AllianceRosterEntry::snapshots(). Intelligence owns snapshots and
# filters roster rows through explicit snapshot-table subqueries.
roster_query = repo / 'app/Contexts/Intelligence/Roster/Queries/RosterQuery.php'
text = roster_query.read_text(encoding='utf-8')
old = '''        if ($observation === 'missing') {
            $query->whereDoesntHave('snapshots');
        } elseif ($observation === 'stale') {
            $query
                ->whereHas('snapshots')
                ->whereDoesntHave('snapshots', static function (Builder $snapshot) use ($freshCutoff): void {
                    $snapshot->where('captured_at', '>=', $freshCutoff);
                });
        } elseif ($observation === 'current') {
            $query->whereHas('snapshots', static function (Builder $snapshot) use ($freshCutoff): void {
                $snapshot->where('captured_at', '>=', $freshCutoff);
            });
        }
'''
new = '''        $snapshotExists = static function ($snapshot): void {
            $snapshot->selectRaw('1')
                ->from('player_snapshots')
                ->whereColumn('player_snapshots.roster_entry_id', 'alliance_roster_entries.id');
        };
        $freshSnapshotExists = static function ($snapshot) use ($freshCutoff): void {
            $snapshot->selectRaw('1')
                ->from('player_snapshots')
                ->whereColumn('player_snapshots.roster_entry_id', 'alliance_roster_entries.id')
                ->where('player_snapshots.captured_at', '>=', $freshCutoff);
        };

        if ($observation === 'missing') {
            $query->whereNotExists($snapshotExists);
        } elseif ($observation === 'stale') {
            $query->whereExists($snapshotExists)->whereNotExists($freshSnapshotExists);
        } elseif ($observation === 'current') {
            $query->whereExists($freshSnapshotExists);
        }
'''
if old not in text:
    raise RuntimeError('RosterQuery snapshot relation block changed unexpectedly.')
text = text.replace(old, new)
text = text.replace(
    '''        return AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->whereNotNull('game_role')
            ->where('game_role', '<>', '')
            ->distinct()
            ->orderBy('game_role')
            ->pluck('game_role')
            ->filter(static fn ($role): bool => is_string($role) && $role !== '')
            ->values()
            ->all();''',
    '''        return array_values(AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->whereNotNull('game_role')
            ->where('game_role', '<>', '')
            ->distinct()
            ->orderBy('game_role')
            ->pluck('game_role')
            ->filter(static fn ($role): bool => is_string($role) && $role !== '')
            ->all());''',
)
roster_query.write_text(text, encoding='utf-8')

# Static-analysis contracts that the V1 files left implicit.
stage = repo / 'app/Contexts/Intelligence/Ingestion/Actions/StageKingdomIngestionCandidate.php'
text = stage.read_text(encoding='utf-8')
text = text.replace('    private function canonicalPayload(array $payload): array', '    /** @return array<string, mixed> */\n    private function canonicalPayload(array $payload): array')
text = text.replace('    private function playerPayload(array $payload): array', '    /** @return array<string, mixed> */\n    private function playerPayload(array $payload): array')
text = text.replace('    private function alliancePayload(array $payload): array', '    /** @return array<string, mixed> */\n    private function alliancePayload(array $payload): array')
text = text.replace('    private function assertOnlyKeys(array $payload, array $allowed): void', '    /** @param list<string> $allowed */\n    private function assertOnlyKeys(array $payload, array $allowed): void')
stage.write_text(text, encoding='utf-8')

record_snapshot = repo / 'app/Contexts/Intelligence/Roster/Actions/RecordPlayerSnapshot.php'
text = record_snapshot.read_text(encoding='utf-8')
text = text.replace('    private function machineProvenance(array $provenance): array', '    /** @return array<string, mixed> */\n    private function machineProvenance(array $provenance): array')
record_snapshot.write_text(text, encoding='utf-8')

mutation_state = repo / 'app/Contexts/Intelligence/Ingestion/Services/KingdomIngestionMutationState.php'
text = mutation_state.read_text(encoding='utf-8')
text = text.replace(
    '        return $this->acquire($subscriptionId, false);',
    "        return $this->acquire($subscriptionId, false)\n            ?? throw new LogicException('Required ingestion subscription mutation state was not acquired.');",
    1,
)
mutation_state.write_text(text, encoding='utf-8')

# Configuration follows the context owner. No compatibility config remains.
old_config = repo / 'config/kingdoms.php'
new_config = repo / 'config/intelligence.php'
if old_config.is_file():
    if new_config.exists():
        raise RuntimeError('config/intelligence.php already exists unexpectedly')
    old_config.rename(new_config)
for root_name in roots:
    root = repo / root_name
    if not root.exists():
        continue
    for path in root.rglob('*.php'):
        text = path.read_text(encoding='utf-8')
        rewritten = text.replace("config('kingdoms.ingestion_", "config('intelligence.ingestion_")
        rewritten = rewritten.replace("config('kingdoms.shared_intelligence", "config('intelligence.shared_intelligence")
        rewritten = rewritten.replace("'kingdoms.ingestion_", "'intelligence.ingestion.")
        rewritten = rewritten.replace('"kingdoms.ingestion_', '"intelligence.ingestion.')
        rewritten = rewritten.replace("'kingdoms.roster_", "'intelligence.roster.")
        rewritten = rewritten.replace('"kingdoms.roster_', '"intelligence.roster.')
        if rewritten != text:
            path.write_text(rewritten, encoding='utf-8')

# Recover only P6-owned behavior from the stale Kingdoms test roots.
feature_kingdoms = repo / 'tests/Feature/Kingdoms'
for path in sorted(feature_kingdoms.glob('*.php')) if feature_kingdoms.exists() else []:
    name = path.stem
    capability = 'Ingestion' if 'KingdomIngestion' in name else ('Roster' if ('Roster' in name or 'PlayerSnapshot' in name) else None)
    if capability is None:
        continue
    dst = repo / f'tests/Feature/Intelligence/{capability}/{path.name}'
    dst.parent.mkdir(parents=True, exist_ok=True)
    text = path.read_text(encoding='utf-8').replace(
        'namespace Tests\\Feature\\Kingdoms;',
        f'namespace Tests\\Feature\\Intelligence\\{capability};',
        1,
    )
    dst.write_text(text, encoding='utf-8')
    path.unlink()

unit_kingdoms = repo / 'tests/Unit/Kingdoms'
for path in sorted(unit_kingdoms.glob('*.php')) if unit_kingdoms.exists() else []:
    name = path.stem
    capability = 'Ingestion' if 'KingdomIngestion' in name else ('Roster' if ('Roster' in name or 'PlayerSnapshot' in name) else None)
    if capability is None:
        continue
    dst = repo / f'tests/Unit/Intelligence/{capability}/{path.name}'
    dst.parent.mkdir(parents=True, exist_ok=True)
    text = path.read_text(encoding='utf-8').replace(
        'namespace Tests\\Unit\\Kingdoms;',
        f'namespace Tests\\Unit\\Intelligence\\{capability};',
        1,
    )
    dst.write_text(text, encoding='utf-8')
    path.unlink()

for root_name in ('tests/Feature/Intelligence', 'tests/Unit/Intelligence'):
    root = repo / root_name
    if not root.exists():
        continue
    for path in root.rglob('*.php'):
        text = path.read_text(encoding='utf-8')
        rewritten = text
        for old, new in replacements.items():
            rewritten = rewritten.replace(old, new)
        if rewritten != text:
            path.write_text(rewritten, encoding='utf-8')

print(f'Moved {len(ingestion_files)} ingestion files and {len(roster_files)} roster files with P6 boundary repairs.')
