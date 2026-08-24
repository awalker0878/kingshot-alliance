#!/usr/bin/env python3
"""Replace volatile raw-page hashes with deterministic factual-table snapshot hashes."""
from __future__ import annotations

import hashlib
import json
from pathlib import Path
from typing import Any

from bs4 import BeautifulSoup

import corroborate_reference_sources as refs
import refresh


def load(path: Path) -> dict[str, Any]:
    return json.loads(path.read_text(encoding="utf-8"))


def write(path: Path, value: Any) -> None:
    path.write_text(json.dumps(value, ensure_ascii=False, separators=(",", ":")) + "\n", encoding="utf-8")


def semantic_sha256(value: Any) -> str:
    payload = json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode("utf-8")
    return hashlib.sha256(payload).hexdigest()


def official_snapshot() -> dict[str, Any]:
    fetched = refresh.fetch(refs.OFFICIAL_GOVERNOR_GEAR)
    soup = BeautifulSoup(fetched.body, "html.parser")
    gear_rows = refs.table_by_headers(soup, {"Tier", "Stars", "Materials", "Stat Total", "Power Total"})
    charm_rows = refs.table_by_headers(soup, {"Level", "Materials", "Stat Total", "Power Total"})
    if len(gear_rows) != 58 or len(charm_rows) != 22:
        raise RuntimeError("Official Governor factual table coverage changed while stabilizing source snapshots.")

    normalized_gear = [
        {
            "tier": row["Tier"].strip(),
            "stars": refs.integer(row["Stars"]),
            "materials": refs.numeric_tokens(row["Materials"]),
            "stat_total_pct": refs.percentage(row["Stat Total"]),
            "power_total": refs.integer(row["Power Total"]),
        }
        for row in gear_rows
    ]
    normalized_charms = [
        {
            "level": refs.integer(row["Level"]),
            "materials": refs.numeric_tokens(row["Materials"]),
            "stat_total_pct": refs.percentage(row["Stat Total"]),
            "power_total": refs.integer(row["Power Total"]),
        }
        for row in charm_rows
    ]
    return {
        "source_id": "kingshot-official-wiki",
        "url": refs.OFFICIAL_GOVERNOR_GEAR,
        "sha256": semantic_sha256({"gear": normalized_gear, "charms": normalized_charms}),
        "scope": "58 Governor Gear rows and 22 Governor Charm rows used for Tier-A reconciliation",
        "snapshot_kind": "normalized_factual_tables",
    }


def kr_charm_snapshot() -> dict[str, Any]:
    fetched = refresh.fetch(refs.KR_GOVERNOR_CHARM)
    soup = BeautifulSoup(fetched.body, "html.parser")
    rows = refs.table_by_headers(soup, {"Level", "Charm Guide", "Charm Design", "Total", "Power"})
    if len(rows) != 22:
        raise RuntimeError("Kingshot Data KR Governor Charm coverage changed while stabilizing source snapshots.")
    normalized = [
        {
            "level": refs.integer(row["Level"]),
            "charm_guide": refs.integer(row["Charm Guide"]),
            "charm_design": refs.integer(row["Charm Design"]),
            "total_stat_pct": refs.percentage(row["Total"]),
            "power": refs.integer(row["Power"]),
        }
        for row in rows
    ]
    return {
        "source_id": "kingshotdata-kr",
        "url": refs.KR_GOVERNOR_CHARM,
        "sha256": semantic_sha256(normalized),
        "scope": "governor_charm_reference",
        "snapshot_kind": "normalized_factual_table",
    }


def kr_widget_snapshot() -> dict[str, Any]:
    fetched = refresh.fetch(refs.KR_WIDGETS)
    soup = BeautifulSoup(fetched.body, "html.parser")
    rows = refs.table_by_headers(soup, {"Exclusive Hero Gear Level", "Widgets Required"})
    normalized = [
        {
            "level": row["Exclusive Hero Gear Level"].strip(),
            "widgets_required": refs.integer(row["Widgets Required"]),
        }
        for row in rows
    ]
    level_rows = [row for row in normalized if str(row["level"]).isdigit()]
    total_rows = [row for row in normalized if str(row["level"]).lower() == "total"]
    if len(level_rows) != 10 or len(total_rows) != 1 or total_rows[0]["widgets_required"] != 275:
        raise RuntimeError("Kingshot Data KR Widget coverage changed while stabilizing source snapshots.")
    return {
        "source_id": "kingshotdata-kr",
        "url": refs.KR_WIDGETS,
        "sha256": semantic_sha256(normalized),
        "scope": "widgets_reference",
        "snapshot_kind": "normalized_factual_table",
    }


def stabilize_release(release_dir: Path) -> None:
    release_path = release_dir / "release.json"
    release = load(release_path)
    sources = release.get("sources")
    if not isinstance(sources, list):
        raise RuntimeError("Release source registry is unavailable.")
    by_id = {
        str(row.get("id")): row
        for row in sources
        if isinstance(row, dict) and isinstance(row.get("id"), str)
    }
    official = by_id.get("kingshot-official-wiki")
    kr = by_id.get("kingshotdata-kr")
    if not isinstance(official, dict) or not isinstance(kr, dict):
        raise RuntimeError("Required reconciliation sources are not registered.")

    official["content_snapshots"] = [official_snapshot()]
    kr["content_snapshots"] = sorted(
        [kr_charm_snapshot(), kr_widget_snapshot()],
        key=lambda row: str(row["url"]),
    )
    write(release_path, release)
    print("Pinned dynamic official/KR references by normalized factual-table SHA-256.")


if __name__ == "__main__":
    target = Path(__file__).resolve().parents[2] / "resources" / "data" / "progression" / "kingshot-2026-08-23-v2"
    stabilize_release(target)
