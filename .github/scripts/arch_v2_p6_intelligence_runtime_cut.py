from pathlib import Path
import re

repo = Path('.')
legacy = repo / 'app/Domain/Kingdoms'
replacements: dict[str, str] = {}


def fqcn(path: Path, text: str) -> str:
    match = re.search(r'^namespace\s+([^;]+);', text, re.M)
    if not match:
        raise RuntimeError(f'No namespace in {path}')
    return match.group(1) + '\\' + path.stem


def move_php(src: Path, dst: Path) -> None:
    if not src.is_file():
        raise RuntimeError(f'Missing P6 runtime source: {src}')
    if dst.exists():
        raise RuntimeError(f'P6 runtime destination already exists: {dst}')
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


def add_use(path: Path, use_line: str) -> None:
    text = path.read_text(encoding='utf-8')
    if use_line in text:
        return
    marker = re.search(r'^(namespace [^;]+;\n)', text, re.M)
    if not marker:
        raise RuntimeError(f'No namespace marker in {path}')
    insert_at = marker.end()
    path.write_text(text[:insert_at] + f'\n{use_line}\n' + text[insert_at:], encoding='utf-8')


# Finish the observation presentation boundary. These controllers only exist to
# expose Intelligence tracking/observation behavior; they are not GameWorld or
# transfer workflow controllers.
for name in ('KingdomAllianceController', 'KingdomAllianceObservationController'):
    move_php(
        legacy / f'Http/Controllers/{name}.php',
        repo / f'app/Contexts/Intelligence/Observations/Http/Controllers/{name}.php',
    )

# The combined Intelligence dashboard is a presentation endpoint over a
# compositional read model, while sharing mutations remain owned by Sharing.
move_php(
    legacy / 'Http/Controllers/KingdomAllianceIntelligenceController.php',
    repo / 'app/Contexts/Intelligence/Http/Controllers/KingdomAllianceIntelligenceController.php',
)
move_php(
    legacy / 'Http/Controllers/KingdomIntelligenceSharingController.php',
    repo / 'app/Contexts/Intelligence/Sharing/Http/Controllers/KingdomIntelligenceSharingController.php',
)

# Cross-capability dashboards/history are V2 read models, not another write
# context. They may compose Alliance + multiple Intelligence capabilities.
for source_group, destination, names in (
    ('Queries', 'app/ReadModels/KingdomIntelligence', ('KingdomAllianceIntelligenceQuery',)),
    ('Services', 'app/ReadModels/KingdomIntelligence', ('KingdomAllianceIntelligence',)),
    ('Queries', 'app/ReadModels/SharedKingdomIntelligence', (
        'SharedKingdomIntelligenceCurrentQuery',
        'SharedKingdomIntelligenceHistoryQuery',
    )),
    ('Services', 'app/ReadModels/SharedKingdomIntelligence', ('SharedKingdomIntelligenceHistoryCursor',)),
):
    for name in names:
        move_php(legacy / f'{source_group}/{name}.php', repo / f'{destination}/{name}.php')

# Apply all old -> new FQCN moves to production, routes, tests and configuration.
for root_name in ('app', 'routes', 'bootstrap', 'config', 'database', 'tests'):
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

# Read-model dependencies that were implicit while everything shared the old
# Kingdoms namespace must be explicit after the cut.
for path in (
    repo / 'app/ReadModels/SharedKingdomIntelligence/SharedKingdomIntelligenceCurrentQuery.php',
    repo / 'app/ReadModels/SharedKingdomIntelligence/SharedKingdomIntelligenceHistoryQuery.php',
):
    add_use(path, 'use App\\Contexts\\Intelligence\\Observations\\Queries\\KingdomAllianceObservationQuery;')
add_use(
    repo / 'app/ReadModels/KingdomIntelligence/KingdomAllianceIntelligence.php',
    'use App\\Contexts\\Intelligence\\Roster\\Services\\PowerMath;',
)

# Intelligence permissions are interpreted only by Intelligence authorization.
auth_import = 'use App\\Contexts\\Intelligence\\Access\\Services\\AllianceIntelligenceAuthorization;'
controllers = {
    'tracking': repo / 'app/Contexts/Intelligence/Observations/Http/Controllers/KingdomAllianceController.php',
    'history': repo / 'app/Contexts/Intelligence/Observations/Http/Controllers/KingdomAllianceObservationController.php',
    'dashboard': repo / 'app/Contexts/Intelligence/Http/Controllers/KingdomAllianceIntelligenceController.php',
    'sharing': repo / 'app/Contexts/Intelligence/Sharing/Http/Controllers/KingdomIntelligenceSharingController.php',
}
for path in controllers.values():
    add_use(path, auth_import)

# Tracking controller: Alliance View remains Alliance-owned; manage checks are
# Intelligence-owned. Mutation actions already enforce their own actor boundary.
path = controllers['tracking']
text = path.read_text(encoding='utf-8')
text = text.replace(
    'AllianceAuthorization $authorization,\n        KingdomAuthorization $kingdomAuthorization,',
    'AllianceAuthorization $authorization,\n        AllianceIntelligenceAuthorization $intelligenceAuthorization,\n        KingdomAuthorization $kingdomAuthorization,',
    1,
)
text = text.replace(
    "'canManage' => $authorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage),",
    "'canManage' => $intelligenceAuthorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage),",
    1,
)
text = text.replace(
    'AllianceAuthorization $authorization,\n        KingdomAllianceQuery $tracking,',
    'AllianceIntelligenceAuthorization $intelligenceAuthorization,\n        KingdomAllianceQuery $tracking,',
    1,
)
text = text.replace(
    '$authorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage)',
    '$intelligenceAuthorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage)',
)
path.write_text(text, encoding='utf-8')

# Observation history keeps Alliance View for disclosure but uses Intelligence
# authorization to decide whether private provenance/management fields appear.
path = controllers['history']
text = path.read_text(encoding='utf-8')
text = text.replace(
    'AllianceAuthorization $authorization,\n        KingdomAllianceObservationQuery $observations,',
    'AllianceAuthorization $authorization,\n        AllianceIntelligenceAuthorization $intelligenceAuthorization,\n        KingdomAllianceObservationQuery $observations,',
    1,
)
text = text.replace(
    '$canManage = $authorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage);',
    '$canManage = $intelligenceAuthorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage);',
    1,
)
path.write_text(text, encoding='utf-8')

# Dashboard: AllianceAuthorization only gates basic visibility.
path = controllers['dashboard']
text = path.read_text(encoding='utf-8')
text = text.replace(
    'AllianceAuthorization $authorization,\n        KingdomAllianceIntelligence $intelligence,',
    'AllianceAuthorization $authorization,\n        AllianceIntelligenceAuthorization $intelligenceAuthorization,\n        KingdomAllianceIntelligence $intelligence,',
    1,
)
text = text.replace(
    '$canManage = $authorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage);',
    '$canManage = $intelligenceAuthorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage);',
    1,
)
path.write_text(text, encoding='utf-8')

# Sharing: recipient view remains Alliance-visible; management is an Intelligence
# permission. Do not widen AllianceAuthorization's permission type.
path = controllers['sharing']
text = path.read_text(encoding='utf-8')
text = text.replace(
    'AllianceAuthorization $authorization,\n        SharedKingdomIntelligenceCurrentQuery $current,',
    'AllianceAuthorization $authorization,\n        AllianceIntelligenceAuthorization $intelligenceAuthorization,\n        SharedKingdomIntelligenceCurrentQuery $current,',
    1,
)
text = text.replace(
    "'canManage' => $authorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage),",
    "'canManage' => $intelligenceAuthorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage),",
    1,
)
text = text.replace(
    'AllianceAuthorization $authorization,\n        KingdomIntelligenceSharingManageQuery $sharing,',
    'AllianceIntelligenceAuthorization $intelligenceAuthorization,\n        KingdomIntelligenceSharingManageQuery $sharing,',
    1,
)
text = text.replace(
    '$authorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage)',
    '$intelligenceAuthorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage)',
)
path.write_text(text, encoding='utf-8')

# Strengthen the permanent architecture contract so P6 cannot regress back into
# the V1 Kingdoms presentation/query/service roots.
architecture = repo / 'tests/Architecture/ArchitectureV2IntelligenceTest.php'
text = architecture.read_text(encoding='utf-8')
anchor = "            'Http/Controllers/KingdomAllianceDiplomacyContactController.php',\n"
extra = """            'Http/Controllers/KingdomAllianceController.php',
            'Http/Controllers/KingdomAllianceObservationController.php',
            'Http/Controllers/KingdomAllianceIntelligenceController.php',
            'Http/Controllers/KingdomIntelligenceSharingController.php',
            'Queries/KingdomAllianceIntelligenceQuery.php',
            'Queries/SharedKingdomIntelligenceCurrentQuery.php',
            'Queries/SharedKingdomIntelligenceHistoryQuery.php',
            'Services/KingdomAllianceIntelligence.php',
            'Services/SharedKingdomIntelligenceHistoryCursor.php',
"""
if extra.strip() not in text:
    if anchor not in text:
        raise RuntimeError('ArchitectureV2IntelligenceTest legacy-path anchor missing.')
    text = text.replace(anchor, anchor + extra, 1)
architecture.write_text(text, encoding='utf-8')

# Fail immediately if this pass leaves a known P6 Intelligence runtime straggler
# under the V1 noun-domain.
for relative in (
    'Http/Controllers/KingdomAllianceController.php',
    'Http/Controllers/KingdomAllianceObservationController.php',
    'Http/Controllers/KingdomAllianceIntelligenceController.php',
    'Http/Controllers/KingdomIntelligenceSharingController.php',
    'Queries/KingdomAllianceIntelligenceQuery.php',
    'Queries/SharedKingdomIntelligenceCurrentQuery.php',
    'Queries/SharedKingdomIntelligenceHistoryQuery.php',
    'Services/KingdomAllianceIntelligence.php',
    'Services/SharedKingdomIntelligenceHistoryCursor.php',
):
    if (legacy / relative).exists():
        raise RuntimeError(f'P6 V1 Intelligence runtime remains: {relative}')

print('Finished P6 Intelligence presentation/read-model hard cut.')
