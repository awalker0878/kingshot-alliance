import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SOURCE = ROOT / "tests/RewriteInput/Intelligence/EventAnalysis"
DESTINATION = ROOT / "tests/Feature/Intelligence/EventAnalysis"
EXPECTED = {
    "EventContributionHistorySchemaTest.php",
    "EventContributionIntelligenceQueryTest.php",
    "EventHistoryPerformanceContractTest.php",
    "EventHistorySecurityTest.php",
    "EventLeaderboardQueryTest.php",
    "EventOrganizationEvidenceQueryTest.php",
    "EventOrganizationHistoryQueryTest.php",
    "EventPlayerHistoryQueryTest.php",
    "EventResultsIntelligenceTest.php",
    "EventTrendQueryTest.php",
}
OLD_NAMESPACE = "namespace Tests\\RewriteInput\\Intelligence\\EventAnalysis;"
NEW_NAMESPACE = "namespace Tests\\Feature\\Intelligence\\EventAnalysis;"
OWNER_REPLACEMENTS = {
    "App\\Contexts\\Operations\\EventCore\\Models\\EventAllianceResult": "App\\Contexts\\Operations\\Results\\Models\\EventAllianceResult",
    "App\\Contexts\\Operations\\EventCore\\Models\\EventAllianceResultMetric": "App\\Contexts\\Operations\\Results\\Models\\EventAllianceResultMetric",
    "App\\Contexts\\Operations\\EventCore\\Models\\EventMetricDefinition": "App\\Contexts\\Operations\\Results\\Models\\EventMetricDefinition",
    "App\\Contexts\\Operations\\EventCore\\Models\\EventPlayerResult": "App\\Contexts\\Operations\\Results\\Models\\EventPlayerResult",
    "App\\Contexts\\Operations\\EventCore\\Models\\EventPlayerResultMetric": "App\\Contexts\\Operations\\Results\\Models\\EventPlayerResultMetric",
    "App\\Contexts\\Operations\\EventCore\\Models\\EventResult": "App\\Contexts\\Operations\\Results\\Models\\EventResult",
    "App\\Contexts\\Operations\\EventCore\\Models\\EventResultMetric": "App\\Contexts\\Operations\\Results\\Models\\EventResultMetric",
    "App\\Contexts\\Operations\\EventCore\\Models\\EventPlayerContext": "App\\Contexts\\Operations\\Participation\\Models\\EventPlayerContext",
    "App\\Contexts\\Operations\\EventCore\\Models\\EventObjective": "App\\Contexts\\Operations\\BattlePlans\\Models\\EventObjective",
    "App\\Contexts\\Operations\\EventCore\\Models\\EventObjectiveAssignment": "App\\Contexts\\Operations\\BattlePlans\\Models\\EventObjectiveAssignment",
}
EVENT_CORE_MODELS = {
    "Event",
    "EventOccurrence",
    "EventPhase",
    "EventTemplate",
    "EventType",
    "EventTypeCapability",
    "EventTypeScope",
}
EVENT_CORE_MODEL_IMPORT = re.compile(r"use App\\Contexts\\Operations\\EventCore\\Models\\([A-Za-z0-9_]+);")

if not SOURCE.is_dir():
    raise SystemExit(f"Expected staging directory is missing: {SOURCE.relative_to(ROOT)}")

actual = {path.name for path in SOURCE.glob("*.php")}
if actual != EXPECTED:
    missing = sorted(EXPECTED - actual)
    unexpected = sorted(actual - EXPECTED)
    raise SystemExit(f"Unexpected EventAnalysis staging inventory; missing={missing}, unexpected={unexpected}")

DESTINATION.mkdir(parents=True, exist_ok=True)

for name in sorted(EXPECTED):
    source = SOURCE / name
    destination = DESTINATION / name
    if destination.exists():
        raise SystemExit(f"Destination already exists: {destination.relative_to(ROOT)}")

    contents = source.read_text()
    if contents.count(OLD_NAMESPACE) != 1:
        raise SystemExit(f"Expected exactly one staging namespace in {source.relative_to(ROOT)}")

    contents = contents.replace(OLD_NAMESPACE, NEW_NAMESPACE, 1)
    for old_owner, new_owner in OWNER_REPLACEMENTS.items():
        contents = contents.replace(old_owner, new_owner)

    stale_event_core_models = sorted(
        model
        for model in EVENT_CORE_MODEL_IMPORT.findall(contents)
        if model not in EVENT_CORE_MODELS
    )
    if stale_event_core_models:
        raise SystemExit(
            f"Unexpected EventCore model ownership in {source.relative_to(ROOT)}: {stale_event_core_models}"
        )

    destination.write_text(contents)
    source.unlink()

for directory in [SOURCE, SOURCE.parent, SOURCE.parent.parent]:
    if directory.exists():
        try:
            directory.rmdir()
        except OSError as exc:
            raise SystemExit(f"Staging directory is not empty after promotion: {directory.relative_to(ROOT)}") from exc

if (ROOT / "tests/RewriteInput").exists():
    raise SystemExit("tests/RewriteInput still exists after EventAnalysis promotion")

for name in EXPECTED:
    contents = (DESTINATION / name).read_text()
    if NEW_NAMESPACE not in contents or "Tests\\RewriteInput" in contents:
        raise SystemExit(f"Namespace promotion failed for {name}")

print(f"Promoted {len(EXPECTED)} EventAnalysis tests into tests/Feature/Intelligence/EventAnalysis")
