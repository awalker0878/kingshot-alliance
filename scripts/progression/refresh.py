#!/usr/bin/env python3
"""Build an immutable factual KingShot progression release from inspectable public sources.

This importer intentionally retains structured factual values/tables and source provenance while
stripping strategy, ranking and recommendation fields. It never fabricates missing values.
"""
from __future__ import annotations

import argparse
import copy
import hashlib
import json
import re
import shutil
import sys
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Iterable
from urllib.parse import urljoin, urlparse

import requests
from bs4 import BeautifulSoup, Tag

ROOT = Path(__file__).resolve().parents[2]
PROGRESSION_ROOT = ROOT / "resources" / "data" / "progression"
USER_AGENT = "kingshot-alliance-factual-progression/1.0 (+https://github.com/awalker0878/kingshot-alliance)"
TIMEOUT = 45

KSP_BASE = "https://kingshotpro.com/data/"
KSP_DATASETS = {
    "governor_gear": "gear.json",
    "buildings_core": "buildings.json",
    "masters_open": "masters.json",
    "troops": "troops.json",
    "truegold": "truegold.json",
    "war_academy": "war-academy.json",
    "governor_charms": "charm.json",
    "hero_xp": "hero-xp.json",
    "hero_shards": "shards.json",
}

G2384_HEROES_URL = (
    "https://raw.githubusercontent.com/g2384/Kingshot-Data/"
    "82f668b5fd1a77caca841a7149469aea51aead84/heroes2.json"
)
KSD_BASE = "https://kingshotdata.com/"
KSD_RESEARCH_URL = urljoin(KSD_BASE, "research/")
KSD_CATEGORY_PATHS = {
    "buildings_tables": "buildings/",
    "pets_tables": "pets/",
    "masters_tables": "masters/",
    "heroes_tables": "heroes/",
    "alliance_tech_tables": "alliance-tech/",
}

BLOCKED_STRATEGY_KEYS = {
    "tier", "tier_list", "tierlist", "rank", "ranking", "recommended", "recommendation",
    "recommendations", "priority", "best", "score", "rating", "rally", "garrison", "bear",
    "joiner", "longevity", "f2p", "vip_value", "investment_priority", "optimizer",
}
BLOCKED_HEADING_WORDS = ("tier list", "ranking", "priority", "recommended", "recommendation", "best heroes", "lineup")
FACT_TABLE_HINTS = (
    "level", "cost", "requirement", "requirements", "time", "power", "effect", "buff",
    "skill", "widget", "star", "material", "attack", "defense", "health", "lethality",
    "affinity", "talent", "research", "truegold", "upgrade", "training", "capacity", "stat",
)


@dataclass(frozen=True)
class FetchResult:
    url: str
    body: bytes
    content_type: str

    @property
    def sha256(self) -> str:
        return hashlib.sha256(self.body).hexdigest()


def fetch(url: str) -> FetchResult:
    response = requests.get(url, headers={"User-Agent": USER_AGENT}, timeout=TIMEOUT)
    response.raise_for_status()
    return FetchResult(url=url, body=response.content, content_type=response.headers.get("content-type", ""))


def fetch_json(url: str) -> tuple[dict[str, Any] | list[Any], FetchResult]:
    result = fetch(url)
    return json.loads(result.body.decode("utf-8")), result


def write_json(path: Path, value: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, ensure_ascii=False, indent=2, sort_keys=False) + "\n", encoding="utf-8")


def slug(value: str) -> str:
    value = re.sub(r"[^a-z0-9]+", "-", value.strip().lower()).strip("-")
    return value or "unknown"


def normalized_name(value: str) -> str:
    return re.sub(r"[^a-z0-9]", "", value.lower())


def strip_strategy(value: Any, *, parent_key: str | None = None) -> Any:
    if isinstance(value, dict):
        cleaned: dict[str, Any] = {}
        for key, child in value.items():
            key_lower = str(key).lower()
            if key_lower in BLOCKED_STRATEGY_KEYS:
                continue
            cleaned[str(key)] = strip_strategy(child, parent_key=key_lower)
        return cleaned
    if isinstance(value, list):
        return [strip_strategy(item, parent_key=parent_key) for item in value]
    return value


def source_meta(raw: Any) -> dict[str, Any]:
    meta = raw.get("_meta", {}) if isinstance(raw, dict) else {}
    provenance = meta.get("provenance", {}) if isinstance(meta, dict) else {}
    return {
        "dataset": meta.get("dataset"),
        "canonical": meta.get("canonical"),
        "updated": meta.get("updated") or provenance.get("verified"),
        "verified": provenance.get("verified"),
        "confidence": provenance.get("confidence"),
        "confidence_detail": provenance.get("confidence_detail") or meta.get("confidence_scale"),
        "sources": provenance.get("sources", []),
        "license": meta.get("license"),
    }


def normalize_kingshotpro(raw: Any, source_id: str) -> dict[str, Any]:
    cleaned = strip_strategy(raw)
    if isinstance(cleaned, dict):
        cleaned.pop("_meta", None)
    return {"schema_version": 1, "source_id": source_id, "source_meta": source_meta(raw), "data": cleaned}


def compact_hero_skills(raw: Any) -> tuple[list[dict[str, Any]], dict[str, Any]]:
    heroes = raw if isinstance(raw, list) else raw.get("heroes", []) if isinstance(raw, dict) else []
    result: list[dict[str, Any]] = []
    for hero in heroes:
        if not isinstance(hero, dict) or not isinstance(hero.get("name"), str):
            continue
        item: dict[str, Any] = {
            "hero_name": hero["name"],
            "rarity": hero.get("rarity"),
            "generation": hero.get("generation"),
            "troop_class": hero.get("class") or hero.get("troop_class"),
            "stats": strip_strategy(hero.get("stats", {})),
            "unlock_methods": hero.get("unlock_methods", []),
            "skills": [],
            "source_id": "g2384-kingshot-data",
        }
        for skill in hero.get("skills", []):
            if not isinstance(skill, dict) or not isinstance(skill.get("name"), str):
                continue
            # Descriptive prose is deliberately omitted. Preserve structured factual effects/ladders only.
            skill_row = {
                "name": skill["name"],
                "effects": strip_strategy(skill.get("effects", {})),
                "upgrade_preview": strip_strategy(skill.get("upgrade_preview", {})),
            }
            item["skills"].append(skill_row)
        result.append(item)
    return result, {"heroes": len(result), "skills": sum(len(item["skills"]) for item in result)}


def table_heading(table: Tag) -> str | None:
    node = table.find_previous(["h2", "h3", "h4", "h5", "strong"])
    if isinstance(node, Tag):
        text = " ".join(node.stripped_strings).strip()
        return text or None
    return None


def table_rows(table: Tag) -> tuple[list[str], list[dict[str, str]]]:
    header_cells = table.find_all("th")
    headers = [" ".join(cell.stripped_strings).strip() for cell in header_cells]
    if not headers:
        first = table.find("tr")
        if first:
            headers = [" ".join(cell.stripped_strings).strip() for cell in first.find_all(["td", "th"])]
    headers = [header or f"column_{index + 1}" for index, header in enumerate(headers)]
    rows: list[dict[str, str]] = []
    tr_rows = table.find_all("tr")
    for tr in tr_rows[1:] if headers else tr_rows:
        cells = [" ".join(cell.stripped_strings).strip() for cell in tr.find_all(["td", "th"])]
        if not cells or all(cell == "" for cell in cells):
            continue
        row = {headers[index] if index < len(headers) else f"column_{index + 1}": cell for index, cell in enumerate(cells)}
        rows.append(row)
    return headers, rows


def is_factual_table(heading: str | None, headers: Iterable[str]) -> bool:
    heading_lower = (heading or "").lower()
    if any(word in heading_lower for word in BLOCKED_HEADING_WORDS):
        return False
    haystack = " ".join(headers).lower() + " " + heading_lower
    return any(hint in haystack for hint in FACT_TABLE_HINTS)


def source_scoped_href(href: str, category_path: str) -> bool:
    parsed = urlparse(href)
    path = parsed.path if parsed.netloc else href
    normalized = "/" + category_path.strip("/") + "/"
    return path.startswith(normalized) and path.rstrip("/") != normalized.rstrip("/")


def scrape_category(category_path: str, *, limit: int = 100) -> tuple[dict[str, Any], list[dict[str, Any]]]:
    hub_url = urljoin(KSD_BASE, category_path)
    hub = fetch(hub_url)
    soup = BeautifulSoup(hub.body, "html.parser")
    urls: list[str] = []
    for anchor in soup.find_all("a", href=True):
        href = str(anchor.get("href"))
        absolute = urljoin(hub_url, href)
        if source_scoped_href(urlparse(absolute).path, category_path) and absolute not in urls:
            urls.append(absolute)
    urls = urls[:limit]
    pages: list[dict[str, Any]] = []
    locks: list[dict[str, Any]] = [{"url": hub_url, "sha256": hub.sha256, "kind": "category_hub"}]
    for url in urls:
        try:
            page = fetch(url)
        except requests.RequestException:
            continue
        page_soup = BeautifulSoup(page.body, "html.parser")
        title_node = page_soup.find("h1")
        title = " ".join(title_node.stripped_strings).strip() if isinstance(title_node, Tag) else url.rstrip("/").split("/")[-1]
        tables: list[dict[str, Any]] = []
        for table in page_soup.find_all("table"):
            if not isinstance(table, Tag):
                continue
            heading = table_heading(table)
            headers, rows = table_rows(table)
            if not rows or not is_factual_table(heading, headers):
                continue
            tables.append({"heading": heading, "headers": headers, "rows": rows})
        if not tables:
            continue
        pages.append({"id": slug(title), "name": title, "source_url": url, "tables": tables})
        locks.append({"url": url, "sha256": page.sha256, "kind": "factual_table_page"})
    return {"schema_version": 1, "source_id": "kingshotdata", "pages": pages}, locks


def academy_research() -> tuple[dict[str, Any], list[dict[str, Any]]]:
    page = fetch(KSD_RESEARCH_URL)
    soup = BeautifulSoup(page.body, "html.parser")
    technologies: list[dict[str, Any]] = []
    current_tree = "unknown"

    for element in soup.find_all(["h2", "h3", "h4", "table"]):
        if not isinstance(element, Tag):
            continue
        if element.name == "h2":
            text = " ".join(element.stripped_strings).strip()
            for tree in ("Development", "Economy", "Battle"):
                if text.startswith(tree):
                    current_tree = tree
                    break
            continue
        if element.name != "table":
            continue
        heading = table_heading(element)
        headers, rows = table_rows(element)
        if not rows or "level" not in " ".join(headers).lower() or "research cost" not in " ".join(headers).lower():
            continue
        name = heading or f"{current_tree} technology {len(technologies) + 1}"
        name = re.sub(r"\s+Max Level\s+\d+.*$", "", name, flags=re.IGNORECASE).strip()
        if any(word in name.lower() for word in BLOCKED_HEADING_WORDS):
            continue
        technologies.append({
            "id": slug(name),
            "name": name,
            "tree": current_tree,
            "levels": rows,
            "source_url": KSD_RESEARCH_URL,
            "source_id": "kingshotdata",
        })

    # The maintained page declares these exact coverage counts. Failing here is safer than publishing a partial scrape.
    level_count = sum(len(tech["levels"]) for tech in technologies)
    if len(technologies) != 191 or level_count != 714:
        raise RuntimeError(f"Academy scrape incomplete: expected 191 technologies/714 levels, got {len(technologies)}/{level_count}")
    return {
        "schema_version": 1,
        "source_id": "kingshotdata",
        "declared_technologies": 191,
        "declared_levels": 714,
        "technologies": technologies,
    }, [{"url": KSD_RESEARCH_URL, "sha256": page.sha256, "kind": "academy_research"}]


def merge_heroes(v1_heroes: dict[str, Any], skill_rows: list[dict[str, Any]]) -> dict[str, Any]:
    by_name = {normalized_name(row["hero_name"]): row for row in skill_rows}
    heroes = copy.deepcopy(v1_heroes.get("heroes", []))
    matched = 0
    for hero in heroes:
        if not isinstance(hero, dict):
            continue
        row = by_name.get(normalized_name(str(hero.get("name", ""))))
        if row is None:
            hero.setdefault("skills", [])
            hero.setdefault("skill_source_status", "unknown")
            continue
        matched += 1
        hero["stats"] = row.get("stats", {})
        hero["unlock_methods"] = row.get("unlock_methods", [])
        hero["skills"] = row.get("skills", [])
        hero["skill_source_status"] = "single_source_inspectable"
        source_ids = list(dict.fromkeys([*(hero.get("source_ids") or []), "g2384-kingshot-data"]))
        hero["source_ids"] = source_ids
    return {
        "schema_version": 2,
        "heroes": heroes,
        "provenance": [
            *(v1_heroes.get("provenance", []) if isinstance(v1_heroes.get("provenance"), list) else []),
            {"source_id": "g2384-kingshot-data", "scope": "structured hero stats, acquisition methods and skill names/effect ladders; prose omitted", "matched_heroes": matched},
        ],
    }


def release_dispositions(catalogue_counts: dict[str, int], hero_skill_counts: dict[str, int], academy: dict[str, Any]) -> list[dict[str, Any]]:
    return [
        {"family": "heroes", "status": "canonicalized", "discovered_entities": 34, "canonical_entities": 34, "reason": "Full discovered Hero identity roster retained and enriched with inspectable structured skill/stat facts where source rows match."},
        {"family": "hero_skills", "status": "canonicalized_with_explicit_gaps", "discovered_entities": 34, "canonical_entities": hero_skill_counts["heroes"], "facts_imported": hero_skill_counts["skills"], "reason": "Inspectable GitHub skill/effect ladders are imported; any unmatched current Hero remains explicit unknown rather than index-only."},
        {"family": "hero_star_shards", "status": "canonicalized", "discovered_entities": catalogue_counts.get("hero_shards", 0), "canonical_entities": catalogue_counts.get("hero_shards", 0), "reason": "Complete open shard ladder imported with source metadata."},
        {"family": "hero_xp", "status": "canonicalized", "discovered_entities": catalogue_counts.get("hero_xp", 0), "canonical_entities": catalogue_counts.get("hero_xp", 0), "reason": "Complete open level 1-80 XP/deployment table imported."},
        {"family": "hero_exclusive_equipment", "status": "canonicalized_with_explicit_gaps", "discovered_entities": 34, "canonical_entities": 34, "reason": "Global Widget ladder plus factual Hero-page tables are retained; unknown per-Hero exclusive effects remain unknown."},
        {"family": "hero_gear", "status": "canonicalized", "discovered_entities": 6, "canonical_entities": 6, "reason": "Existing quality/Mastery ladder retained and factual Hero-page gear tables added where published."},
        {"family": "governor_gear", "status": "canonicalized", "discovered_entities": catalogue_counts.get("governor_gear", 0), "canonical_entities": catalogue_counts.get("governor_gear", 0), "reason": "Complete CC-BY per-step material/bonus/power/confidence ladder imported."},
        {"family": "governor_charms", "status": "canonicalized_with_conflict_history", "discovered_entities": catalogue_counts.get("governor_charms", 0), "canonical_entities": catalogue_counts.get("governor_charms", 0), "reason": "Complete open per-level Charm table imported; historical maintained-source max-level disagreement remains visible in conflict history."},
        {"family": "formations", "status": "community_conventions", "discovered_entities": 8, "canonical_entities": 8, "reason": "Named ratios remain sourced non-recommendation conventions."},
        {"family": "buildings", "status": "canonicalized", "discovered_entities": catalogue_counts.get("buildings_tables", 0), "canonical_entities": catalogue_counts.get("buildings_tables", 0), "reason": "Complete structured core-building/Truegold feeds plus factual tables discovered from the maintained building category are snapshotted."},
        {"family": "troops", "status": "canonicalized", "discovered_entities": catalogue_counts.get("troops", 0), "canonical_entities": catalogue_counts.get("troops", 0), "reason": "Complete open troop tier table is imported; source terminology is preserved with canonical class mapping at read time."},
        {"family": "academy_research", "status": "canonicalized", "discovered_entities": academy["declared_technologies"], "canonical_entities": len(academy["technologies"]), "facts_imported": academy["declared_levels"], "reason": "All 191 maintained Academy technologies / 714 visible levels are imported and completeness-gated."},
        {"family": "war_academy", "status": "canonicalized", "discovered_entities": catalogue_counts.get("war_academy", 0), "canonical_entities": catalogue_counts.get("war_academy", 0), "reason": "Complete open 30-technology War Academy feed is imported with per-level resources/Truegold Dust/time."},
        {"family": "alliance_tech", "status": "canonicalized_tables", "discovered_entities": catalogue_counts.get("alliance_tech_tables", 0), "canonical_entities": catalogue_counts.get("alliance_tech_tables", 0), "reason": "All factual tables discoverable from the maintained Alliance Tech category are snapshotted; missing structured cells remain unknown."},
        {"family": "pets", "status": "canonicalized_tables", "discovered_entities": catalogue_counts.get("pets_tables", 0), "canonical_entities": catalogue_counts.get("pets_tables", 0), "reason": "All factual Pet tables discoverable from the maintained category are snapshotted; identity-only rows remain for Pets without a published table."},
        {"family": "masters", "status": "canonicalized_tables", "discovered_entities": catalogue_counts.get("masters_tables", 0), "canonical_entities": catalogue_counts.get("masters_tables", 0), "reason": "Open structured Master feed plus maintained factual Master tables are imported."},
        {"family": "truegold", "status": "canonicalized", "discovered_entities": catalogue_counts.get("truegold", 0), "canonical_entities": catalogue_counts.get("truegold", 0), "reason": "Complete open Truegold/Tempered Truegold table imported."},
        {"family": "max_levels", "status": "canonicalized_summary", "discovered_entities": 10, "canonical_entities": 10, "reason": "Major system caps retained from maintained reference."},
        {"family": "g2384_lineups", "status": "excluded_strategy_opinion", "discovered_entities": 6, "canonical_entities": 0, "reason": "Lineup strings are tactical recommendations rather than factual game progression."},
    ]


def count_dataset_rows(name: str, data: Any) -> int:
    if name == "governor_gear" and isinstance(data, dict):
        return len(data.get("upgradeSteps", []))
    if name == "governor_charms" and isinstance(data, dict):
        for key in ("levels", "upgradeSteps", "charms"):
            if isinstance(data.get(key), list):
                return len(data[key])
    if name == "troops" and isinstance(data, dict):
        if isinstance(data.get("troops"), list):
            return len(data["troops"])
        total = 0
        for value in data.values():
            if isinstance(value, list):
                total += len(value)
        return total
    if name == "war_academy" and isinstance(data, dict):
        return len(data.get("technologies", []))
    if name == "hero_xp" and isinstance(data, dict):
        for key in ("levels", "heroXp", "data"):
            if isinstance(data.get(key), list):
                return len(data[key])
    if name == "hero_shards" and isinstance(data, dict):
        total = 0
        for value in data.values():
            if isinstance(value, list):
                total += len(value)
        return total
    if name == "truegold" and isinstance(data, dict):
        for key in ("buildings", "levels", "data"):
            if isinstance(data.get(key), list):
                return len(data[key])
    if name == "masters_open" and isinstance(data, dict):
        return len(data.get("masters", []))
    if name == "buildings_core" and isinstance(data, dict):
        return len(data.get("buildings", []))
    return 0


def build_release(source_release: str, target_release: str, observed_at: str) -> None:
    source_dir = PROGRESSION_ROOT / source_release
    target_dir = PROGRESSION_ROOT / target_release
    if not source_dir.is_dir():
        raise RuntimeError(f"Source release does not exist: {source_dir}")

    if target_dir.exists():
        shutil.rmtree(target_dir)
    target_dir.mkdir(parents=True)

    v1_release = json.loads((source_dir / "release.json").read_text(encoding="utf-8"))
    v1_heroes = json.loads((source_dir / "heroes.json").read_text(encoding="utf-8"))
    v1_systems = json.loads((source_dir / "systems.json").read_text(encoding="utf-8"))
    v1_formations = json.loads((source_dir / "formations.json").read_text(encoding="utf-8"))

    source_locks: list[dict[str, Any]] = []
    catalogue_counts: dict[str, int] = {}
    source_registry = copy.deepcopy(v1_release.get("sources", []))
    source_registry.append({
        "id": "kingshotpro-open-data",
        "publisher": "KingshotPro",
        "label": "Kingshot Data API",
        "uri": "https://kingshotpro.com/data/",
        "kind": "open_structured_api",
        "authority_tier": "B",
        "official": False,
        "locale": "en",
        "retrieved_at": observed_at,
        "observed_at": observed_at,
        "license_note": "CC-BY-4.0; attribution retained. Recommendation/tier fields are excluded from canonical progression truth.",
    })

    for name, endpoint in KSP_DATASETS.items():
        raw, fetched = fetch_json(urljoin(KSP_BASE, endpoint))
        source_locks.append({"source_id": "kingshotpro-open-data", "dataset": name, "url": fetched.url, "sha256": fetched.sha256, "content_type": fetched.content_type})
        normalized = normalize_kingshotpro(raw, "kingshotpro-open-data")
        write_json(target_dir / f"{name}.json", normalized)
        catalogue_counts[name] = count_dataset_rows(name, raw)

    raw_skills, skills_fetch = fetch_json(G2384_HEROES_URL)
    skill_rows, skill_counts = compact_hero_skills(raw_skills)
    source_locks.append({"source_id": "g2384-kingshot-data", "dataset": "hero_skills", "url": G2384_HEROES_URL, "sha256": skills_fetch.sha256, "content_type": skills_fetch.content_type, "upstream_commit": "82f668b5fd1a77caca841a7149469aea51aead84"})
    heroes_v2 = merge_heroes(v1_heroes, skill_rows)
    write_json(target_dir / "heroes.json", heroes_v2)

    academy, locks = academy_research()
    source_locks.extend({"source_id": "kingshotdata", **lock} for lock in locks)
    write_json(target_dir / "academy_research.json", academy)

    for family, category_path in KSD_CATEGORY_PATHS.items():
        catalogue, locks = scrape_category(category_path)
        source_locks.extend({"source_id": "kingshotdata", "dataset": family, **lock} for lock in locks)
        write_json(target_dir / f"{family}.json", catalogue)
        catalogue_counts[family] = len(catalogue["pages"])

    # Preserve already-reviewed non-recommendation systems/formations while linking detailed families.
    systems_v2 = copy.deepcopy(v1_systems)
    systems_v2["schema_version"] = 2
    systems_v2["detailed_families"] = {
        "governor_gear": "governor_gear.json",
        "governor_charms": "governor_charms.json",
        "buildings_core": "buildings_core.json",
        "buildings_tables": "buildings_tables.json",
        "troops": "troops.json",
        "academy_research": "academy_research.json",
        "war_academy": "war_academy.json",
        "alliance_tech_tables": "alliance_tech_tables.json",
        "pets_tables": "pets_tables.json",
        "masters_open": "masters_open.json",
        "masters_tables": "masters_tables.json",
        "hero_xp": "hero_xp.json",
        "hero_shards": "hero_shards.json",
        "truegold": "truegold.json",
        "heroes_tables": "heroes_tables.json",
    }
    systems_v2["source_ids"] = list(dict.fromkeys([*(systems_v2.get("source_ids") or []), "kingshotpro-open-data", "g2384-kingshot-data"]))
    write_json(target_dir / "systems.json", systems_v2)
    write_json(target_dir / "formations.json", v1_formations)

    catalogue_counts["hero_shards"] = catalogue_counts.pop("hero_shards", catalogue_counts.get("hero_shards", 0))
    release = copy.deepcopy(v1_release)
    release.update({
        "id": target_release,
        "schema_version": 2,
        "dataset_version": "2026.08.23.2",
        "observed_at": observed_at,
        "generated_at": "2026-08-24T00:00:00Z",
        "review_status": "candidate_open_data_canonicalized",
        "release_notes": "Second factual release: complete openly reusable/inspectable progression tables are canonicalized instead of index-only. Strategy/tier/recommendation fields are stripped. Missing values remain unknown and conflicts remain explicit.",
        "sources": source_registry,
        "family_dispositions": release_dispositions(catalogue_counts, skill_counts, academy),
        "files": sorted(path.name for path in target_dir.glob("*.json") if path.name != "release.json") + ["source-lock.json"],
    })
    # Keep conflict history even when a newer structured source supplies a canonical ladder.
    for conflict in release.get("conflicts", []):
        if conflict.get("id") == "governor-charm-max-level":
            conflict["resolution"] = "historical conflict retained; current open per-level ladder is canonical for this release at its own recorded confidence, and calculator evidence remains separately gated"

    write_json(target_dir / "source-lock.json", {"schema_version": 1, "observed_at": observed_at, "sources": sorted(source_locks, key=lambda row: (row.get("url", ""), row.get("dataset", "")))})
    # release files now include source-lock and every generated family.
    release["files"] = sorted(path.name for path in target_dir.glob("*.json") if path.name != "release.json")
    write_json(target_dir / "release.json", release)

    # Determinism/completeness assertions.
    if len(heroes_v2["heroes"]) != 34:
        raise RuntimeError("Hero identity roster must remain exactly 34 for this scoped release")
    if len(academy["technologies"]) != 191 or sum(len(t["levels"]) for t in academy["technologies"]) != 714:
        raise RuntimeError("Academy completeness gate failed")
    if catalogue_counts.get("governor_gear", 0) < 50:
        raise RuntimeError("Governor Gear open ladder unexpectedly incomplete")
    if catalogue_counts.get("war_academy", 0) != 30:
        raise RuntimeError("War Academy expected 30 technologies from the pinned open feed")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--source-release", default="kingshot-2026-08-23-v1")
    parser.add_argument("--target-release", default="kingshot-2026-08-23-v2")
    parser.add_argument("--observed-at", default="2026-08-23")
    args = parser.parse_args()
    build_release(args.source_release, args.target_release, args.observed_at)
    print(f"Generated {args.target_release}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
