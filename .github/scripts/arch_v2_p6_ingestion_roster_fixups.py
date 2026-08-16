from pathlib import Path
import re

repo = Path('.')


def rewrite_docblock(path: Path, method: str, doc: str) -> None:
    text = path.read_text(encoding='utf-8')
    pattern = re.compile(
        r'(?:\n\s*/\*\*[^\n]*\*/)*\n(\s*)private function ' + re.escape(method) + r'\(',
        re.M,
    )
    match = pattern.search(text)
    if not match:
        raise RuntimeError(f'Could not locate {method} in {path}')
    indent = match.group(1)
    replacement = '\n' + '\n'.join(indent + line if line else line for line in doc.splitlines()) + '\n' + indent + f'private function {method}('
    text = text[:match.start()] + replacement + text[match.end():]
    path.write_text(text, encoding='utf-8')


stage = repo / 'app/Contexts/Intelligence/Ingestion/Actions/StageKingdomIngestionCandidate.php'
rewrite_docblock(
    stage,
    'canonicalPayload',
    '''/**
 * @param  array<string|int, mixed>  $payload
 * @return array<string, mixed>
 */''',
)
rewrite_docblock(
    stage,
    'playerPayload',
    '''/**
 * @param  array<string|int, mixed>  $payload
 * @return array<string, mixed>
 */''',
)
rewrite_docblock(
    stage,
    'alliancePayload',
    '''/**
 * @param  array<string|int, mixed>  $payload
 * @return array<string, mixed>
 */''',
)
rewrite_docblock(
    stage,
    'assertOnlyKeys',
    '''/**
 * @param  array<string|int, mixed>  $payload
 * @param  list<string>  $allowed
 */''',
)

record = repo / 'app/Contexts/Intelligence/Roster/Actions/RecordPlayerSnapshot.php'
rewrite_docblock(
    record,
    'machineProvenance',
    '''/**
 * @param  array<string, mixed>  $provenance
 * @return array<string, string|null>
 */''',
)

INTEL_AUTH_USE = 'use App\\Contexts\\Intelligence\\Access\\Services\\AllianceIntelligenceAuthorization;'


def ensure_use(text: str, after: str, use_line: str) -> str:
    if use_line in text:
        return text
    if after not in text:
        raise RuntimeError(f'Missing import anchor: {after}')
    return text.replace(after, after + '\n' + use_line, 1)


# PlayerSnapshot: Alliance view remains Alliance-owned; management visibility is Intelligence-owned.
path = repo / 'app/Contexts/Intelligence/Roster/Http/Controllers/PlayerSnapshotController.php'
text = path.read_text(encoding='utf-8')
text = ensure_use(text, 'use App\\Contexts\\Intelligence\\Access\\Enums\\IntelligencePermission;', INTEL_AUTH_USE)
needle = '''        AllianceAuthorization $authorization,
        PlayerSnapshotQuery $snapshots,'''
replacement = '''        AllianceAuthorization $authorization,
        AllianceIntelligenceAuthorization $intelligenceAuthorization,
        PlayerSnapshotQuery $snapshots,'''
if needle not in text:
    raise RuntimeError('PlayerSnapshotController show signature changed unexpectedly')
text = text.replace(needle, replacement, 1)
text = text.replace(
    '$canManage = $authorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage);',
    '$canManage = $intelligenceAuthorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage);',
    1,
)
path.write_text(text, encoding='utf-8')

# Roster index needs both policies; management page is Intelligence-only.
path = repo / 'app/Contexts/Intelligence/Roster/Http/Controllers/RosterController.php'
text = path.read_text(encoding='utf-8')
text = ensure_use(text, 'use App\\Contexts\\Intelligence\\Access\\Enums\\IntelligencePermission;', INTEL_AUTH_USE)
needle = '''        AllianceAuthorization $authorization,
        RosterQuery $roster,
        PlayerSnapshotQuery $snapshots,'''
replacement = '''        AllianceAuthorization $authorization,
        AllianceIntelligenceAuthorization $intelligenceAuthorization,
        RosterQuery $roster,
        PlayerSnapshotQuery $snapshots,'''
if needle not in text:
    raise RuntimeError('RosterController index signature changed unexpectedly')
text = text.replace(needle, replacement, 1)
text = text.replace(
    "'canManage' => $authorization->allows($actor, $alliance, IntelligencePermission::KingdomManage),",
    "'canManage' => $intelligenceAuthorization->allows($actor, $alliance, IntelligencePermission::KingdomManage),",
    1,
)
manage_sig = '''    public function manage(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,'''
manage_new = '''    public function manage(
        Request $request,
        AllianceContext $context,
        AllianceIntelligenceAuthorization $authorization,'''
if manage_sig not in text:
    raise RuntimeError('RosterController manage signature changed unexpectedly')
text = text.replace(manage_sig, manage_new, 1)
path.write_text(text, encoding='utf-8')

# Roster intelligence page: basic page visibility is Alliance-owned; private comparisons are Intelligence-owned.
path = repo / 'app/Contexts/Intelligence/Roster/Http/Controllers/RosterIntelligenceController.php'
text = path.read_text(encoding='utf-8')
text = ensure_use(text, 'use App\\Contexts\\Intelligence\\Access\\Enums\\IntelligencePermission;', INTEL_AUTH_USE)
needle = '''        AllianceAuthorization $authorization,
        RosterIntelligence $intelligence,'''
replacement = '''        AllianceAuthorization $authorization,
        AllianceIntelligenceAuthorization $intelligenceAuthorization,
        RosterIntelligence $intelligence,'''
if needle not in text:
    raise RuntimeError('RosterIntelligenceController signature changed unexpectedly')
text = text.replace(needle, replacement, 1)
text = text.replace(
    '$canManage = $authorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage);',
    '$canManage = $intelligenceAuthorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage);',
    1,
)
path.write_text(text, encoding='utf-8')

# Make RosterCsv authorization wiring deterministic after the broader move script.
path = repo / 'app/Contexts/Intelligence/Roster/Http/Controllers/RosterCsvController.php'
text = path.read_text(encoding='utf-8')
text = ensure_use(text, 'use App\\Contexts\\Intelligence\\Access\\Enums\\IntelligencePermission;', INTEL_AUTH_USE)

# index/show are management-only.
for method in ('index', 'show'):
    pattern = re.compile(
        r'(public function ' + method + r'\(\n\s*Request \$request,\n\s*AllianceContext \$context,\n\s*)(?:AllianceAuthorization|AllianceIntelligenceAuthorization) \$authorization,'
    )
    text, count = pattern.subn(r'\1AllianceIntelligenceAuthorization $authorization,', text, count=1)
    if count != 1:
        raise RuntimeError(f'RosterCsvController {method} signature not found')

# Export needs both contexts because member export is Alliance view while management export is Intelligence.
export_pattern = re.compile(
    r'(public function export\(\n\s*Request \$request,\n\s*AllianceContext \$context,\n)(?:\s*AllianceAuthorization \$authorization,\n)?(?:\s*AllianceIntelligenceAuthorization \$intelligenceAuthorization,\n)?\s*RosterCsvExporter \$exporter,'
)
export_replacement = r'''\1        AllianceAuthorization $allianceAuthorization,
        AllianceIntelligenceAuthorization $intelligenceAuthorization,
        RosterCsvExporter $exporter,'''
text, count = export_pattern.subn(export_replacement, text, count=1)
if count != 1:
    raise RuntimeError('RosterCsvController export signature not found')

# Normalize either the original mixed-permission block or the first-pass split block.
body_start = text.index("        $includePrivate = ($validated['scope'] ?? 'member') === 'management';", text.index('public function export'))
body_end = text.index('\n\n        $export = $exporter->export', body_start)
new_body = '''        $includePrivate = ($validated['scope'] ?? 'member') === 'management';
        $allowed = $includePrivate
            ? $intelligenceAuthorization->allows($context->player(), $alliance, IntelligencePermission::KingdomManage)
            : $allianceAuthorization->allows($context->player(), $alliance, AlliancePermission::View);

        if (! $allowed) {
            throw new AuthorizationException;
        }'''
text = text[:body_start] + new_body + text[body_end:]

# Helper must always accept the Intelligence policy.
text = re.sub(
    r'(private function authorizeManage\(\n\s*)(?:AllianceAuthorization|AllianceIntelligenceAuthorization) \$authorization,',
    r'\1AllianceIntelligenceAuthorization $authorization,',
    text,
    count=1,
)
path.write_text(text, encoding='utf-8')

print('Applied P6 ingestion/roster PHPDoc and authorization fixups.')
