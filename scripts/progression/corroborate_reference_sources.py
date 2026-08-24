#!/usr/bin/env python3
"""Pin and reconcile first-party/secondary progression reference pages.

These pages are already represented in the source registry. This step makes their use reproducible:
we hash the exact fetched HTML, validate the factual tables that are relied upon, and record
corroboration on the canonical release without copying expressive guide prose.
"""
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
    refresh.write_json(path, value)


def integer(value: str) -> int:
    normalized = re.sub(r"[^0-9-]", "", value)
    if normalized in {"", "-"}:
        return 0
    return int(normalized)


def percentage(value: str) -> float:
    match = re.search(r"-?\d+(?:\.\d+)?", value.replace(",", ""))
    if match is None:
        raise RuntimeError(f"Expected percentage value; got {value!r}")
    return float(match.group(0))


def table_by_headers(soup: BeautifulSoup, required: set[str]) -> tuple[list[str], list[dict[str, str]]]:
    for table in soup.find_all("table"):
        if not isinstance(table, Tag):
            continue
        headers, rows = refresh.table_rows(table)
        if required <= set(headers):
            return headers, rows
    raise RuntimeError(f"Could not find expected factual table with headers {sorted(required)}")


def corroborate_official_gear(release_dir: Path) -> dict[str, Any]:
    fetched = refresh.fetch(OFFICIAL_GOVERNOR_GEAR)
    soup = BeautifulSoup(fetched.body, "html.parser")
    page_text = " ".join(soup.stripped_strings)
    if "Kingshot - Official Wiki" not in page_text or "Century Games" not in page_text:
        raise RuntimeError("Governor Gear source no longer identifies itself as the Century Games official wiki.")
    if "Governor Gear unlocked at Town Center Lv. 22" not in page_text:
        raise RuntimeError("Official Governor Gear Town Center unlock fact changed or disappeared.")
    if "Governor Charm unlocked at Town Center Lv. 25" not in page_text:
        raise RuntimeError("Official Governor Charm Town Center unlock fact changed or disappeared.")

    _, gear_rows = table_by_headers(soup, {"Tier", "Stars", "Stat Total", "Power Total"})
    _, charm_rows = table_by_headers(soup, {"Level", "Stat Total", "Power Total"})
    if len(gear_rows) != 58:
        raise RuntimeError(f"Official Governor Gear table expected 58 rows; got {len(gear_rows)}")
    if len(charm_rows) != 22:
        raise RuntimeError(f"Official Governor Charm table expected 22 rows; got {len(charm_rows)}")

    gear = load(release_dir / "governor_gear.json")
    steps = gear.get("data", {}).get("upgradeSteps") if isinstance(gear.get("data"), dict) else None
    if not isinstance(steps, list) or len(steps) != 58:
        raise RuntimeError("Canonical Governor Gear ladder is not the expected 58-step release.")

    for index, (official, canonical) in enumerate(zip(gear_rows, steps, strict=True), start=1):
        if not isinstance(canonical, dict):
            raise RuntimeError(f"Canonical Governor Gear row {index} is invalid.")
        if str(official.get("Tier", "")).strip() != str(canonical.get("tier", "")).strip():
            raise RuntimeError(f"Official Governor Gear tier mismatch at row {index}: {official!r}")
        if integer(str(official.get("Stars", ""))) != int(canonical.get("stars", -1)):
            raise RuntimeError(f"Official Governor Gear star mismatch at row {index}: {official!r}")
        bonuses = canonical.get("bonuses") if isinstance(canonical.get("bonuses"), dict) else {}
        canonical_stat = percentage(str(bonuses.get("attack", "")))
        if abs(percentage(str(official.get("Stat Total", ""))) - canonical_stat) > 0.001:
            raise RuntimeError(f"Official Governor Gear stat mismatch at row {index}: {official!r}")
        if integer(str(official.get("Power Total", ""))) != int(canonical.get("power_total", -1)):
            raise RuntimeError(f"Official Governor Gear power mismatch at row {index}: {official!r}")

    charms = load(release_dir / "governor_charms.json")
    charm_levels = charms.get("data", {}).get("charmLevels") if isinstance(charms.get("data"), dict) else None
    if not isinstance(charm_levels, list) or len(charm_levels) != 22:
        raise RuntimeError("Canonical Governor Charm ladder is not the expected 22-level release.")

    cumulative_stat = 0.0
    cumulative_power = 0
    for official, canonical in zip(charm_rows, charm_levels, strict=True):
        if not isinstance(canonical, dict):
            raise RuntimeError("Canonical Governor Charm row is invalid.")
        level = int(canonical.get("level", -1))
        if integer(str(official.get("Level", ""))) != level:
            raise RuntimeError(f"Official Governor Charm level mismatch at level {level}.")
        cumulative_stat += float(canonical.get("statIncreasePct", 0))
        cumulative_power += int(canonical.get("powerGained", 0))
        if abs(percentage(str(official.get("Stat Total", ""))) - cumulative_stat) > 0.001:
            raise RuntimeError(f"Official Governor Charm stat mismatch at level {level}.")
        if integer(str(official.get("Power Total", ""))) != cumulative_power:
            raise RuntimeError(f"Official Governor Charm power mismatch at level {level}.")

    gear.setdefault("source_meta", {})["official_corroboration"] = {
        "source_id": "kingshot-official-wiki",
        "url": OFFICIAL_GOVERNOR_GEAR,
        "rows_matched": 58,
        "scope": "tier, stars, cumulative stat and cumulative power",
    }
    charms.setdefault("source_meta", {})["official_corroboration"] = {
        "source_id": "kingshot-official-wiki",
        "url": OFFICIAL_GOVERNOR_GEAR,
        "rows_matched": 22,
        "scope": "level, cumulative stat and cumulative power",
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
    }


def corroborate_kr_charm(release_dir: Path) -> dict[str, Any]:
    fetched = refresh.fetch(KR_GOVERNOR_CHARM)
    soup = BeautifulSoup(fetched.body, "html.parser")
    _, rows = table_by_headers(soup, {"Level", "Charm Guide", "Charm Design", "Total", "Power"})
    if len(rows) != 22:
        raise RuntimeError(f"Kingshot Data KR Governor Charm expected 22 rows; got {len(rows)}")

    charms = load(release_dir / "governor_charms.json")
    levels = charms.get("data", {}).get("charmLevels") if isinstance(charms.get("data"), dict) else None
    if not isinstance(levels, list) or len(levels) != 22:
        raise RuntimeError("Canonical Governor Charm ladder is incomplete.")

    cumulative_stat = 0.0
    cumulative_power = 0
    for row, canonical in zip(rows, levels, strict=True):
        if not isinstance(canonical, dict):
            raise RuntimeError("Canonical Governor Charm row is invalid.")
        level = int(canonical["level"])
        cumulative_stat += float(canonical["statIncreasePct"])
        cumulative_power += int(canonical["powerGained"])
        if integer(row["Level"]) != level:
            raise RuntimeError(f"KR Governor Charm level mismatch at {level}")
        if integer(row["Charm Guide"]) != int(canonical["charmGuides"]):
            raise RuntimeError(f"KR Governor Charm Guide mismatch at {level}")
        if integer(row["Charm Design"]) != int(canonical["charmDesigns"]):
            raise RuntimeError(f"KR Governor Charm Design mismatch at {level}")
        if abs(percentage(row["Total"]) - cumulative_stat) > 0.001:
            raise RuntimeError(f"KR Governor Charm total-stat mismatch at {level}")
        # KR exposes per-upgrade Power; the official wiki independently corroborates cumulative power.
        if integer(row["Power"]) != int(canonical["powerGained"]):
            raise RuntimeError(f"KR Governor Charm power-gain mismatch at {level}")

    meta = charms.setdefault("source_meta", {})
    corroboration = meta.setdefault("corroboration", [])
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
    _, rows = table_by_headers(soup, {"Exclusive Hero Gear Level", "Widgets Required"})
    level_rows = [row for row in rows if str(row.get("Exclusive Hero Gear Level", "")).strip().isdigit()]
    if len(level_rows) != 10:
        raise RuntimeError(f"Kingshot Data KR Widgets expected 10 level rows; got {len(level_rows)}")
    expected = list(range(5, 51, 5))
    actual = [integer(row["Widgets Required"]) for row in level_rows]
    if actual != expected:
        raise RuntimeError(f"Widget requirement ladder changed: {actual!r}")
    total_rows = [row for row in rows if str(row.get("Exclusive Hero Gear Level", "")).strip().lower() == "total"]
    if len(total_rows) != 1 or integer(total_rows[0]["Widgets Required"]) != 275:
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


def update_release(release_dir: Path) -> None:
    release = load(release_dir / "release.json")
    conflicts = release.get("conflicts")
    if not isinstance(conflicts, list):
        raise RuntimeError("Release conflicts are unavailable.")
    conflict = next(
        (row for row in conflicts if isinstance(row, dict) and row.get("id") == "governor-charm-max-level"),
        None,
    )
    if conflict is None:
        raise RuntimeError("Expected Governor Charm historical max-level conflict is missing.")
    claims = conflict.get("claims")
    if not isinstance(claims, list):
        raise RuntimeError("Governor Charm conflict claims are invalid.")
    if not any(isinstance(row, dict) and row.get("source_id") == "kingshot-official-wiki" for row in claims):
        claims.append({"source_id": "kingshot-official-wiki", "value": 22, "unit": "level"})
    conflict["resolution"] = (
        "Resolved for this release by the Century Games official wiki and the complete 22-level open ladders. "
        "The older Kingshot Data 21-level claim remains recorded as superseded historical evidence; calculator eligibility remains separately gated."
    )
    conflict["resolution_status"] = "resolved_by_tier_a"

    dispositions = release.get("family_dispositions")
    if not isinstance(dispositions, list):
        raise RuntimeError("Release family dispositions are unavailable.")
    for row in dispositions:
        if not isinstance(row, dict):
            continue
        if row.get("family") == "governor_gear":
            row["reason"] = (
                "All 58 open per-step rows are canonicalized with source confidence and are reconciled against the Century Games official wiki's 58-row Gear table for tier, stars, cumulative stat and cumulative power."
            )
        elif row.get("family") == "governor_charms":
            row["reason"] = (
                "All 22 open Charm levels are canonicalized and reconciled against both the Century Games official wiki and the maintained Kingshot Data KR 22-level table; the older 21-level claim remains superseded history."
            )
    write(release_dir / "release.json", release)


def update_source_lock(release_dir: Path, locks: list[dict[str, Any]]) -> None:
    path = release_dir / "source-lock.json"
    document = load(path)
    sources = document.get("sources")
    if not isinstance(sources, list):
        raise RuntimeError("Source lock document is invalid.")
    urls = {str(lock["url"]) for lock in locks}
    sources[:] = [row for row in sources if not (isinstance(row, dict) and str(row.get("url")) in urls)]
    sources.extend(locks)
    sources.sort(key=lambda row: (str(row.get("source_id", "")), str(row.get("dataset", "")), str(row.get("url", ""))))
    write(path, document)


def corroborate_release(release_dir: Path) -> None:
    official = corroborate_official_gear(release_dir)
    kr_charm = corroborate_kr_charm(release_dir)
    kr_widgets = corroborate_kr_widgets()
    update_release(release_dir)
    update_source_lock(release_dir, [official, kr_charm, kr_widgets])
    print(
        "Corroborated Governor Gear/Charms with Tier-A official tables and pinned Kingshot Data KR Charm/Widget references."
    )


if __name__ == "__main__":
    target = Path(__file__).resolve().parents[2] / "resources" / "data" / "progression" / "kingshot-2026-08-23-v2"
    corroborate_release(target)
