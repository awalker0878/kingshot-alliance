#!/usr/bin/env python3
"""Build and finalize the reviewed 2026.08.23.2 factual progression candidate.

This layer applies release-wide policy and reviewed source supplements that do not belong in the
source-specific adapters: factual rank preservation, Gen 6-7 Hero skill completion, semantic
coverage counts, and final completeness assertions.
"""
from __future__ import annotations

import json
import sys
from pathlib import Path
from typing import Any

import build_v2
import refresh
import validate_prerequisites

TARGET_RELEASE = "kingshot-2026-08-23-v2"
SUPPLEMENT = Path(__file__).with_name("hero_skills_gen6_7.json")


def load(path: Path) -> dict[str, Any]:
    return json.loads(path.read_text(encoding="utf-8"))


def write(path: Path, value: Any) -> None:
    refresh.write_json(path, value)


def preserve_factual_rank_fields() -> None:
    # `ranking` is ambiguous: an opinion ranking is advisory, but a competition/Event rank is a
    # factual result field. Source adapters explicitly exclude recommendation/tier-list headings,
    # so the generic key-level filter must not erase factual rank data.
    refresh.BLOCKED_ADVISORY_KEYS.discard("ranking")
    refresh.BLOCKED_HEADING_WORDS = tuple(
        word for word in refresh.BLOCKED_HEADING_WORDS if word != "ranking"
    )


def complete_gen6_gen7_hero_skills(release_dir: Path) -> int:
    supplement = load(SUPPLEMENT)
    if supplement.get("source_id") != "kingshotdata" or not isinstance(supplement.get("heroes"), list):
        raise RuntimeError("Gen 6-7 Hero skill supplement has an invalid source contract.")

    heroes_doc = load(release_dir / "heroes.json")
    heroes = heroes_doc.get("heroes")
    if not isinstance(heroes, list):
        raise RuntimeError("Generated Hero catalogue is invalid.")

    by_name = {
        str(row.get("name")): row
        for row in supplement["heroes"]
        if isinstance(row, dict) and isinstance(row.get("name"), str)
    }
    expected = {"Triton", "Sophia", "Yang", "Charles", "Ava", "Wee & Woo"}
    if set(by_name) != expected:
        raise RuntimeError(f"Gen 6-7 Hero supplement coverage changed: {sorted(by_name)}")

    completed = 0
    for hero in heroes:
        if not isinstance(hero, dict) or hero.get("name") not in expected:
            continue
        source = by_name[str(hero["name"])]
        skills = source.get("skills")
        if not isinstance(skills, list) or len(skills) != 8:
            raise RuntimeError(f"Expected exactly 8 reviewed skills for {hero['name']}")
        if hero.get("skills") not in (None, []):
            raise RuntimeError(f"Refusing to overwrite existing structured skills for {hero['name']}")
        hero["skills"] = skills
        hero["skill_source_status"] = "maintained_source_inspectable"
        source_ids = hero.get("source_ids") if isinstance(hero.get("source_ids"), list) else []
        hero["source_ids"] = list(dict.fromkeys([*source_ids, "kingshotdata"]))
        hero["skill_source_url"] = source["source_url"]
        completed += 1

    if completed != 6:
        raise RuntimeError(f"Expected to complete six Gen 6-7 Hero skill records; completed {completed}")

    provenance = heroes_doc.get("provenance") if isinstance(heroes_doc.get("provenance"), list) else []
    provenance.append({
        "source_id": "kingshotdata",
        "scope": "Gen 6-7 structured skill names/effect ladders for Triton, Sophia, Yang, Charles, Ava and Wee & Woo; expressive descriptions omitted",
        "source_locked_by": "heroes_tables.json detail-page SHA-256 entries in source-lock.json",
        "observed_at": supplement.get("observed_at"),
    })
    heroes_doc["provenance"] = provenance
    write(release_dir / "heroes.json", heroes_doc)

    return sum(
        len(hero.get("skills", []))
        for hero in heroes
        if isinstance(hero, dict) and isinstance(hero.get("skills"), list)
    )


def reviewed_semantic_counts(release_dir: Path) -> dict[str, int]:
    hero_tables = load(release_dir / "heroes_tables.json")
    building_tables = load(release_dir / "buildings_tables.json")
    master_tables = load(release_dir / "masters_tables.json")

    pages = hero_tables.get("pages")
    if not isinstance(pages, list) or len(pages) != 34:
        raise RuntimeError("Hero detail-page sweep is incomplete.")

    star_rows = 0
    exclusive_weapon_rows = 0
    exclusive_weapon_heroes = 0
    for page in pages:
        if not isinstance(page, dict) or not isinstance(page.get("tables"), list):
            raise RuntimeError("Hero detail-page table payload is invalid.")
        stats = [table for table in page["tables"] if isinstance(table, dict) and table.get("heading") == "Stats progression"]
        if len(stats) != 1 or not isinstance(stats[0].get("rows"), list) or len(stats[0]["rows"]) != 31:
            raise RuntimeError(f"Hero star progression is incomplete for {page.get('name')}")
        star_rows += len(stats[0]["rows"])
        weapons = [table for table in page["tables"] if isinstance(table, dict) and table.get("heading") == "Exclusive Weapon"]
        if weapons:
            if len(weapons) != 1 or not isinstance(weapons[0].get("rows"), list) or len(weapons[0]["rows"]) != 10:
                raise RuntimeError(f"Hero exclusive weapon progression is incomplete for {page.get('name')}")
            exclusive_weapon_heroes += 1
            exclusive_weapon_rows += len(weapons[0]["rows"])

    if star_rows != 1054:
        raise RuntimeError(f"Expected 1,054 Hero star rows; got {star_rows}")
    if exclusive_weapon_heroes != 22 or exclusive_weapon_rows != 220:
        raise RuntimeError(
            f"Expected 22 Heroes / 220 visible Exclusive Weapon rows; got {exclusive_weapon_heroes}/{exclusive_weapon_rows}"
        )
    if building_tables.get("discovered_pages") != 12:
        raise RuntimeError("Building semantic entity count changed from reviewed cutoff.")
    if master_tables.get("discovered_pages") != 6:
        raise RuntimeError("Master semantic entity count changed from reviewed cutoff.")

    return {
        "hero_star_rows": star_rows,
        "exclusive_weapon_heroes": exclusive_weapon_heroes,
        "exclusive_weapon_rows": exclusive_weapon_rows,
        "buildings": 12,
        "masters": 6,
    }


def update_release_report(release_dir: Path, skill_count: int, counts: dict[str, int]) -> None:
    release_path = release_dir / "release.json"
    release = load(release_path)
    dispositions = release.get("family_dispositions")
    if not isinstance(dispositions, list):
        raise RuntimeError("Release dispositions are unavailable.")

    by_family = {
        str(row.get("family")): row
        for row in dispositions
        if isinstance(row, dict) and isinstance(row.get("family"), str)
    }

    hero_skills = by_family["hero_skills"]
    hero_skills.update({
        "status": "canonicalized",
        "discovered_entities": 34,
        "canonical_entities": 34,
        "facts_imported": skill_count,
        "reason": "All 34 current Heroes have structured skill ladders: the pinned inspectable GitHub dataset covers Gen 1-5 and maintained source-locked Hero pages complete Gen 6-7. Expressive guide prose and recommendations are excluded.",
    })

    star = by_family["hero_star_shards"]
    star.update({
        "status": "canonicalized",
        "discovered_entities": 34,
        "canonical_entities": 34,
        "facts_imported": counts["hero_star_rows"],
        "reason": "All 34 maintained Hero pages expose a 31-row star-stat/shard progression table (1,054 rows total); the open shard reference is retained as corroborating structured data.",
    })

    exclusive = by_family["hero_exclusive_equipment"]
    exclusive.update({
        "status": "canonicalized_with_explicit_non_applicability",
        "discovered_entities": 34,
        "canonical_entities": 34,
        "facts_imported": counts["exclusive_weapon_rows"],
        "applicable_heroes": counts["exclusive_weapon_heroes"],
        "reason": "All 34 Hero identities are classified; 22 Heroes expose complete 10-level Exclusive Weapon tables (220 rows) and non-exclusive Heroes remain explicitly non-applicable rather than missing.",
    })

    buildings = by_family["buildings"]
    buildings.update({
        "discovered_entities": counts["buildings"],
        "canonical_entities": counts["buildings"],
        "reason": "All 12 maintained Building entities are represented with their complete structured factual tables; open Building/Truegold feeds corroborate those entities rather than being double-counted as extra Buildings.",
    })

    masters = by_family["masters"]
    masters.update({
        "discovered_entities": counts["masters"],
        "canonical_entities": counts["masters"],
        "reason": "All 6 maintained Master entities are represented with every published structured Affinity/skill/material/research table; the open Master feed is corroborating data rather than additional game entities.",
    })

    release["review_status"] = "candidate_complete_source_corpus"
    release["release_notes"] = (
        "Complete release-cutoff factual corpus candidate. Open inspectable tables are canonicalized; all 34 Hero skill ladders and 1,054 Hero star rows are represented; "
        "Academy preserves one explicit Fortified Mail VI source-table gap; recommendation/optimizer semantics remain excluded and calculator eligibility remains separately gated."
    )
    write(release_path, release)


def validate_final_candidate(release_dir: Path) -> None:
    heroes = load(release_dir / "heroes.json").get("heroes")
    if not isinstance(heroes, list) or len(heroes) != 34:
        raise RuntimeError("Final Hero catalogue must contain 34 Heroes.")
    incomplete = [
        hero.get("name")
        for hero in heroes
        if not isinstance(hero, dict) or not isinstance(hero.get("skills"), list) or len(hero["skills"]) == 0
    ]
    if incomplete:
        raise RuntimeError(f"Final Hero skill coverage is incomplete: {incomplete}")

    release = load(release_dir / "release.json")
    dispositions = release.get("family_dispositions", [])
    if any(isinstance(row, dict) and row.get("status") == "indexed_external_table" for row in dispositions):
        raise RuntimeError("Final release still contains index-only reusable tables.")


def main() -> int:
    preserve_factual_rank_fields()
    code = build_v2.main()
    if code != 0:
        return code

    release_dir = refresh.PROGRESSION_ROOT / TARGET_RELEASE
    skill_count = complete_gen6_gen7_hero_skills(release_dir)
    counts = reviewed_semantic_counts(release_dir)
    update_release_report(release_dir, skill_count, counts)
    prerequisite_stats = validate_prerequisites.validate_release(release_dir)
    validate_final_candidate(release_dir)
    print(
        f"Finalized {TARGET_RELEASE}: 34/34 Heroes with skills ({skill_count} structured skills), "
        f"{counts['hero_star_rows']} Hero star rows, {counts['exclusive_weapon_rows']} Exclusive Weapon rows; "
        f"Academy prerequisite graph validated across {prerequisite_stats['level_nodes']} declared levels "
        f"with {prerequisite_stats['explicit_technology_edges']} explicit technology edges."
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
