#!/usr/bin/env python3
"""Reconcile Factual Governor Progression product docs with the verified implementation."""
from __future__ import annotations

from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, text: str) -> None:
    (ROOT / path).write_text(text, encoding="utf-8")


def replace_once(text: str, old: str, new: str, label: str) -> str:
    count = text.count(old)
    if count != 1:
        raise RuntimeError(f"Expected exactly one {label}; found {count}")
    return text.replace(old, new, 1)


def reconcile_contract() -> None:
    path = "docs/product/factual-governor-progression.md"
    text = read(path)
    text = replace_once(
        text,
        "Status: Active delivery — 2026-08-23",
        "Status: Release verification — 2026-08-24",
        "contract status",
    )
    snapshot_anchor = (
        "Re-importing identical source snapshots and normalization rules must produce identical semantic content/checksum and no duplicate facts.\n"
    )
    snapshot_block = snapshot_anchor + (
        "\nFor dynamic HTML sources, the immutable snapshot checksum is computed from the normalized factual rows after structural validation rather than from volatile page chrome. This keeps a release stable across non-factual HTML changes while any changed Gear, Charm, Widget or other pinned factual row still changes the semantic checksum and forces review.\n"
    )
    text = replace_once(text, snapshot_anchor, snapshot_block, "semantic snapshot policy")

    evidence_marker = "## Acceptance criteria\n"
    evidence = """## Implemented release evidence\n\nRelease `kingshot-2026-08-23-v2` / dataset `2026.08.23.2` is the reviewed factual corpus candidate for the 2026-08-23 source cutoff. The reproducible release contains 24 generated JSON documents and currently verifies:\n\n- 34/34 discovered Heroes with structured skills (262 structured skill records);\n- 1,054 Hero star/progression rows and 80 Hero XP/deployment-capacity rows;\n- 22 applicable Exclusive Weapons with 220 ten-level rows, plus the complete 10-level / 275-total Widget reference;\n- Hero Gear/Mastery reference tables;\n- all 58 Governor Gear steps, with Century Games Tier-A values canonical and 10 differing prior community rows retained as superseded claims;\n- all 22 Governor Charm levels, with the historical 21-vs-22 maximum conflict resolved for this release by Tier-A/current corroboration while the older claim remains recorded;\n- 8 named formation conventions with no recommendation semantics;\n- all 12 maintained Building entities and 15 selected troop-tier records;\n- 191 Academy technologies with all 714 source-published level rows, plus one explicit source gap for `Fortified Mail VI` (declared max level 6, no published six-row table);\n- Academy prerequisite validation across 720 declared levels with 232 explicit technology edges, while source-labelled external prerequisites remain distinct external nodes;\n- 30 War Academy technologies;\n- 60 Alliance Technologies with 279 source-published level rows;\n- all 14 maintained Pets and all 6 maintained Masters with every structured table published on their selected detail pages; and\n- Truegold/Tempered Truegold, VIP, max-level/server-timeline/cap references and factual progression-event tables discovered in the selected source sweep.\n\nThe only missing Academy values are the six unpublished `Fortified Mail VI` level rows. They remain `unknown`; their absence is a documented source gap rather than an unfinished import. The Building inventory also retains the explicitly dispositioned early Range/Stable prose/table inconsistency rather than inventing a prerequisite. Resolved conflicts remain visible in provenance instead of being deleted.\n\nThe current official/KR reconciliation pages are pinned by deterministic SHA-256 values over their normalized factual tables, so cosmetic/dynamic HTML changes do not mutate this immutable release. Runtime requests consume the committed release and never scrape these sources.\n\n"""
    if "## Implemented release evidence\n" not in text:
        text = replace_once(text, evidence_marker, evidence + evidence_marker, "acceptance criteria marker")

    for phase in range(1, 17):
        old = f"| {phase} | In progress |"
        new = f"| {phase} | Complete |"
        text = replace_once(text, old, new, f"phase {phase} status")
    write(path, text)


def reconcile_inventory() -> None:
    path = "docs/product/factual-governor-progression-source-inventory.md"
    text = read(path)
    marker = "### Kingshot Data\n"
    block = """### Century Games official Governor Gear / Charm tables\n\nThe selected official KingShot wiki Governor Gear page exposes a complete 58-row Governor Gear ladder and a complete 22-level Governor Charm ladder. Release `2026.08.23.2` uses those Tier-A rows as canonical current Gear/Charm facts where they overlap community data. Ten Governor Gear rows differed from the earlier open structured community feed; the official values are canonical and every displaced community row is retained as an explicit `superseded_by_tier_a` claim. The older community claim that Governor Charms stop at level 21 remains historical provenance; the current 22-level boundary is resolved by the official table plus the maintained KR table.\n\nBecause the official page contains dynamic non-factual HTML, its immutable source snapshot is the SHA-256 of the normalized 58 Gear + 22 Charm factual rows after the expected table structure and row counts are validated. A cosmetic page change therefore does not create false release drift; a factual row change does.\n\n### Kingshot Data KR corroboration\n\nThe maintained KR reference contributes independent structured corroboration for all 22 Governor Charm levels and the 10-level Exclusive Hero Gear Widget ladder (275 Widgets total). These pages are likewise pinned by normalized factual-table SHA-256 rather than volatile page HTML.\n\n"""
    if "### Century Games official Governor Gear / Charm tables\n" not in text:
        text = replace_once(text, marker, block + marker, "Kingshot Data inventory marker")
    write(path, text)


def reconcile_catalogue() -> None:
    path = "docs/product/capability-catalogue.md"
    text = read(path)
    text = replace_once(
        text,
        "- sourced Hero shard/Widget/Mastery progression, Hero/Governor Gear and Charm facts with unresolved numeric conflicts preserved rather than guessed;",
        "- sourced Hero shard/Widget/Mastery progression plus Hero/Governor Gear and Charm facts, with current conflicts resolved only through documented source precedence and all superseded/unresolved claims preserved rather than guessed away;",
        "catalogue Gear/Charm evidence bullet",
    )
    text = replace_once(
        text,
        "- indexed/dispositioned building, troop, Academy/War Academy, Alliance Tech, Pet, Master and additional progression families;",
        "- canonicalized building, troop, Academy/War Academy, Alliance Tech, Pet, Master and additional progression families at every selected inspectable row, with genuinely unpublished or disputed values explicitly dispositioned;",
        "catalogue indexed family bullet",
    )
    write(path, text)


def reconcile_gap_analysis() -> None:
    path = "docs/product/capability-gap-analysis.md"
    text = read(path)
    anchor = "The application has governed workflows across Alliance membership/access, recruitment review, content revisions, Events and participation, rosters/battle plans/rallies, King Perks, results, intelligence provenance, Kingdom transfers, platform administration, webhooks, Gift Codes, retryable notifications, Territory & Hive planning, Screenshot Intake, and Bear Hunt Debrief.\n"
    addition = anchor + "\nFactual Governor Progression is in release verification rather than capability discovery. The selected 2026-08-23 source sweep is represented by immutable dataset `2026.08.23.2`: complete reusable public tables are canonicalized, the Century Games 58-row Governor Gear and 22-level Charm tables resolve the current Gear/Charm conflicts while preserving superseded community claims, one unpublished Academy table remains an explicit unknown source gap, and the application exposes the resulting factual corpus without recommendation semantics. Calculator eligibility remains a separate evidence decision and is not implied by factual-reference completeness.\n"
    text = replace_once(text, anchor, addition, "gap-analysis current coverage anchor")
    row = "| Evidence-gated | Calculators | Troop, Governor Gear, Charm and Hero Gear planning with saved scenarios | No implementation until the dataset gate in the delivery ledger is satisfied |"
    new_row = "| Release verification | Factual Governor Progression | Immutable, source-labelled factual progression corpus, Governor Hero observations and dataset-pinned saved loadouts without recommendations | Complete reusable open tables are canonicalized; unknown/conflicting values remain explicit; calculator eligibility remains separately evidence-gated |\n" + row
    text = replace_once(text, row, new_row, "calculator prioritized row")
    write(path, text)


def reconcile_delivery_ledger() -> None:
    path = "docs/product/capability-delivery-ledger.md"
    text = read(path)
    if "## Factual Governor Progression delivery program\n" in text:
        return
    block = """\n\n## Factual Governor Progression delivery program\n\nTarget: a comprehensive factual KingShot progression corpus and Governor progression/loadout experience whose completeness is proven by reproducible source-backed releases, not by links to external sites. Canonical contract: [Factual Governor Progression](factual-governor-progression.md).\n\nArchitectural ownership remains split: `GameWorld/Progression` owns immutable catalogue truth and reconciliation; `Intelligence/Roster` owns dated Governor observations; `Operations/Rallies` owns saved loadout/planning intent. The calculator evidence gate remains closed independently of factual-reference completeness.\n\nRelease `2026.08.23.2` canonicalizes the selected open/inspectable source surface: 34 Heroes/262 skills/1,054 star rows, 220 Exclusive Weapon rows, Hero Gear/Mastery, 58 official Governor Gear rows, 22 Charm levels, 8 community formation conventions, 12 Buildings, 15 troop records, 191 Academy technologies/714 published rows, 30 War Academy technologies, 60 Alliance Technologies/279 rows, 14 Pets, 6 Masters and the selected Truegold/VIP/max-level/event reference families. `Fortified Mail VI` remains the single explicit Academy table gap because its six level rows are not published; no values are inferred.\n\n### Phase queue\n\n| Phase | Status | Slice | Exit condition |\n| --- | --- | --- | --- |\n| 0 | Complete | Product contract | Product/evidence/reuse/ownership/UX/authorization/calculator-gate contract is the implementation source of truth. |\n| 1 | Complete | Architecture + release foundation | Source registry, immutable releases, deterministic checksums, owner boundaries and import validation are implemented. |\n| 2 | Complete | Source discovery + snapshots | Selected official/community/open/GitHub source surfaces are surveyed and reproducibly pinned; complete reusable tables are not left index-only. |\n| 3 | Complete | Heroes | 34-Hero roster, progression, XP/shards and 262 structured skills are represented/dispositioned. |\n| 4 | Complete | Exclusive equipment + Widgets | 22 applicable ten-level Exclusive Weapon ladders (220 rows), non-applicability and Widget progression are represented. |\n| 5 | Complete | Hero Gear + Mastery | Selected inspectable enhancement/mastery/material tables are represented with provenance. |\n| 6 | Complete | Governor Gear + Charms | 58 official Gear rows and 22 Charm levels are canonical; Tier-A resolutions retain superseded claims. |\n| 7 | Complete | Named formations | Eight sourced ratios remain scoped community conventions with no recommendation semantics. |\n| 8 | Complete | Buildings + unlocks | All 12 maintained Building entities/tables are represented; disputed early prerequisite prose is explicitly dispositioned. |\n| 9 | Complete | Troops | Selected troop-tier/cost/time/points facts are canonicalized with source terminology. |\n| 10 | Complete | Academy + War Academy | 191 Academy identities/714 published rows and 30 War Academy technologies are represented; Academy dependency graph is validated and the single unpublished table remains explicit unknown. |\n| 11 | Complete | Pets + Masters | All 14 maintained Pets and 6 Masters retain every selected published structured factual table. |\n| 12 | Complete | Additional families | Alliance Tech 60/279, Truegold, VIP, caps/server timeline and selected progression-event tables are canonicalized/dispositioned. |\n| 13 | Complete | Governor Hero observations | Intelligence/Roster observations normalize canonical Hero identities, retain unknowns, remain idempotent and pin the factual release. |\n| 14 | Complete | Saved loadouts | Operations planning intent stores canonical Hero IDs/formation ratios and exact dataset identity/checksum separately from observations. |\n| 15 | Complete | Progression Library UX | Factual rows, source/confidence/conflict/unknown states, formations, observations and loadouts are localized, accessible and responsive with deterministic visual coverage. |\n| 16 | Complete | Completeness/reconciliation gates | Source/coverage/licence/reference/advisory-field/prerequisite/idempotency checks and read-only source regeneration pass; dynamic pages use normalized factual-table checksums. |\n| 17 | In progress | Final reconciliation + release gates | Product docs are reconciled to implemented evidence and one human-authored immutable candidate must pass CI, Architecture V3, Intelligence, Progression Source Refresh, Visual Regression, CodeQL, Dependency Review and King Perks together before closeout. |\n\nThe capability must not be marked complete merely because another site publishes a table. Conversely, community ownership is not a reason to refuse a complete reusable factual table: selected tables are imported and reconciled when evidence/reuse rules permit, while strategy opinion remains excluded and calculator eligibility remains separately gated.\n"""
    write(path, text.rstrip() + block + "\n")


def main() -> None:
    reconcile_contract()
    reconcile_inventory()
    reconcile_catalogue()
    reconcile_gap_analysis()
    reconcile_delivery_ledger()
    print("Reconciled Factual Governor Progression product documentation with implemented release evidence.")


if __name__ == "__main__":
    main()
