from pathlib import Path
import runpy

repo = Path('.')

# Finish the Intelligence presentation/read-model boundary after the mechanical
# ingestion/roster cut. Keep this invoked from the existing one-shot workflow so
# the whole P6 change is still reproduced and committed as a single verified cut.
runtime_cut = repo / '.github/scripts/arch_v2_p6_intelligence_runtime_cut.py'
if not runtime_cut.is_file():
    raise RuntimeError('Missing P6 Intelligence runtime cut helper.')
runpy.run_path(str(runtime_cut), run_name='__main__')
runtime_cut.unlink()

# The hard-cut configuration keeps the existing flat key shape under the new
# Intelligence owner. The first mechanical pass rewrote config()->set() keys
# to dotted ingestion.* paths while runtime config() calls retained the flat
# ingestion_* keys. Normalize both production/test string literals to the one
# context-owned contract.
for root_name in ('app', 'routes', 'bootstrap', 'config', 'database', 'tests'):
    root = repo / root_name
    if not root.exists():
        continue
    for path in root.rglob('*.php'):
        text = path.read_text(encoding='utf-8')
        rewritten = text.replace("'intelligence.ingestion.", "'intelligence.ingestion_")
        rewritten = rewritten.replace('"intelligence.ingestion.', '"intelligence.ingestion_')
        rewritten = rewritten.replace("'intelligence.roster.", "'intelligence.roster_")
        rewritten = rewritten.replace('"intelligence.roster.', '"intelligence.roster_')
        if rewritten != text:
            path.write_text(rewritten, encoding='utf-8')

# Observations were already hard-cut earlier in P6. Recovered ingestion tests
# must call the V2 observation capability directly instead of resurrecting V1
# Kingdoms aliases. That includes the tracking model/state used by promotion
# contracts, not just the StartTracking action.
ingestion_tests = repo / 'tests/Feature/Intelligence/Ingestion'
if ingestion_tests.exists():
    observation_replacements = {
        'App\\Domain\\Kingdoms\\Actions\\StartTrackingKingdomAlliance':
            'App\\Contexts\\Intelligence\\Observations\\Actions\\StartTrackingKingdomAlliance',
        'App\\Domain\\Kingdoms\\Models\\TrackedKingdomAlliance':
            'App\\Contexts\\Intelligence\\Observations\\Models\\TrackedKingdomAlliance',
        'App\\Domain\\Kingdoms\\Enums\\TrackedKingdomAllianceState':
            'App\\Contexts\\Intelligence\\Observations\\Enums\\TrackedKingdomAllianceState',
    }
    for path in ingestion_tests.rglob('*.php'):
        text = path.read_text(encoding='utf-8')
        rewritten = text
        for old, new in observation_replacements.items():
            rewritten = rewritten.replace(old, new)
        if rewritten != text:
            path.write_text(rewritten, encoding='utf-8')

# This roster contract explicitly verifies Intelligence's interpretation of
# Alliance rank. It therefore resolves the Intelligence authorization policy,
# not AllianceAuthorization with a downstream permission argument.
roster_test = repo / 'tests/Feature/Intelligence/Roster/RosterTest.php'
if roster_test.is_file():
    text = roster_test.read_text(encoding='utf-8')
    intel_use = 'use App\\Contexts\\Intelligence\\Access\\Services\\AllianceIntelligenceAuthorization;'
    anchor = 'use App\\Contexts\\Intelligence\\Access\\Enums\\IntelligencePermission;'
    if intel_use not in text:
        if anchor not in text:
            raise RuntimeError('RosterTest IntelligencePermission import anchor missing.')
        text = text.replace(anchor, anchor + '\n' + intel_use, 1)

    method_start = text.find('public function test_r5_and_r4_players_receive_kingdom_manage_but_lower_ranks_and_specialists_do_not(): void')
    if method_start < 0:
        raise RuntimeError('RosterTest KingdomManage semantic ownership contract missing.')
    method_end = text.find('\n    public function ', method_start + 1)
    if method_end < 0:
        method_end = len(text)
    before = text[:method_start]
    method = text[method_start:method_end]
    after = text[method_end:]
    method = method.replace(
        '$this->app->make(AllianceAuthorization::class)',
        '$this->app->make(AllianceIntelligenceAuthorization::class)',
    )
    text = before + method + after
    roster_test.write_text(text, encoding='utf-8')

# Fail the one-shot cut itself if any recovered ingestion test still references
# deleted V1 observation ownership or a split-brain ingestion config key.
violations = []
for root in (repo / 'tests/Feature/Intelligence/Ingestion', repo / 'app/Contexts/Intelligence/Ingestion'):
    if not root.exists():
        continue
    for path in root.rglob('*.php'):
        text = path.read_text(encoding='utf-8')
        for stale in (
            'App\\Domain\\Kingdoms\\Actions\\StartTrackingKingdomAlliance',
            'App\\Domain\\Kingdoms\\Models\\TrackedKingdomAlliance',
            'App\\Domain\\Kingdoms\\Enums\\TrackedKingdomAllianceState',
        ):
            if stale in text:
                violations.append(f'{path}: V1 observation reference {stale}')
        if 'intelligence.ingestion.' in text:
            violations.append(f'{path}: split-brain Intelligence ingestion config key')

if violations:
    raise RuntimeError('\n'.join(violations))

print('Rewrote P6 Intelligence acceptance contracts and normalized config ownership.')
