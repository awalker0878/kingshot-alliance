#!/usr/bin/env python3
"""Verify the checked-in immutable factual progression candidate."""
from __future__ import annotations

import json
import re
from pathlib import Path
from typing import Any

RELEASE_DIR = Path(__file__).resolve().parents[2] / "resources" / "data" / "progression" / "kingshot-2026-08-23-v2"
BLOCKED_ADVISORY_KEYS = {
    "tier_list",
    "tierlist",
    "recommended",
    "recommendation",
    "recommendations",
    "investment_priority",
    "upgrade_priority",
    "priority_score",
    "optimizer",
    "optimization",
    "best_use",
    "best_for",
    "f2p_rating",
    "value_rating",
    "longevity_rating",
}


def load(name: str) -> dict[str, Any]:
    return json.loads((RELEASE_DIR / name).read_text(encoding="utf-8"))


def reject_advisory_fields(value: Any, path: str = "root") -> None:
    if isinstance(value, dict):
        for key, child in value.items():
            if key.lower() in BLOCKED_ADVISORY_KEYS:
                raise RuntimeError(f"Advisory field leaked into canonical release: {path}.{key}")
            reject_advisory_fields(child, f"{path}.{key}")
    elif isinstance(value, list):
        for index, child in enumerate(value):
            reject_advisory_fields(child, f"{path}[{index}]")


def verify() -> None:
    files = sorted(RELEASE_DIR.glob("*.json"))
    if not files:
        raise RuntimeError("No generated progression JSON files found.")
    for path in files:
        reject_advisory_fields(json.loads(path.read_text(encoding="utf-8")), path.name)

    release = load("release.json")
    assert release["schema_version"] == 2
    assert release["dataset_version"] == "2026.08.23.2"
    assert release["review_status"] == "candidate_complete_source_corpus"
    assert release["source_inventory_document"] == "docs/product/factual-governor-progression-source-inventory.md"
    declared_files = sorted(release["files"])
    actual_files = sorted(path.name for path in files if path.name != "release.json")
    assert declared_files == actual_files, (declared_files, actual_files)
    assert "source-lock.json" in declared_files

    dispositions = {row["family"]: row for row in release["family_dispositions"]}
    assert all(row["status"] != "indexed_external_table" for row in dispositions.values())
    for required in (
        "heroes",
        "hero_skills",
        "hero_star_shards",
        "hero_xp",
        "hero_exclusive_equipment",
        "hero_gear",
        "governor_gear",
        "governor_charms",
        "formations",
        "buildings",
        "troops",
        "academy_research",
        "war_academy",
        "alliance_tech",
        "pets",
        "masters",
        "truegold",
        "vip",
        "kvk_scoring",
        "database_reference_tables",
        "progression_event_tables",
    ):
        assert required in dispositions, f"Missing disposition: {required}"

    assert dispositions["hero_skills"]["canonical_entities"] == 34
    assert dispositions["hero_skills"]["discovered_entities"] == 34
    assert dispositions["hero_skills"]["facts_imported"] >= 262
    assert dispositions["hero_star_shards"]["canonical_entities"] == 34
    assert dispositions["hero_star_shards"]["facts_imported"] == 1054
    assert dispositions["hero_exclusive_equipment"]["applicable_heroes"] == 22
    assert dispositions["hero_exclusive_equipment"]["facts_imported"] == 220
    assert dispositions["buildings"]["canonical_entities"] == 12
    assert dispositions["masters"]["canonical_entities"] == 6

    heroes = load("heroes.json")["heroes"]
    assert len(heroes) == 34
    assert all(isinstance(hero.get("skills"), list) and hero["skills"] for hero in heroes)
    for name in ("Triton", "Sophia", "Yang", "Charles", "Ava", "Wee & Woo"):
        hero = next(row for row in heroes if row["name"] == name)
        assert hero["skill_source_status"] == "maintained_source_inspectable"
        assert len(hero["skills"]) == 8
        assert "kingshotdata" in hero["source_ids"]

    assert release["source_gaps"] == [
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

    academy = load("academy_research.json")
    assert len(academy["technologies"]) == 191
    assert academy["declared_levels"] == 714
    assert sum(len(technology["levels"]) for technology in academy["technologies"]) == 714
    assert academy["declared_max_level_sum"] == 720
    fortified = next(technology for technology in academy["technologies"] if technology["name"] == "Fortified Mail VI")
    assert fortified["max_level"] == 6
    assert fortified["levels_status"] == "source_table_missing"
    assert fortified["levels"] == []

    alliance = load("alliance_tech_tables.json")
    assert len(alliance["technologies"]) == 60
    assert sum(len(technology["levels"]) for technology in alliance["technologies"]) == 279
    assert alliance["trees"] == [
        {"name": "Growth", "technologies": 24, "levels": 108},
        {"name": "Territory", "technologies": 16, "levels": 80},
        {"name": "Battle", "technologies": 20, "levels": 91},
    ]

    expected_pages = {
        "buildings_tables.json": 12,
        "pets_tables.json": 14,
        "masters_tables.json": 6,
        "heroes_tables.json": 34,
        "database_tables.json": 8,
        "events_tables.json": 33,
    }
    for filename, expected in expected_pages.items():
        data = load(filename)
        assert data["discovered_pages"] == expected
        assert len(data["pages"]) == expected

    hero_tables = load("heroes_tables.json")
    star_rows = 0
    weapon_rows = 0
    weapon_heroes = 0
    for page in hero_tables["pages"]:
        stats = next(table for table in page["tables"] if table["heading"] == "Stats progression")
        assert len(stats["rows"]) == 31
        star_rows += len(stats["rows"])
        weapons = [table for table in page["tables"] if table["heading"] == "Exclusive Weapon"]
        if weapons:
            assert len(weapons) == 1 and len(weapons[0]["rows"]) == 10
            weapon_heroes += 1
            weapon_rows += 10
    assert star_rows == 1054
    assert weapon_heroes == 22
    assert weapon_rows == 220

    gear = load("governor_gear.json")
    assert gear["source_meta"]["count"] == 58
    war = load("war_academy.json")
    assert war["source_meta"]["count"] == 30

    source_lock = load("source-lock.json")
    assert source_lock["sources"]
    for row in source_lock["sources"]:
        assert re.fullmatch(r"[a-f0-9]{64}", row["sha256"]), row

    registered_sources = {row["id"] for row in release["sources"]}
    assert {"kingshotdata", "kingshotpro-open-data", "g2384-kingshot-data"} <= registered_sources

    print(
        f"Validated {len(files)} generated JSON files: 34/34 Hero skills, 1,054 Hero star rows, "
        "191 Academy techs/714 visible rows, 60 Alliance Tech techs/279 rows, complete source sweeps, "
        "and one explicit Academy source gap."
    )


if __name__ == "__main__":
    verify()
