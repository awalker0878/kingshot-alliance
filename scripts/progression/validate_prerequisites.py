#!/usr/bin/env python3
"""Validate source-exposed research prerequisites without inventing missing game rules."""
from __future__ import annotations

import json
import re
from collections import defaultdict
from pathlib import Path
from typing import Any

LEVEL_REQUIREMENT = re.compile(r"^(?P<name>.+?)\s+Lv\.?\s*(?P<level>\d+)$", re.IGNORECASE)


def load(path: Path) -> dict[str, Any]:
    return json.loads(path.read_text(encoding="utf-8"))


def validate_academy(release_dir: Path) -> dict[str, int]:
    document = load(release_dir / "academy_research.json")
    technologies = document.get("technologies")
    if not isinstance(technologies, list) or len(technologies) != 191:
        raise RuntimeError("Academy prerequisite validation requires the complete 191-technology catalogue.")

    by_tree_and_name: dict[tuple[str, str], list[dict[str, Any]]] = defaultdict(list)
    by_name: dict[str, list[dict[str, Any]]] = defaultdict(list)
    nodes: set[tuple[str, int]] = set()

    for technology in technologies:
        if not isinstance(technology, dict):
            raise RuntimeError("Academy technology payload is invalid.")
        technology_id = str(technology.get("id", "")).strip()
        name = str(technology.get("name", "")).strip()
        tree = str(technology.get("tree", "")).strip()
        max_level = technology.get("max_level")
        if technology_id == "" or name == "" or tree == "" or not isinstance(max_level, int) or max_level < 1:
            raise RuntimeError(f"Academy technology identity is incomplete: {technology!r}")
        by_tree_and_name[(tree, name)].append(technology)
        by_name[name].append(technology)
        for level in range(1, max_level + 1):
            nodes.add((technology_id, level))

    graph: dict[tuple[str, int], set[tuple[str, int]]] = {node: set() for node in nodes}
    explicit_edges = 0
    external_requirements = 0
    external_nodes: set[tuple[str, int]] = set()

    for technology in technologies:
        technology_id = str(technology["id"])
        tree = str(technology["tree"])
        max_level = int(technology["max_level"])
        levels = technology.get("levels")
        if not isinstance(levels, list):
            raise RuntimeError(f"Academy levels are invalid for {technology_id}.")

        # Research levels are sequential even when a source row only repeats an external gate.
        for level in range(2, max_level + 1):
            graph[(technology_id, level)].add((technology_id, level - 1))

        for row in levels:
            if not isinstance(row, dict):
                raise RuntimeError(f"Academy level row is invalid for {technology_id}.")
            raw_level = str(row.get("Lv", "")).strip()
            if not raw_level.isdigit():
                raise RuntimeError(f"Academy level row is missing a numeric Lv for {technology_id}: {row!r}")
            level = int(raw_level)
            node = (technology_id, level)
            if node not in graph:
                raise RuntimeError(f"Academy level {level} is outside declared max for {technology_id}.")

            raw_requirement = str(row.get("Requirement", "")).strip()
            if raw_requirement in {"", "—", "-", "None", "No requirement"}:
                continue

            for token in (part.strip() for part in raw_requirement.split("·")):
                if token == "":
                    continue
                match = LEVEL_REQUIREMENT.fullmatch(token)
                if match is None:
                    raise RuntimeError(
                        f"Unparseable Academy prerequisite for {technology_id} Lv.{level}: {token!r}"
                    )
                required_name = match.group("name").strip()
                required_level = int(match.group("level"))
                if required_level < 1:
                    raise RuntimeError(
                        f"Academy prerequisite {required_name!r} for {technology_id} Lv.{level} "
                        "must reference a positive level."
                    )

                candidates = by_tree_and_name.get((tree, required_name), [])
                if not candidates:
                    candidates = by_name.get(required_name, [])

                if len(candidates) > 1:
                    raise RuntimeError(
                        f"Academy prerequisite {required_name!r} for {technology_id} Lv.{level} "
                        f"resolved ambiguously to {len(candidates)} technology identities."
                    )

                if len(candidates) == 0:
                    # The maintained source also names prerequisites outside the Academy technology
                    # catalogue (for example Academy/Town Center levels and troop Upgrade tracks).
                    # Preserve those labels as explicit source-scoped external leaf nodes instead of
                    # guessing their owning family or rejecting otherwise complete source data.
                    external_nodes.add((required_name, required_level))
                    external_requirements += 1
                    continue

                required = candidates[0]
                required_id = str(required["id"])
                required_max = int(required["max_level"])
                if required_level > required_max:
                    raise RuntimeError(
                        f"Academy prerequisite {required_id} Lv.{required_level} for {technology_id} Lv.{level} "
                        f"is outside declared max {required_max}."
                    )
                graph[node].add((required_id, required_level))
                explicit_edges += 1

    visiting: set[tuple[str, int]] = set()
    visited: set[tuple[str, int]] = set()

    def visit(node: tuple[str, int], path: list[tuple[str, int]]) -> None:
        if node in visited:
            return
        if node in visiting:
            cycle = " -> ".join(f"{tech} Lv.{level}" for tech, level in [*path, node])
            raise RuntimeError(f"Academy prerequisite cycle detected: {cycle}")
        visiting.add(node)
        for dependency in sorted(graph[node]):
            visit(dependency, [*path, node])
        visiting.remove(node)
        visited.add(node)

    for node in sorted(graph):
        visit(node, [])

    return {
        "technology_nodes": len(technologies),
        "level_nodes": len(nodes),
        "explicit_technology_edges": explicit_edges,
        "external_level_requirements": external_requirements,
        "external_requirement_nodes": len(external_nodes),
    }


def validate_release(release_dir: Path) -> dict[str, int]:
    stats = validate_academy(release_dir)

    # The selected War Academy open dataset publishes per-level resources, dust, time and benefit,
    # but no prerequisite field. Absence remains unknown rather than being inferred from row order.
    war = load(release_dir / "war_academy.json")
    technologies = war.get("data", {}).get("technologies") if isinstance(war.get("data"), dict) else None
    if not isinstance(technologies, list) or len(technologies) != 30:
        raise RuntimeError("War Academy source coverage changed before prerequisite validation.")
    for technology in technologies:
        if not isinstance(technology, dict):
            raise RuntimeError("War Academy technology payload is invalid.")
        for level in technology.get("levels", []):
            if isinstance(level, dict) and any(key.lower().startswith("require") for key in level):
                raise RuntimeError(
                    "War Academy source now exposes prerequisites; add them to the canonical graph validator before publication."
                )

    return stats


if __name__ == "__main__":
    root = Path(__file__).resolve().parents[2] / "resources" / "data" / "progression" / "kingshot-2026-08-23-v2"
    result = validate_release(root)
    print(
        "Validated Academy prerequisite graph: "
        f"{result['technology_nodes']} technologies / {result['level_nodes']} declared levels / "
        f"{result['explicit_technology_edges']} explicit technology edges / "
        f"{result['external_level_requirements']} external source requirements across "
        f"{result['external_requirement_nodes']} external prerequisite nodes; no cycles."
    )
