#!/usr/bin/env python3
"""Build factual progression schema v2 from the verified release-cutoff source inventory."""
from __future__ import annotations

import json
import re
import sys
from collections import Counter
from typing import Any
from urllib.parse import urljoin, urlparse

import requests
from bs4 import BeautifulSoup, Tag

import refresh


ACADEMY_TREE_BOUNDARIES = ((45, "Development"), (89, "Economy"), (191, "Battle"))
ALLIANCE_TECH_TREE_BOUNDARIES = ((24, "Growth"), (40, "Territory"), (60, "Battle"))
EXPECTED_ACADEMY_SOURCE_GAP = ("Fortified Mail VI", 6)
CATEGORY_ADAPTERS: dict[str, tuple[str, int]] = {
    "category/buildings/": ("buildings/", 12),
    "category/pets/": ("pets/", 14),
    "category/masters/": ("masters/", 6),
    "category/heroes/": ("heroes/", 34),
    "category/database/": ("database/", 8),
    "category/events/": ("events/", 33),
}


def bounded_tree(index: int, boundaries: tuple[tuple[int, str], ...], label: str) -> str:
    for upper, tree in boundaries:
        if index < upper:
            return tree
    raise RuntimeError(f"{label} index outside declared source coverage: {index}")


def details_technology(
    details: Tag,
    index: int,
    boundaries: tuple[tuple[int, str], ...],
    source_url: str,
    source_id: str,
    name_counts: Counter[tuple[str, str]],
    *,
    require_table: bool,
) -> tuple[dict[str, Any], tuple[str, int] | None]:
    summary = details.find("summary")
    if not isinstance(summary, Tag):
        raise RuntimeError("Technology details record is missing a summary.")
    summary_text = " ".join(summary.stripped_strings).strip()
    max_match = re.search(r"Max Level\s+(\d+)", summary_text, re.IGNORECASE)
    if max_match is None:
        raise RuntimeError(f"Technology summary is missing Max Level: {summary_text[:160]}")

    strong = summary.find("strong")
    name = " ".join(strong.stripped_strings).strip() if isinstance(strong, Tag) else ""
    if name == "":
        name = re.sub(r"\s+Max Level\s+\d+.*$", "", summary_text, flags=re.IGNORECASE).strip()
    if name == "":
        raise RuntimeError(f"Technology is missing a name: {summary_text[:160]}")

    tree = bounded_tree(index, boundaries, "technology")
    max_level = int(max_match.group(1))
    name_counts[(tree, name)] += 1
    occurrence = name_counts[(tree, name)]
    identifier = f"{refresh.slug(tree)}-{refresh.slug(name)}"
    if occurrence > 1:
        identifier += f"-{occurrence}"

    table = details.find("table")
    levels: list[dict[str, str]] = []
    levels_status = "complete_visible_table"
    missing: tuple[str, int] | None = None
    if isinstance(table, Tag):
        headers, rows = refresh.table_rows(table)
        if not headers or not rows:
            raise RuntimeError(f"Technology table is empty for {name}")
        levels = rows
        if len(levels) != max_level:
            raise RuntimeError(
                f"Technology table row count does not match declared max for {name}: "
                f"{len(levels)} rows vs max {max_level}"
            )
    else:
        if require_table:
            raise RuntimeError(f"Required technology level table is missing for {name}")
        missing = (name, max_level)
        levels_status = "source_table_missing"

    return ({
        "id": identifier,
        "name": name,
        "tree": tree,
        "occurrence": occurrence,
        "max_level": max_level,
        "levels_status": levels_status,
        "levels": levels,
        "source_url": source_url,
        "source_id": source_id,
    }, missing)


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
        if not isinstance(summary, Tag) or "Max Level" not in " ".join(summary.stripped_strings):
            continue
        technology, missing = details_technology(
            details,
            len(technologies),
            ACADEMY_TREE_BOUNDARIES,
            refresh.KSD_RESEARCH_URL,
            "kingshotdata",
            name_counts,
            require_table=False,
        )
        technologies.append(technology)
        if missing is not None:
            missing_tables.append(missing)

    level_count = sum(len(technology["levels"]) for technology in technologies)
    declared_max_level_sum = sum(int(technology["max_level"]) for technology in technologies)
    ids = [technology["id"] for technology in technologies]
    tree_counts = Counter(technology["tree"] for technology in technologies)
    if len(technologies) != 191:
        raise RuntimeError(f"Academy expected 191 technology identities; got {len(technologies)}")
    if level_count != 714:
        raise RuntimeError(f"Academy expected exactly 714 visible level rows; got {level_count}")
    if declared_max_level_sum != 720:
        raise RuntimeError(f"Academy expected declared max-level sum 720; got {declared_max_level_sum}")
    if len(ids) != len(set(ids)):
        raise RuntimeError("Academy technology IDs are not unique")
    if missing_tables != [EXPECTED_ACADEMY_SOURCE_GAP]:
        raise RuntimeError(f"Academy source-table gap changed: {missing_tables!r}")
    if tree_counts != Counter({"Battle": 102, "Development": 45, "Economy": 44}):
        raise RuntimeError(f"Academy tree coverage changed: {tree_counts!r}")

    return ({
        "schema_version": 1,
        "source_id": "kingshotdata",
        "declared_technologies": 191,
        "declared_levels": 714,
        "visible_level_rows": 714,
        "declared_max_level_sum": 720,
        "source_table_gaps": [{
            "technology_id": "battle-fortified-mail-vi",
            "technology": "Fortified Mail VI",
            "declared_max_level": 6,
            "missing_visible_level_rows": 6,
            "status": "source_table_missing",
            "reason": "The maintained source publishes the technology identity and Max Level 6 but no per-level table. Values remain unknown.",
            "source_id": "kingshotdata",
        }],
        "technologies": technologies,
    }, [{
        "url": refresh.KSD_RESEARCH_URL,
        "sha256": page.sha256,
        "kind": "academy_research",
        "technology_identities": 191,
        "visible_level_rows": 714,
        "source_table_gaps": 1,
    }])


def normalized_url(base: str, href: str) -> str:
    absolute = urljoin(base, href)
    parsed = urlparse(absolute)
    clean_path = re.sub(r"/{2,}", "/", parsed.path)
    if not clean_path.endswith("/"):
        clean_path += "/"
    return f"{parsed.scheme}://{parsed.netloc}{clean_path}"


def discover_detail_urls(hub_url: str, detail_root: str, expected: int) -> tuple[list[str], dict[str, Any]]:
    hub = refresh.fetch(hub_url)
    soup = BeautifulSoup(hub.body, "html.parser")
    root = "/" + detail_root.strip("/") + "/"
    urls: list[str] = []
    for anchor in soup.find_all("a", href=True):
        absolute = normalized_url(hub_url, str(anchor.get("href")))
        parsed = urlparse(absolute)
        if parsed.netloc != urlparse(refresh.KSD_BASE).netloc:
            continue
        if not parsed.path.startswith(root) or parsed.path.rstrip("/") == root.rstrip("/"):
            continue
        if absolute not in urls:
            urls.append(absolute)
    urls.sort()
    if len(urls) < expected:
        raise RuntimeError(
            f"{hub_url} expected at least {expected} detail links under /{detail_root}, got {len(urls)}"
        )
    return urls, {
        "url": hub_url,
        "sha256": hub.sha256,
        "kind": "category_hub",
        "expected_entities": expected,
        "discovered_candidate_links": len(urls),
    }


def factual_tables(page_soup: BeautifulSoup) -> list[dict[str, Any]]:
    tables: list[dict[str, Any]] = []
    for table in page_soup.find_all("table"):
        if not isinstance(table, Tag):
            continue
        heading = refresh.table_heading(table)
        heading_lower = (heading or "").lower()
        if any(word in heading_lower for word in refresh.BLOCKED_HEADING_WORDS):
            continue
        headers, rows = refresh.table_rows(table)
        if not rows:
            continue
        header_text = " ".join(headers).lower()
        if any(word in header_text for word in ("recommendation", "recommended", "tier list", "best lineup")):
            continue
        normalized_headers = ["Rank" if header.strip().lower() == "ranking" else header for header in headers]
        normalized_rows: list[dict[str, str]] = []
        for row in rows:
            normalized_rows.append({
                ("Rank" if str(key).strip().lower() == "ranking" else str(key)): value
                for key, value in row.items()
            })
        tables.append({"heading": heading, "headers": normalized_headers, "rows": normalized_rows})
    return tables


def canonical_hero_names() -> set[str]:
    path = refresh.PROGRESSION_ROOT / "kingshot-2026-08-23-v1" / "heroes.json"
    data = json.loads(path.read_text(encoding="utf-8"))
    return {
        refresh.normalized_name(str(hero.get("name", "")))
        for hero in data.get("heroes", [])
        if isinstance(hero, dict) and str(hero.get("name", "")).strip() != ""
    }


def scrape_confirmed_category(hub_path: str) -> tuple[dict[str, Any], list[dict[str, Any]]]:
    if hub_path == "alliance-tech/":
        return parse_alliance_tech()
    adapter = CATEGORY_ADAPTERS.get(hub_path)
    if adapter is None:
        raise RuntimeError(f"No confirmed category adapter for {hub_path}")
    detail_root, expected = adapter
    hub_url = urljoin(refresh.KSD_BASE, hub_path)
    urls, hub_lock = discover_detail_urls(hub_url, detail_root, expected)
    pages: list[dict[str, Any]] = []
    locks: list[dict[str, Any]] = [hub_lock]
    allowed_hero_names = canonical_hero_names() if hub_path == "category/heroes/" else None

    for url in urls:
        try:
            page = refresh.fetch(url)
        except requests.RequestException as exception:
            raise RuntimeError(f"Failed confirmed detail source {url}: {exception}") from exception
        soup = BeautifulSoup(page.body, "html.parser")
        title_node = soup.find("h1")
        title = " ".join(title_node.stripped_strings).strip() if isinstance(title_node, Tag) else ""
        if title == "":
            raise RuntimeError(f"Confirmed detail source is missing H1 title: {url}")
        if allowed_hero_names is not None and refresh.normalized_name(title) not in allowed_hero_names:
            continue
        tables = factual_tables(soup)
        pages.append({
            "id": refresh.slug(title),
            "name": title,
            "source_url": url,
            "structured_table_count": len(tables),
            "tables": tables,
        })
        locks.append({"url": url, "sha256": page.sha256, "kind": "factual_detail_page"})

    if len(pages) != expected:
        names = [page["name"] for page in pages]
        raise RuntimeError(
            f"{hub_url} expected {expected} confirmed entities after filtering, got {len(pages)}: {names}"
        )

    return ({
        "schema_version": 1,
        "source_id": "kingshotdata",
        "hub_url": hub_url,
        "discovered_pages": len(pages),
        "pages": pages,
    }, locks)


def parse_alliance_tech() -> tuple[dict[str, Any], list[dict[str, Any]]]:
    url = urljoin(refresh.KSD_BASE, "alliance-tech/")
    page = refresh.fetch(url)
    soup = BeautifulSoup(page.body, "html.parser")
    technologies: list[dict[str, Any]] = []
    name_counts: Counter[tuple[str, str]] = Counter()

    for details in soup.find_all("details"):
        if not isinstance(details, Tag):
            continue
        summary = details.find("summary")
        if not isinstance(summary, Tag) or "Max Level" not in " ".join(summary.stripped_strings):
            continue
        technology, missing = details_technology(
            details,
            len(technologies),
            ALLIANCE_TECH_TREE_BOUNDARIES,
            url,
            "kingshotdata",
            name_counts,
            require_table=True,
        )
        if missing is not None:
            raise RuntimeError(f"Alliance Tech unexpectedly missing table for {missing[0]}")
        technologies.append(technology)

    level_count = sum(len(technology["levels"]) for technology in technologies)
    ids = [technology["id"] for technology in technologies]
    tree_counts = Counter(technology["tree"] for technology in technologies)
    if len(technologies) != 60:
        raise RuntimeError(f"Alliance Tech expected 60 technologies; got {len(technologies)}")
    if level_count != 279:
        raise RuntimeError(f"Alliance Tech expected 279 visible levels; got {level_count}")
    if len(ids) != len(set(ids)):
        raise RuntimeError("Alliance Tech technology IDs are not unique")
    if tree_counts != Counter({"Growth": 24, "Territory": 16, "Battle": 20}):
        raise RuntimeError(f"Alliance Tech tree coverage changed: {tree_counts!r}")

    pages = [{
        "id": technology["id"],
        "name": technology["name"],
        "tree": technology["tree"],
        "source_url": url,
        "tables": [{
            "heading": f"{technology['name']} levels",
            "headers": list(technology["levels"][0].keys()) if technology["levels"] else [],
            "rows": technology["levels"],
        }],
    } for technology in technologies]

    return ({
        "schema_version": 1,
        "source_id": "kingshotdata",
        "declared_technologies": 60,
        "visible_level_rows": 279,
        "trees": [
            {"name": "Growth", "technologies": 24, "levels": 108},
            {"name": "Territory", "technologies": 16, "levels": 80},
            {"name": "Battle", "technologies": 20, "levels": 91},
        ],
        "technologies": technologies,
        "pages": pages,
    }, [{
        "url": url,
        "sha256": page.sha256,
        "kind": "alliance_tech",
        "technology_identities": 60,
        "visible_level_rows": 279,
    }])


def reconcile_release(target_release: str) -> None:
    release_dir = refresh.PROGRESSION_ROOT / target_release
    release_path = release_dir / "release.json"
    release = json.loads(release_path.read_text(encoding="utf-8"))

    for row in release.get("family_dispositions", []):
        family = row.get("family")
        if family == "academy_research":
            row["facts_imported"] = 714
            row["unresolved_level_tables"] = 1
            row["reason"] = (
                "All 191 maintained Academy technology identities and all 714 visible per-level rows are imported. "
                "Fortified Mail VI declares Max Level 6 but publishes no level table; its six level values remain explicit unknowns."
            )
        elif family == "alliance_tech":
            row["status"] = "canonicalized"
            row["discovered_entities"] = 60
            row["canonical_entities"] = 60
            row["facts_imported"] = 279
            row["reason"] = "All 60 maintained Alliance Technology identities and all 279 visible level rows are imported with costs, donations, bar, time, effect and requirements."
        elif family == "buildings":
            row["reason"] = "The complete 12-page maintained Building sweep plus the selected open structured Building/Truegold feeds are snapshotted; every structured factual table is retained."
        elif family == "pets":
            row["reason"] = "All 14 maintained Pet detail pages are represented and every structured factual table published on those pages is retained; genuinely unpublished fields remain unknown."
        elif family == "masters":
            row["reason"] = "All 6 maintained Master detail pages plus the open structured Master feed are represented; factual Affinity/skill/material/research tables are retained."

    extra_dispositions = {
        "database_reference_tables": {
            "family": "database_reference_tables",
            "status": "canonicalized_corroboration",
            "discovered_entities": 8,
            "canonical_entities": 8,
            "reason": "All eight maintained Database & Charts pages are snapshotted as structured corroborating facts for Gear, Charms, Hero Gear, shards, Mastery, max levels, server timeline and Widgets.",
        },
        "progression_event_tables": {
            "family": "progression_event_tables",
            "status": "canonicalized_factual_tables",
            "discovered_entities": 33,
            "canonical_entities": 33,
            "reason": "All 33 maintained Event detail pages are source-locked; structured factual tables are retained while narrative strategy/recommendation prose is excluded.",
        },
    }
    existing = {str(row.get("family")) for row in release.get("family_dispositions", []) if isinstance(row, dict)}
    for family, disposition in extra_dispositions.items():
        if family not in existing:
            release.setdefault("family_dispositions", []).append(disposition)

    release["source_gaps"] = [{
        "id": "academy-fortified-mail-vi-level-table",
        "family": "academy_research",
        "source_id": "kingshotdata",
        "entity": "Fortified Mail VI",
        "declared_max_level": 6,
        "missing_visible_level_rows": 6,
        "status": "source_table_missing",
        "resolution": "Do not infer the six rows. Retain the technology identity and wait for an inspectable source table or independently verified in-game evidence.",
    }]
    release["source_inventory_document"] = "docs/product/factual-governor-progression-source-inventory.md"
    refresh.write_json(release_path, release)


def assert_category_release(release_dir: Any, file_name: str, expected: int) -> None:
    data = json.loads((release_dir / file_name).read_text(encoding="utf-8"))
    pages = data.get("pages", [])
    if not isinstance(pages, list) or len(pages) != expected:
        raise RuntimeError(f"{file_name} expected {expected} confirmed pages; got {len(pages) if isinstance(pages, list) else 'invalid'}")


def main() -> int:
    source_release = "kingshot-2026-08-23-v1"
    target_release = "kingshot-2026-08-23-v2"
    refresh.academy_research = parse_academy
    refresh.scrape_category = scrape_confirmed_category
    refresh.KSD_CATEGORY_PATHS = {
        "buildings_tables": "category/buildings/",
        "pets_tables": "category/pets/",
        "masters_tables": "category/masters/",
        "heroes_tables": "category/heroes/",
        "database_tables": "category/database/",
        "events_tables": "category/events/",
        "alliance_tech_tables": "alliance-tech/",
    }
    refresh.build_release(source_release, target_release, "2026-08-23")
    reconcile_release(target_release)

    release_dir = refresh.PROGRESSION_ROOT / target_release
    academy = json.loads((release_dir / "academy_research.json").read_text(encoding="utf-8"))
    alliance_tech = json.loads((release_dir / "alliance_tech_tables.json").read_text(encoding="utf-8"))
    assert len(academy["technologies"]) == 191
    assert sum(len(row["levels"]) for row in academy["technologies"]) == 714
    assert academy["source_table_gaps"][0]["technology"] == "Fortified Mail VI"
    assert len(alliance_tech["technologies"]) == 60
    assert sum(len(row["levels"]) for row in alliance_tech["technologies"]) == 279
    assert_category_release(release_dir, "buildings_tables.json", 12)
    assert_category_release(release_dir, "pets_tables.json", 14)
    assert_category_release(release_dir, "masters_tables.json", 6)
    assert_category_release(release_dir, "heroes_tables.json", 34)
    assert_category_release(release_dir, "database_tables.json", 8)
    assert_category_release(release_dir, "events_tables.json", 33)
    print(
        f"Generated {target_release}: 191 Academy technologies / 714 visible rows / 1 source gap; "
        "60 Alliance Tech technologies / 279 rows; confirmed category sweeps complete"
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
