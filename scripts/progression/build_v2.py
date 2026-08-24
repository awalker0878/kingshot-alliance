#!/usr/bin/env python3
"""Build factual progression schema v2 with the verified Academy source structure."""
from __future__ import annotations

import json
import re
import sys
from collections import Counter
from pathlib import Path
from typing import Any

from bs4 import BeautifulSoup, Tag

import refresh


TREE_BOUNDARIES = (
    (45, "Development"),
    (89, "Economy"),
    (191, "Battle"),
)
EXPECTED_SOURCE_GAP = ("Fortified Mail VI", 6)


def tree_for_index(index: int) -> str:
    for upper, tree in TREE_BOUNDARIES:
        if index < upper:
            return tree
    raise RuntimeError(f"Academy technology index outside declared source coverage: {index}")


def parse_academy() -> tuple[dict[str, Any], list[dict[str, Any]]]:
    page = refresh.fetch(refresh.KSD_RESEARCH_URL)
    soup = BeautifulSoup(page.body, "html.parser")
    technologies: list[dict[str, Any]] = []
    name_counts: Counter[tuple[str, str]] = Counter()
    missing_tables: list[tuple[str, int]] = []

    for details in soup.find_all("details"):
        if not isinstance(details, Tag):
            continue
        summary = details.find("summary")
        if not isinstance(summary, Tag):
            continue
        summary_text = " ".join(summary.stripped_strings).strip()
        max_match = re.search(r"Max Level\s+(\d+)", summary_text, re.IGNORECASE)
        if max_match is None:
            continue

        strong = summary.find("strong")
        name = " ".join(strong.stripped_strings).strip() if isinstance(strong, Tag) else ""
        if name == "":
            raise RuntimeError(f"Academy technology is missing a name: {summary_text[:160]}")

        index = len(technologies)
        tree = tree_for_index(index)
        max_level = int(max_match.group(1))
        name_counts[(tree, name)] += 1
        occurrence = name_counts[(tree, name)]
        identifier = f"{refresh.slug(tree)}-{refresh.slug(name)}"
        if occurrence > 1:
            identifier += f"-{occurrence}"

        table = details.find("table")
        levels: list[dict[str, str]] = []
        levels_status = "complete_visible_table"
        if isinstance(table, Tag):
            headers, rows = refresh.table_rows(table)
            header_text = " ".join(headers).lower()
            if "research cost" not in header_text or "lv" not in header_text:
                raise RuntimeError(f"Academy table shape changed for {name}: {headers}")
            levels = rows
            if len(levels) != max_level:
                raise RuntimeError(
                    f"Academy table row count does not match declared max for {name}: "
                    f"{len(levels)} rows vs max {max_level}"
                )
        else:
            missing_tables.append((name, max_level))
            levels_status = "source_table_missing"

        technologies.append({
            "id": identifier,
            "name": name,
            "tree": tree,
            "occurrence": occurrence,
            "max_level": max_level,
            "levels_status": levels_status,
            "levels": levels,
            "source_url": refresh.KSD_RESEARCH_URL,
            "source_id": "kingshotdata",
        })

    level_count = sum(len(technology["levels"]) for technology in technologies)
    ids = [technology["id"] for technology in technologies]
    tree_counts = Counter(technology["tree"] for technology in technologies)

    if len(technologies) != 191:
        raise RuntimeError(f"Academy expected 191 technology identities; got {len(technologies)}")
    if level_count != 714:
        raise RuntimeError(f"Academy expected exactly 714 visible level rows; got {level_count}")
    if len(ids) != len(set(ids)):
        raise RuntimeError("Academy technology IDs are not unique")
    if missing_tables != [EXPECTED_SOURCE_GAP]:
        raise RuntimeError(f"Academy source-table gap changed: {missing_tables!r}")
    if tree_counts != Counter({"Battle": 102, "Development": 45, "Economy": 44}):
        raise RuntimeError(f"Academy tree coverage changed: {tree_counts!r}")

    return {
        "schema_version": 1,
        "source_id": "kingshotdata",
        "declared_technologies": 191,
        "visible_level_rows": 714,
        "declared_max_level_sum": 720,
        "source_table_gaps": [
            {
                "technology_id": "battle-fortified-mail-vi",
                "technology": "Fortified Mail VI",
                "declared_max_level": 6,
                "missing_visible_level_rows": 6,
                "status": "source_table_missing",
                "reason": "The maintained source publishes the technology identity and Max Level 6 but no per-level table. Values remain unknown.",
                "source_id": "kingshotdata",
            }
        ],
        "technologies": technologies,
    }, [{
        "url": refresh.KSD_RESEARCH_URL,
        "sha256": page.sha256,
        "kind": "academy_research",
        "technology_identities": 191,
        "visible_level_rows": 714,
        "source_table_gaps": 1,
    }]


def reconcile_release(target_release: str) -> None:
    release_dir = refresh.PROGRESSION_ROOT / target_release
    release_path = release_dir / "release.json"
    release = json.loads(release_path.read_text(encoding="utf-8"))

    for row in release.get("family_dispositions", []):
        if row.get("family") != "academy_research":
            continue
        row["facts_imported"] = 714
        row["unresolved_level_tables"] = 1
        row["reason"] = (
            "All 191 maintained Academy technology identities and all 714 visible per-level rows are imported. "
            "Fortified Mail VI declares Max Level 6 but publishes no level table; its six level values remain explicit unknowns."
        )

    release["source_gaps"] = [
        {
            "id": "academy-fortified-mail-vi-level-table",
            "family": "academy_research",
            "source_id": "kingshotdata",
            "entity": "Fortified Mail VI",
            "declared_max_level": 6,
            "missing_visible_level_rows": 6,
            "status": "source_table_missing",
            "resolution": "Do not infer the six rows. Retain the technology identity and wait for an inspectable source table or independently verified in-game evidence.",
        }
    ]
    refresh.write_json(release_path, release)


def main() -> int:
    source_release = "kingshot-2026-08-23-v1"
    target_release = "kingshot-2026-08-23-v2"
    refresh.academy_research = parse_academy
    refresh.build_release(source_release, target_release, "2026-08-23")
    reconcile_release(target_release)

    academy_path = refresh.PROGRESSION_ROOT / target_release / "academy_research.json"
    academy = json.loads(academy_path.read_text(encoding="utf-8"))
    assert len(academy["technologies"]) == 191
    assert sum(len(row["levels"]) for row in academy["technologies"]) == 714
    assert academy["source_table_gaps"][0]["technology"] == "Fortified Mail VI"
    print(f"Generated {target_release}: 191 Academy technologies, 714 visible level rows, 1 explicit source gap")
    return 0


if __name__ == "__main__":
    sys.exit(main())
