from pathlib import Path
import re

repo = Path('.')


def canonical_doc(path: Path, method: str, lines: list[str]) -> None:
    text = path.read_text(encoding='utf-8')
    sig = f'private function {method}('
    pos = text.find(sig)
    if pos < 0:
        raise RuntimeError(f'{method} not found in {path}')
    line_start = text.rfind('\n', 0, pos) + 1
    doc_start = line_start
    while True:
        prev_end = doc_start - 1
        if prev_end <= 0:
            break
        prev_start = text.rfind('\n', 0, prev_end) + 1
        prev = text[prev_start:prev_end].strip()
        if prev.startswith('/**') and prev.endswith('*/'):
            doc_start = prev_start
            continue
        break
    indent = text[line_start:pos]
    block = indent + '/**\n' + ''.join(indent + ' * ' + line + '\n' for line in lines) + indent + ' */\n'
    text = text[:doc_start] + block + text[line_start:]
    path.write_text(text, encoding='utf-8')


stage = repo / 'app/Contexts/Intelligence/Ingestion/Actions/StageKingdomIngestionCandidate.php'
canonical_doc(stage, 'canonicalPayload', [
    '@param  array<string|int, mixed>  $payload',
    '@return array<string, mixed>',
])
canonical_doc(stage, 'playerPayload', [
    '@param  array<string|int, mixed>  $payload',
    '@return array<string, mixed>',
])
canonical_doc(stage, 'alliancePayload', [
    '@param  array<string|int, mixed>  $payload',
    '@return array<string, mixed>',
])
canonical_doc(stage, 'assertOnlyKeys', [
    '@param  array<string|int, mixed>  $payload',
    '@param  list<string>  $allowed',
])
canonical_doc(
    repo / 'app/Contexts/Intelligence/Roster/Actions/RecordPlayerSnapshot.php',
    'machineProvenance',
    [
        '@param  array<string, mixed>  $provenance',
        '@return array<string, string|null>',
    ],
)

intel_use = 'use App\\Contexts\\Intelligence\\Access\\Services\\AllianceIntelligenceAuthorization;'


def with_intel_use(text: str) -> str:
    if intel_use in text:
        return text
    anchor = 'use App\\Contexts\\Intelligence\\Access\\Enums\\IntelligencePermission;'
    if anchor not in text:
        raise RuntimeError('Intelligence permission import anchor missing')
    return text.replace(anchor, anchor + '\n' + intel_use, 1)


# PlayerSnapshotController: Alliance view + Intelligence management visibility.
path = repo / 'app/Contexts/Intelligence/Roster/Http/Controllers/PlayerSnapshotController.php'
text = with_intel_use(path.read_text(encoding='utf-8'))
text = text.replace(
    '        AllianceAuthorization $authorization,\n        PlayerSnapshotQuery $snapshots,',
    '        AllianceAuthorization $authorization,\n        AllianceIntelligenceAuthorization $intelligenceAuthorization,\n        PlayerSnapshotQuery $snapshots,',
    1,
)
text = text.replace(
    '$canManage = $authorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage);',
    '$canManage = $intelligenceAuthorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage);',
    1,
)
path.write_text(text, encoding='utf-8')

# RosterController: Alliance view on index, Intelligence management otherwise.
path = repo / 'app/Contexts/Intelligence/Roster/Http/Controllers/RosterController.php'
text = with_intel_use(path.read_text(encoding='utf-8'))
text = text.replace(
    '        AllianceAuthorization $authorization,\n        RosterQuery $roster,\n        PlayerSnapshotQuery $snapshots,',
    '        AllianceAuthorization $authorization,\n        AllianceIntelligenceAuthorization $intelligenceAuthorization,\n        RosterQuery $roster,\n        PlayerSnapshotQuery $snapshots,',
    1,
)
text = text.replace(
    "'canManage' => $authorization->allows($actor, $alliance, IntelligencePermission::KingdomManage),",
    "'canManage' => $intelligenceAuthorization->allows($actor, $alliance, IntelligencePermission::KingdomManage),",
    1,
)
text = text.replace(
    '    public function manage(\n        Request $request,\n        AllianceContext $context,\n        AllianceAuthorization $authorization,',
    '    public function manage(\n        Request $request,\n        AllianceContext $context,\n        AllianceIntelligenceAuthorization $authorization,',
    1,
)
path.write_text(text, encoding='utf-8')

# RosterIntelligenceController: public roster visibility remains Alliance-owned.
path = repo / 'app/Contexts/Intelligence/Roster/Http/Controllers/RosterIntelligenceController.php'
text = with_intel_use(path.read_text(encoding='utf-8'))
text = text.replace(
    '        AllianceAuthorization $authorization,\n        RosterIntelligence $intelligence,',
    '        AllianceAuthorization $authorization,\n        AllianceIntelligenceAuthorization $intelligenceAuthorization,\n        RosterIntelligence $intelligence,',
    1,
)
text = text.replace(
    '$canManage = $authorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage);',
    '$canManage = $intelligenceAuthorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage);',
    1,
)
path.write_text(text, encoding='utf-8')

# RosterCsvController: index/show are management-only; export has separate policies.
path = repo / 'app/Contexts/Intelligence/Roster/Http/Controllers/RosterCsvController.php'
text = with_intel_use(path.read_text(encoding='utf-8'))
for method in ('index', 'show'):
    pattern = re.compile(
        r'(public function ' + method + r'\(\n\s*Request \$request,\n\s*AllianceContext \$context,\n\s*)(?:AllianceAuthorization|AllianceIntelligenceAuthorization) \$[A-Za-z_][A-Za-z0-9_]*,'
    )
    text, count = pattern.subn(r'\1AllianceIntelligenceAuthorization $authorization,', text, count=1)
    if count != 1:
        raise RuntimeError(f'{method} authorization signature not found')

export_start = text.index('    public function export(')
export_body = text.index('    ): HttpResponse {', export_start)
header = text[export_start:export_body]
header = re.sub(r'\n\s*AllianceAuthorization \$[A-Za-z_][A-Za-z0-9_]*,', '', header)
header = re.sub(r'\n\s*AllianceIntelligenceAuthorization \$[A-Za-z_][A-Za-z0-9_]*,', '', header)
header = header.replace(
    '        RosterCsvExporter $exporter,',
    '        AllianceAuthorization $allianceAuthorization,\n        AllianceIntelligenceAuthorization $intelligenceAuthorization,\n        RosterCsvExporter $exporter,',
    1,
)
text = text[:export_start] + header + text[export_body:]

body_start = text.index("        $includePrivate = ($validated['scope'] ?? 'member') === 'management';", export_start)
body_end = text.index('\n\n        $export = $exporter->export', body_start)
text = text[:body_start] + '''        $includePrivate = ($validated['scope'] ?? 'member') === 'management';
        $allowed = $includePrivate
            ? $intelligenceAuthorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage)
            : $allianceAuthorization->allows($context->player(), $alliance, AlliancePermission::View);

        if (! $allowed) {
            throw new AuthorizationException;
        }''' + text[body_end:]

text, count = re.subn(
    r'(private function authorizeManage\(\n\s*)(?:AllianceAuthorization|AllianceIntelligenceAuthorization) \$[A-Za-z_][A-Za-z0-9_]*,',
    r'\1AllianceIntelligenceAuthorization $authorization,',
    text,
    count=1,
)
if count != 1:
    raise RuntimeError('authorizeManage signature not found')
path.write_text(text, encoding='utf-8')

print('Applied P6 ingestion/roster v2 fixups.')
