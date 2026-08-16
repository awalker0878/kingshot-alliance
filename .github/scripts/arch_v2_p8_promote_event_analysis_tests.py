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

    destination.write_text(contents.replace(OLD_NAMESPACE, NEW_NAMESPACE, 1))
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
