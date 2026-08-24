#!/usr/bin/env python3
"""Pin and reconcile first-party/secondary progression reference pages."""
from __future__ import annotations

import json
import re
from pathlib import Path
from typing import Any

from bs4 import BeautifulSoup, Tag

import refresh

OFFICIAL_GOVERNOR_GEAR = "https://kingshotwiki.com/governor-gear/governor-gear/"
KR_GOVERNOR_CHARM = "https://kingshotdata.kr/en/database/governor-charm.html"
KR_WIDGETS = "https://kingshotdata.kr/en/database/widgets.html"


def load(path: Path) -> dict[str, Any]:
    return json.loads(path.read_text(encoding="utf-8"))


def write(path: Path, value: Any) -> None:
    path.write_text(json.dumps(value, ensure_ascii=False, separators=(",", ":")) + "\n", encoding="utf-8")


def integer(value: str) -> int:
    normalized = re.sub(r"[^0-9-]", "", value)
    return 0 if normalized in {"", "-"} else int(normalized)


def percentage(value: str) -> float:
    match = re.search(r"-?\d+(?:\.\d+)?", value.replace(",", ""))
    if match is None:
        raise RuntimeError(f"Expected percentage value; got {value!r}")
    return float(match.group(0))


def numeric_tokens(value: str) -> list[int]:
    """Parse integer material counts with comma or dot thousands separators."""
    tokens = re.findall(r"\d+(?:[.,]\d+)*", value)
    return [int(token.replace(",", "").replace(".", "")) for token in tokens]


def table_by_headers(soup: BeautifulSoup, required: set[str]) -> list[dict[str, str]]:
    for table in soup.find_all("table"):
        if not isinstance(table, Tag):
            continue
        headers, rows = refresh.table_rows(table)
        if required <= set(headers):
            return rows
    raise RuntimeError(f"Could not find expected factual table with headers {sorted(required)}")


def corroborate_official_gear(release_dir: Path) -> dict[str, Any]:
    fetched = refresh.fetch(OFFICIAL_GOVERNOR_GEAR)
    soup = BeautifulSoup(fetched.body, "html.parser")
    page_text = " ".join(soup.stripped_strings)
    for required in (
        "Kingshot - Official Wiki",
        "Century Games",
        "Governor Gear unlocked at Town Center Lv. 22",
        "Governor Charm unlocked at Town Center Lv. 25",
    ):
        if required not in page_text:
            raise RuntimeError(f"Official Governor Gear evidence changed or disappeared: {required}")

    gear_rows = table_by_headers(soup, {"Tier", "Stars", "Materials", "Stat Total", "Power Total"})
    charm_rows = table_by_headers(soup, {"Level", "Materials", "Stat Total", "Power Total"})
    if len(gear_rows) != 58 or len(charm_rows) != 22:
        raise RuntimeError(
            f"Official Governor tables changed: Gear={len(gear_rows)} rows, Charms={len(charm_rows)} rows"
        )

    gear = load(release_dir / "governor_gear.json")
    steps = gear.get("data", {}).get("upgradeSteps") if isinstance(gear.get("data"), dict) else None
    if not isinstance(steps, list) or len(steps) != 58:
        raise RuntimeError("Canonical Governor Gear ladder is not the expected 58-step release.")

    superseded_claims: list[dict[str, Any]] = []
    for index, (official, canonical) in enumerate(zip(gear_rows, steps, strict=True), start=1):
        if not isinstance(canonical, dict):
            raise RuntimeError(f"Canonical Governor Gear row {index} is invalid.")
        tier = official["Tier"].strip()
        stars = integer(official["Stars"])
        if tier != str(canonical.get("tier", "")).strip():
            raise RuntimeError(f"Official Governor Gear tier identity mismatch at row {index}: {official!r}")
        if stars != int(canonical.get("stars", -1)):
            raise RuntimeError(f"Official Governor Gear star identity mismatch at row {index}: {official!r}")

        official_materials = numeric_tokens(official["Materials"])
        if len(official_materials) != 3:
            raise RuntimeError(f"Official Governor Gear material shape changed at row {index}: {official!r}")
        materials = canonical.get("materials") if isinstance(canonical.get("materials"), dict) else {}
        prior_materials = [
            int(materials.get("satin", 0)),
            int(materials.get("gilded_threads", 0)),
            int(materials.get("artisans_vision", 0)),
        ]
        bonuses = canonical.get("bonuses") if isinstance(canonical.get("bonuses"), dict) else {}
        prior_stat = percentage(str(bonuses.get("attack", "")))
        prior_power = int(canonical.get("power_total", -1))
        official_stat = percentage(official["Stat Total"])
        official_power = integer(official["Power Total"])

        if prior_materials != official_materials or abs(prior_stat - official_stat) > 0.001 or prior_power != official_power:
            superseded_claims.append({
                "row": index,
                "tier": tier,
                "stars": stars,
                "source_id": "kingshotpro-open-data",
                "materials": {
                    "satin": prior_materials[0],
                    "gilded_threads": prior_materials[1],
                    "artisans_vision": prior_materials[2],
                },
                "stat_total_pct": prior_stat,
                "power_total": prior_power,
                "resolution_status": "superseded_by_tier_a",
            })

        canonical["materials"] = {
            "satin": official_materials[0],
            "gilded_threads": official_materials[1],
            "artisans_vision": official_materials[2],
        }
        canonical_bonuses = canonical.get("bonuses") if isinstance(canonical.get("bonuses"), dict) else {}
        canonical_bonuses["attack"] = f"{official_stat:.2f}%"
        canonical_bonuses["defense"] = f"{official_stat:.2f}%"
        canonical["bonuses"] = canonical_bonuses
        canonical["power_total"] = official_power
        canonical["evidence_status"] = "official"
        source_ids = canonical.get("source_ids") if isinstance(canonical.get("source_ids"), list) else []
        canonical["source_ids"] = list(dict.fromkeys([*source_ids, "kingshot-official-wiki"]))
        canonical["official_source_url"] = OFFICIAL_GOVERNOR_GEAR

    charms = load(release_dir / "governor_charms.json")
    levels = charms.get("data", {}).get("charmLevels") if isinstance(charms.get("data"), dict) else None
    if not isinstance(levels, list) or len(levels) != 22:
        raise RuntimeError("Canonical Governor Charm ladder is not the expected 22-level release.")
    cumulative_stat = 0.0
    cumulative_power = 0
    for official, canonical in zip(charm_rows, levels, strict=True):
        if not isinstance(canonical, dict):
            raise RuntimeError("Canonical Governor Charm row is invalid.")
        level = int(canonical["level"])
        if integer(official["Level"]) != level:
            raise RuntimeError(f"Official Governor Charm level mismatch at {level}")
        materials = numeric_tokens(official["Materials"])
        expected_materials = [int(canonical["charmGuides"]), int(canonical["charmDesigns"])]
        if materials != expected_materials:
            raise RuntimeError(
                f"Official Governor Charm material mismatch at level {level}: "
                f"official={materials!r}, canonical={expected_materials!r}"
            )
        cumulative_stat += float(canonical["statIncreasePct"])
        cumulative_power += int(canonical["powerGained"])
        if abs(percentage(official["Stat Total"]) - cumulative_stat) > 0.001:
            raise RuntimeError(f"Official Governor Charm stat mismatch at level {level}")
        if integer(official["Power Total"]) != cumulative_power:
            raise RuntimeError(f"Official Governor Charm cumulative power mismatch at level {level}")
        canonical["evidence_status"] = "official"
        source_ids = canonical.get("source_ids") if isinstance(canonical.get("source_ids"), list) else []
        canonical["source_ids"] = list(dict.fromkeys([*source_ids, "kingshot-official-wiki"]))

    gear.setdefault("source_meta", {})["official_corroboration"] = {
        "source_id": "kingshot-official-wiki",
        "url": OFFICIAL_GOVERNOR_GEAR,
        "rows_matched": 58,
        "scope": "tier, stars, materials, cumulative stat and cumulative power",
        "canonical_precedence": "tier_a",
        "superseded_claim_count": len(superseded_claims),
        "superseded_claims": superseded_claims,
    }
    charms.setdefault("source_meta", {})["official_corroboration"] = {
        "source_id": "kingshot-official-wiki",
        "url": OFFICIAL_GOVERNOR_GEAR,
        "rows_matched": 22,
        "scope": "level, materials, cumulative stat and cumulative power",
        "canonical_precedence": "tier_a",
    }
    write(release_dir / "governor_gear.json", gear)
    write(release_dir / "governor_charms.json", charms)

    return {
        "source_id": "kingshot-official-wiki",
        "dataset": "governor_gear_and_charms_official",
        "url": OFFICIAL_GOVERNOR_GEAR,
        "sha256": fetched.sha256,
        "kind": "first_party_reconciliation",
        "governor_gear_rows": 58,
        "governor_charm_rows": 22,
        "canonical_precedence": "tier_a",
        "superseded_claim_count": len(superseded_claims),
    }


def corroborate_kr_charm(release_dir: Path) -> dict[str, Any]:
    fetched = refresh.fetch(KR_GOVERNOR_CHARM)
    soup = BeautifulSoup(fetched.body, "html.parser")
    rows = table_by_headers(soup, {"Level", "Charm Guide", "Charm Design", "Total", "Power"})
    if len(rows) != 22:
        raise RuntimeError(f"Kingshot Data KR Governor Charm expected 22 rows; got {len(rows)}")
    charms = load(release_dir / "governor_charms.json")
    levels = charms.get("data", {}).get("charmLevels") if isinstance(charms.get("data"), dict) else None
    if not isinstance(levels, list) or len(levels) != 22:
        raise RuntimeError("Canonical Governor Charm ladder is incomplete.")
    cumulative_stat = 0.0
    for row, canonical in zip(rows, levels, strict=True):
        if not isinstance(canonical, dict):
            raise RuntimeError("Canonical Governor Charm row is invalid.")
        level = int(canonical["level"])
        cumulative_stat += float(canonical["statIncreasePct"])
        checks = (
            (integer(row["Level"]), level, "level"),
            (integer(row["Charm Guide"]), int(canonical["charmGuides"]), "Charm Guide"),
            (integer(row["Charm Design"]), int(canonical["charmDesigns"]), "Charm Design"),
            (integer(row["Power"]), int(canonical["powerGained"]), "power gain"),
        )
        for actual, expected, label in checks:
            if actual != expected:
                raise RuntimeError(f"KR Governor Charm {label} mismatch at level {level}: {actual} != {expected}")
        if abs(percentage(row["Total"]) - cumulative_stat) > 0.001:
            raise RuntimeError(f"KR Governor Charm total-stat mismatch at level {level}")

    corroboration = charms.setdefault("source_meta", {}).setdefault("corroboration", [])
    if not any(isinstance(row, dict) and row.get("source_id") == "kingshotdata-kr" for row in corroboration):
        corroboration.append({
            "source_id": "kingshotdata-kr",
            "url": KR_GOVERNOR_CHARM,
            "rows_matched": 22,
            "scope": "level, materials, cumulative stat and per-upgrade power",
        })
    write(release_dir / "governor_charms.json", charms)
    return {
        "source_id": "kingshotdata-kr",
        "dataset": "governor_charm_reference",
        "url": KR_GOVERNOR_CHARM,
        "sha256": fetched.sha256,
        "kind": "factual_detail_page",
        "visible_level_rows": 22,
    }


def corroborate_kr_widgets() -> dict[str, Any]:
    fetched = refresh.fetch(KR_WIDGETS)
    soup = BeautifulSoup(fetched.body, "html.parser")
    rows = table_by_headers(soup, {"Exclusive Hero Gear Level", "Widgets Required"})
    level_rows = [row for row in rows if row["Exclusive Hero Gear Level"].strip().isdigit()]
    if len(level_rows) != 10:
        raise RuntimeError(f"Kingshot Data KR Widgets expected 10 level rows; got {len(level_rows)}")
    actual = [integer(row["Widgets Required"]) for row in level_rows]
    if actual != list(range(5, 51, 5)):
        raise RuntimeError(f"Widget requirement ladder changed: {actual!r}")
    total = [row for row in rows if row["Exclusive Hero Gear Level"].strip().lower() == "total"]
    if len(total) != 1 or integer(total[0]["Widgets Required"]) != 275:
        raise RuntimeError("Widget total changed from the reviewed 275 requirement.")
    return {
        "source_id": "kingshotdata-kr",
        "dataset": "widgets_reference",
        "url": KR_WIDGETS,
        "sha256": fetched.sha256,
        "kind": "factual_detail_page",
        "visible_level_rows": 10,
        "total_widgets": 275,
    }


def update_release(release_dir: Path, snapshots: list[dict[str, Any]]) -> None:
    path = release_dir / "release.json"
    release = load(path)
    conflicts = release.get("conflicts")
    if not isinstance(conflicts, list):
        raise RuntimeError("Release conflicts are unavailable.")
    charm_conflict = next(
        (row for row in conflicts if isinstance(row, dict) and row.get("id") == "governor-charm-max-level"),
        None,
    )
    if not isinstance(charm_conflict, dict) or not isinstance(charm_conflict.get("claims"), list):
        raise RuntimeError("Expected Governor Charm historical max-level conflict is missing.")
    if not any(
        isinstance(row, dict) and row.get("source_id") == "kingshot-official-wiki"
        for row in charm_conflict["claims"]
    ):
        charm_conflict["claims"].append({"source_id": "kingshot-official-wiki", "value": 22, "unit": "level"})
    charm_conflict["resolution_status"] = "resolved_by_tier_a"
    charm_conflict["resolution"] = (
        "Resolved for this release by the Century Games official wiki and complete 22-level open ladders. "
        "The older Kingshot Data 21-level claim remains recorded as superseded historical evidence; calculator eligibility remains separately gated."
    )

    gear = load(release_dir / "governor_gear.json")
    official_meta = gear.get("source_meta", {}).get("official_corroboration") if isinstance(gear.get("source_meta"), dict) else None
    superseded_count = int(official_meta.get("superseded_claim_count", 0)) if isinstance(official_meta, dict) else 0
    conflicts[:] = [
        row for row in conflicts
        if not (isinstance(row, dict) and row.get("id") == "governor-gear-community-official-values")
    ]
    if superseded_count > 0:
        conflicts.append({
            "id": "governor-gear-community-official-values",
            "family": "governor_gear",
            "description": "The imported open community Governor Gear ladder disagreed with the current Century Games official wiki on one or more row values.",
            "claims": [
                {"source_id": "kingshotpro-open-data", "scope": "prior imported rows retained in governor_gear source metadata"},
                {"source_id": "kingshot-official-wiki", "scope": "current 58-row official Governor Gear table"},
            ],
            "resolution_status": "resolved_by_tier_a",
            "resolution": "The official Century Games table is canonical for this release; every displaced community row is retained as a superseded claim in governor_gear.json. Calculator eligibility remains separately gated.",
            "superseded_claim_count": superseded_count,
        })

    dispositions = release.get("family_dispositions")
    if not isinstance(dispositions, list):
        raise RuntimeError("Release family dispositions are unavailable.")
    for row in dispositions:
        if not isinstance(row, dict):
            continue
        if row.get("family") == "governor_gear":
            row["reason"] = (
                "All 58 official per-step Gear rows are canonicalized from the Century Games wiki for tier, stars, materials, cumulative stat and cumulative power. Prior differing open-community rows remain explicit superseded claims with source provenance."
            )
        elif row.get("family") == "governor_charms":
            row["reason"] = (
                "All 22 open Charm levels are canonicalized and reconciled against both the Century Games official wiki and maintained Kingshot Data KR table; the older 21-level claim remains superseded history."
            )

    source_rows = release.get("sources")
    if not isinstance(source_rows, list):
        raise RuntimeError("Release source registry is unavailable.")
    by_source = {
        str(row.get("id")): row
        for row in source_rows
        if isinstance(row, dict) and isinstance(row.get("id"), str)
    }
    official = by_source.get("kingshot-official-wiki")
    kr = by_source.get("kingshotdata-kr")
    if not isinstance(official, dict) or not isinstance(kr, dict):
        raise RuntimeError("Expected reconciliation sources are not registered.")

    official_snapshot = next(
        (row for row in snapshots if row.get("source_id") == "kingshot-official-wiki"),
        None,
    )
    kr_snapshots = [row for row in snapshots if row.get("source_id") == "kingshotdata-kr"]
    if not isinstance(official_snapshot, dict) or len(kr_snapshots) != 2:
        raise RuntimeError("Expected reconciliation source snapshots are incomplete.")

    official["content_snapshots"] = [{
        "url": official_snapshot["url"],
        "sha256": official_snapshot["sha256"],
        "scope": "58 Governor Gear rows and 22 Governor Charm rows used for Tier-A reconciliation",
    }]
    kr["content_snapshots"] = [
        {
            "url": row["url"],
            "sha256": row["sha256"],
            "scope": row["dataset"],
        }
        for row in sorted(kr_snapshots, key=lambda value: str(value["url"]))
    ]
    write(path, release)


def corroborate_release(release_dir: Path) -> None:
    snapshots = [
        corroborate_official_gear(release_dir),
        corroborate_kr_charm(release_dir),
        corroborate_kr_widgets(),
    ]
    update_release(release_dir, snapshots)
    print("Canonicalized Governor Gear from Tier-A official tables and pinned official/KR snapshots in the release registry.")


if __name__ == "__main__":
    target = Path(__file__).resolve().parents[2] / "resources" / "data" / "progression" / "kingshot-2026-08-23-v2"
    corroborate_release(target)
