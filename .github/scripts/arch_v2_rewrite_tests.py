from __future__ import annotations

import re
import shutil
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
TESTS = ROOT / "tests"
NS_RE = re.compile(r"^namespace\s+[^;]+;", re.M)
CLASS_RE = re.compile(r"(?P<prefix>\b(?:final\s+)?class\s+)(?P<name>[A-Za-z_][A-Za-z0-9_]*Test)\b")

EVENT_REPLACEMENTS = {
    r"App\Contexts\Operations\EventCore\Models\EventAllianceResult": r"App\Contexts\Operations\Results\Models\EventAllianceResult",
    r"App\Contexts\Operations\EventCore\Models\EventAllianceResultMetric": r"App\Contexts\Operations\Results\Models\EventAllianceResultMetric",
    r"App\Contexts\Operations\EventCore\Models\EventMetricDefinition": r"App\Contexts\Operations\Results\Models\EventMetricDefinition",
    r"App\Contexts\Operations\EventCore\Models\EventPlayerResult": r"App\Contexts\Operations\Results\Models\EventPlayerResult",
    r"App\Contexts\Operations\EventCore\Models\EventPlayerResultMetric": r"App\Contexts\Operations\Results\Models\EventPlayerResultMetric",
    r"App\Contexts\Operations\EventCore\Models\EventResult": r"App\Contexts\Operations\Results\Models\EventResult",
    r"App\Contexts\Operations\EventCore\Models\EventResultMetric": r"App\Contexts\Operations\Results\Models\EventResultMetric",
    r"App\Contexts\Operations\EventCore\Models\EventPlayerContext": r"App\Contexts\Operations\Participation\Models\EventPlayerContext",
    r"App\Contexts\Operations\EventCore\Models\EventObjective": r"App\Contexts\Operations\BattlePlans\Models\EventObjective",
    r"App\Contexts\Operations\EventCore\Models\EventObjectiveAssignment": r"App\Contexts\Operations\BattlePlans\Models\EventObjectiveAssignment",
}


def v2_name(name: str) -> str:
    if name.endswith("V2Test.php"):
        return name
    if not name.endswith("Test.php"):
        raise SystemExit(f"Not a PHPUnit test: {name}")
    return name[:-8] + "V2Test.php"


def ensure_use(text: str, fqcn: str) -> str:
    line = f"use {fqcn};"
    if line in text:
        return text
    match = NS_RE.search(text)
    if match is None:
        raise SystemExit(f"Cannot add import {fqcn}: no namespace")
    return text[:match.end()] + "\n\n" + line + text[match.end():]


def rewrite_identity_fixtures(text: str) -> str:
    kingdom = re.compile(
        r"(?P<i>^[ \t]*)\$(?P<v>\w+)\s*=\s*Kingdom::query\(\)->create\(\[\s*"
        r"'number'\s*=>\s*(?P<n>[^,\]\n]+)"
        r"(?:\s*,\s*'status'\s*=>\s*'active')?\s*,?\s*\]\);",
        re.M,
    )
    if kingdom.search(text):
        text = ensure_use(text, r"App\Contexts\GameWorld\Actions\ResolveKingdom")
        text = kingdom.sub(
            lambda m: f"{m['i']}${m['v']} = app(ResolveKingdom::class)->handle({m['n'].strip()});",
            text,
        )

    player = re.compile(
        r"(?P<i>^[ \t]*)\$(?P<v>\w+)\s*=\s*Player::query\(\)->create\(\[\s*"
        r"(?P<body>.*?)^[ \t]*\]\);",
        re.M | re.S,
    )

    changed = False

    def replace(match: re.Match[str]) -> str:
        nonlocal changed
        pairs = {
            key: value.strip()
            for key, value in re.findall(r"'(\w+)'\s*=>\s*([^,\n]+)", match["body"])
        }
        required = {"current_kingdom_id", "game_player_id", "current_name"}
        if not required.issubset(pairs) or not set(pairs).issubset(required | {"user_id"}):
            return match.group(0)
        kid = pairs["current_kingdom_id"]
        if not kid.endswith("->id"):
            return match.group(0)

        i, var = match["i"], match["v"]
        result = (
            f"{i}${var} = app(PersistPlayerIdentity::class)->handle(\n"
            f"{i}    (string) {kid[:-4]}->id,\n"
            f"{i}    {pairs['current_name']},\n"
            f"{i}    {pairs['game_player_id']},\n"
            f"{i});"
        )
        uid = pairs.get("user_id")
        if uid not in (None, "null"):
            if not uid.endswith("->id"):
                return match.group(0)
            result += f"\n{i}${var} = app(ClaimPlayerAccount::class)->handle(${var}, {uid[:-4]});"
        changed = True
        return result

    text = player.sub(replace, text)
    if changed:
        text = ensure_use(text, r"App\Contexts\GameWorld\Actions\PersistPlayerIdentity")
        if "ClaimPlayerAccount::class" in text:
            text = ensure_use(text, r"App\Contexts\GameWorld\Actions\ClaimPlayerAccount")
    return text


def feature_dest(rel: Path) -> Path:
    p = rel.parts
    if len(p) == 1:
        return Path("Platform/Runtime/v2") / v2_name(p[0])
    root, name = p[0], p[-1]

    if root == "Accounts":
        base = Path("Accounts/Core/v2")
    elif root == "Alliance":
        base = Path("Alliance") / (p[1] if len(p) > 2 else "Core") / "v2"
    elif root == "Authorization":
        base = Path("GameWorld/Governance/v2")
    elif root == "Communications":
        base = Path("Communications/Reminders/v2")
    elif root == "Contributions":
        base = Path("Intelligence/Contributions/v2")
    elif root == "GameWorld":
        base = Path("GameWorld") / ("Governance" if len(p) > 2 and p[1] == "Governance" else "Core") / "v2"
    elif root == "Intelligence":
        base = Path("Intelligence") / (p[1] if len(p) > 2 else "Access") / "v2"
    elif root == "Operations":
        base = Path("Operations") / (p[1] if len(p) > 2 else "Access") / "v2"
    elif root == "Platform":
        base = Path("Platform") / ("Integrations" if len(p) > 2 and p[1] == "Integrations" else "Core") / "v2"
    elif root == "ReadModels":
        base = Path("ReadModels") / (p[1] if len(p) > 2 else "Core") / "v2"
    elif root == "Workflows":
        base = Path("Workflows") / (p[1] if len(p) > 2 else "Core") / "v2"
    elif root == "Kingdoms":
        if "Diplomacy" in name:
            base = Path("Intelligence/Diplomacy/v2")
        elif "SharedIntelligence" in name:
            base = Path("ReadModels/SharedKingdomIntelligence/v2")
        elif "AllianceIntelligence" in name:
            base = Path("ReadModels/KingdomIntelligence/v2")
        elif "IntelligenceSharing" in name:
            base = Path("Intelligence/Sharing/v2")
        elif "Observation" in name:
            base = Path("Intelligence/Observations/v2")
        elif "Tracking" in name:
            base = Path("Intelligence/Roster/v2")
        elif "Schema" in name:
            base = Path("Platform/Schema/v2")
        elif "Acceptance" in name or "Increment" in name:
            base = Path("GameWorld/Core/v2")
        else:
            base = Path("Intelligence/Observations/v2")
    else:
        raise SystemExit(f"Unclassified Feature test: {rel}")
    return base / v2_name(name)


def unit_dest(rel: Path) -> Path:
    p, name = rel.parts, rel.name
    if len(p) == 1:
        if name == "RuntimeConfigurationValidatorTest.php":
            return Path("Platform/Runtime/v2") / v2_name(name)
        raise SystemExit(f"Unclassified Unit test: {rel}")

    root = p[0]
    if root == "Accounts":
        base = Path("Accounts/Core/v2")
    elif root == "Alliance":
        base = Path("Alliance/Access/v2")
    elif root == "Alliances":
        base = Path("Alliance/Core/v2")
    elif root == "Contributions":
        base = Path("Intelligence/Contributions/v2")
    elif root == "Events":
        base = Path("Operations/Results/v2") if "Metric" in name else Path("Operations/EventCore/v2")
    elif root == "GameWorld":
        base = Path("GameWorld/Governance/v2")
    elif root == "KingPerks" or (root == "Operations" and len(p) > 2 and p[1] == "KingPerks"):
        base = Path("Operations/KingPerks/v2")
    elif root == "Kingdoms":
        base = Path("Intelligence/Roster/v2")
    else:
        raise SystemExit(f"Unclassified Unit test: {rel}")
    return base / v2_name(name)


def performance_dest(rel: Path) -> Path:
    bases = {
        "EventOperationsPerformanceTest.php": "Operations/EventCore/v2",
        "KingdomAllianceIntelligencePerformanceTest.php": "ReadModels/KingdomIntelligence/v2",
        "KingdomIngestionOperationsPerformanceTest.php": "Intelligence/Ingestion/v2",
        "KingdomRosterPerformanceTest.php": "Intelligence/Roster/v2",
        "KingdomSharedIntelligenceCapacityTest.php": "ReadModels/SharedKingdomIntelligence/v2",
        "KingdomTransferPerformanceTest.php": "Workflows/KingdomTransfer/v2",
        "PlatformCapacityTest.php": "Platform/Core/v2",
    }
    if rel.name not in bases:
        raise SystemExit(f"Unclassified Performance test: {rel}")
    return Path(bases[rel.name]) / v2_name(rel.name)


def isolation_dest(rel: Path) -> Path:
    bases = {
        "Alliance": "Alliance/Core/v2",
        "Content": "Alliance/Content/v2",
        "Contributions": "Intelligence/Contributions/v2",
        "Integrations": "Platform/Integrations/v2",
    }
    if rel.parts[0] not in bases:
        raise SystemExit(f"Unclassified TenantIsolation test: {rel}")
    return Path(bases[rel.parts[0]]) / v2_name(rel.name)


def integration_dest(rel: Path) -> Path | None:
    if "MigrationRollbackTest.php" in rel.name:
        return None
    if rel.name == "OutboxPublisherTest.php":
        return Path("Shared/Infrastructure/Messaging/Outbox/v2/OutboxPublisherV2Test.php")
    raise SystemExit(f"Unclassified Integration test: {rel}")


def rewrite_php(src: Path, dst: Path, event_analysis: bool = False) -> None:
    text = src.read_text()
    ns = "Tests\\" + "\\".join(dst.parent.relative_to(TESTS).parts)
    if NS_RE.search(text) is None:
        raise SystemExit(f"Missing namespace: {src.relative_to(ROOT)}")
    text = NS_RE.sub(f"namespace {ns};", text, count=1)

    def rename(m: re.Match[str]) -> str:
        old = m["name"]
        return m["prefix"] + (old if old.endswith("V2Test") else old[:-4] + "V2Test")

    text, count = CLASS_RE.subn(rename, text, count=1)
    if count != 1:
        raise SystemExit(f"Expected one test class: {src.relative_to(ROOT)}")

    if event_analysis:
        for old, new in EVENT_REPLACEMENTS.items():
            text = text.replace(old, new)
        role = "$roles = $this->app->make(KingdomRoleProvisioner::class)->provision($kingdom);"
        op = "$this->app->make(KingdomOperationsRoleProvisioner::class)->provision($kingdom);"
        if role in text and op not in text:
            text = text.replace(
                r"use App\Contexts\GameWorld\Governance\Services\KingdomRoleProvisioner;",
                r"use App\Contexts\GameWorld\Governance\Services\KingdomRoleProvisioner;"
                "\n"
                r"use App\Contexts\Operations\Access\Services\KingdomOperationsRoleProvisioner;",
            )
            text = text.replace(role, role + "\n        " + op)

    text = rewrite_identity_fixtures(text)
    dst.parent.mkdir(parents=True, exist_ok=True)
    if dst.exists() and dst.resolve() != src.resolve():
        raise SystemExit(f"Destination exists: {dst.relative_to(ROOT)}")
    dst.write_text(text)
    if src.resolve() != dst.resolve():
        src.unlink()


def rewrite_php_suites() -> None:
    for src in sorted(TESTS.rglob("*Test.php")):
        rel = src.relative_to(TESTS)
        if rel == Path("TestCase.php") or "Support" in rel.parts or "v2" in rel.parts:
            continue
        if rel == Path("Unit/ExampleTest.php") or "MigrationRollbackTest.php" in src.name:
            src.unlink()
            continue

        root = rel.parts[0]
        tail = Path(*rel.parts[1:])
        if root == "Architecture":
            if "Concerns" in rel.parts:
                continue
            dst_rel = Path("Architecture/v2") / v2_name(src.name)
        elif root == "Feature":
            dst_rel = Path("Feature") / feature_dest(tail)
        elif root == "Integration":
            mapped = integration_dest(tail)
            if mapped is None:
                src.unlink()
                continue
            dst_rel = Path("Integration") / mapped
        elif root == "Performance":
            dst_rel = Path("Performance") / performance_dest(tail)
        elif root == "TenantIsolation":
            dst_rel = Path("TenantIsolation") / isolation_dest(tail)
        elif root == "Unit":
            dst_rel = Path("Unit") / unit_dest(tail)
        elif root == "RewriteInput":
            if tail.parts[:2] != ("Intelligence", "EventAnalysis"):
                raise SystemExit(f"Unexpected RewriteInput: {rel}")
            dst_rel = Path("Feature/Intelligence/EventAnalysis/v2") / v2_name(src.name)
            rewrite_php(src, TESTS / dst_rel, event_analysis=True)
            continue
        else:
            raise SystemExit(f"Unclassified test root: {rel}")
        rewrite_php(src, TESTS / dst_rel)

    rewrite = TESTS / "RewriteInput"
    if rewrite.exists():
        for directory in sorted((p for p in rewrite.rglob("*") if p.is_dir()), reverse=True):
            try:
                directory.rmdir()
            except OSError:
                pass
        try:
            rewrite.rmdir()
        except OSError:
            remaining = [str(p.relative_to(ROOT)) for p in rewrite.rglob("*")]
            raise SystemExit(f"RewriteInput remains: {remaining}")


def rewrite_visual() -> None:
    src = TESTS / "Visual/ux-p9.spec.ts"
    dst = TESTS / "Visual/v2/ux-p9V2.spec.ts"
    if src.exists():
        dst.parent.mkdir(parents=True, exist_ok=True)
        text = src.read_text().replace(
            "`${route.name} English baseline`",
            "`${route.name} English baseline V2`",
        )
        for title in (
            "home Arabic RTL baseline",
            "login Arabic RTL baseline",
            "authenticated application shell English baseline",
            "authenticated application shell Arabic RTL baseline",
            "keyboard skip link reaches the main application content",
        ):
            text = text.replace(f"test('{title}'", f"test('{title} V2'")
        dst.write_text(text)
        src.unlink()

    old = TESTS / "Visual/__screenshots__/ux-p9.spec.ts"
    new = TESTS / "Visual/__screenshots__/v2/ux-p9V2.spec.ts"
    if old.exists():
        new.parent.mkdir(parents=True, exist_ok=True)
        if new.exists():
            raise SystemExit(f"Snapshot destination exists: {new}")
        shutil.move(str(old), str(new))


def rewrite_visual_workflow() -> None:
    workflow = ROOT / ".github/workflows/visual-regression.yml"
    text = workflow.read_text()
    old = r"App\\Domain\\Identity\\Models\\User"
    new = r"App\\Contexts\\Accounts\\Models\\User"
    if old in text:
        workflow.write_text(text.replace(old, new))


def assert_structure() -> None:
    failures: list[str] = []
    for path in sorted(TESTS.rglob("*Test.php")):
        rel = path.relative_to(TESTS)
        if rel == Path("TestCase.php") or "Support" in rel.parts or "Concerns" in rel.parts:
            continue
        if "v2" not in rel.parts:
            failures.append(f"PHP test outside v2: {rel}")
        if not path.name.endswith("V2Test.php"):
            failures.append(f"PHP test missing V2 suffix: {rel}")
        text = path.read_text()
        if "App\\Domain\\" in text:
            failures.append(f"V1 namespace remains: {rel}")
        if "Player::query()->create(" in text:
            failures.append(f"Direct Player fixture remains: {rel}")
        if "Kingdom::query()->create(" in text:
            failures.append(f"Direct Kingdom fixture remains: {rel}")

    for path in sorted((TESTS / "Visual").rglob("*.spec.ts")):
        rel = path.relative_to(TESTS)
        if "v2" not in rel.parts or not path.name.endswith("V2.spec.ts"):
            failures.append(f"Visual test violates V2 naming: {rel}")

    if (TESTS / "RewriteInput").exists():
        failures.append("tests/RewriteInput still exists")
    if failures:
        raise SystemExit("\n".join(failures))


rewrite_php_suites()
rewrite_visual()
rewrite_visual_workflow()
assert_structure()
print("ARCH-V2-TESTS: full test tree rewritten into capability-owned v2 suites.")
