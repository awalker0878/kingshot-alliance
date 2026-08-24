#!/usr/bin/env python3
"""Apply the final evidence-backed Factual Governor Progression status closeout."""
from __future__ import annotations

from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
IMPLEMENTATION_SHA = "299e3eddb1d1f16b4be0a08c09e8c7b5091a4c8a"


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, text: str) -> None:
    (ROOT / path).write_text(text, encoding="utf-8")


def replace_once(text: str, old: str, new: str, label: str) -> str:
    count = text.count(old)
    if count != 1:
        raise RuntimeError(f"Expected exactly one {label}; found {count}")
    return text.replace(old, new, 1)


def close_contract() -> None:
    path = "docs/product/factual-governor-progression.md"
    text = read(path)
    text = replace_once(
        text,
        "Status: Release verification — 2026-08-24",
        "Status: Complete — 2026-08-24",
        "contract status",
    )
    text = replace_once(
        text,
        "| 17 | In progress | Final reconciliation + release gates | Spec→code, code→spec, source→data, data→source, source→disposition, UX→backend, authorization and ownership scans are reconciled; all applicable repository gates pass on one immutable candidate. |",
        f"| 17 | Complete | Final reconciliation + release gates | Spec→code, code→spec, source→data, data→source, source→disposition, UX→backend, authorization and ownership scans are reconciled; immutable implementation candidate `{IMPLEMENTATION_SHA}` passed CI, Architecture V3 Verification, Intelligence Verification, Progression Source Refresh, Visual Regression, CodeQL, Dependency Review and King Perks Verification together. |",
        "contract phase 17 row",
    )
    marker = "After each completed requirement implementation proceeds directly to the next incomplete ledger row. Research that exposes another progression family, source conflict, provenance requirement or architecture/UX gap updates this contract and is implemented before closeout.\n"
    replacement = marker + (
        "\nThe delivery queue is closed: phases 0–17 are Complete and no known Factual Governor Progression product feature is deferred. Any later defect, newly discovered source family, or material source/product change that invalidates an exit condition reopens the affected phase. Calculator eligibility remains a separate evidence-gated capability and is not unlocked by this factual-reference closeout.\n"
    )
    text = replace_once(text, marker, replacement, "contract closeout marker")
    write(path, text)


def close_gap_analysis() -> None:
    path = "docs/product/capability-gap-analysis.md"
    text = read(path)
    old = (
        "Factual Governor Progression is in release verification rather than capability discovery. The selected 2026-08-23 source sweep is represented by immutable dataset `2026.08.23.2`: complete reusable public tables are canonicalized, the Century Games 58-row Governor Gear and 22-level Charm tables resolve the current Gear/Charm conflicts while preserving superseded community claims, one unpublished Academy table remains an explicit unknown source gap, and the application exposes the resulting factual corpus without recommendation semantics. Calculator eligibility remains a separate evidence decision and is not implied by factual-reference completeness."
    )
    new = (
        f"Factual Governor Progression is complete and is no longer a capability gap. The selected 2026-08-23 source sweep is represented by immutable dataset `2026.08.23.2`: complete reusable public tables are canonicalized, the Century Games 58-row Governor Gear and 22-level Charm tables resolve the current Gear/Charm conflicts while preserving superseded community claims, one unpublished Academy table remains an explicit unknown source gap, and the application exposes the resulting factual corpus without recommendation semantics. Immutable implementation candidate `{IMPLEMENTATION_SHA}` passed CI, Architecture V3 Verification, Intelligence Verification, Progression Source Refresh, Visual Regression, CodeQL, Dependency Review and King Perks Verification together. Calculator eligibility remains a separate evidence decision and is not implied by factual-reference completeness."
    )
    text = replace_once(text, old, new, "gap-analysis progression paragraph")
    text = replace_once(
        text,
        "| Release verification | Factual Governor Progression |",
        "| Complete | Factual Governor Progression |",
        "gap-analysis progression priority row",
    )
    write(path, text)


def close_delivery_ledger() -> None:
    path = "docs/product/capability-delivery-ledger.md"
    text = read(path)
    text = replace_once(
        text,
        "| 17 | In progress | Final reconciliation + release gates | Product docs are reconciled to implemented evidence and one human-authored immutable candidate must pass CI, Architecture V3, Intelligence, Progression Source Refresh, Visual Regression, CodeQL, Dependency Review and King Perks together before closeout. |",
        f"| 17 | Complete | Final reconciliation + release gates | Product docs are reconciled to implemented evidence; immutable implementation candidate `{IMPLEMENTATION_SHA}` passed CI, Architecture V3, Intelligence, Progression Source Refresh, Visual Regression, CodeQL, Dependency Review and King Perks together. |",
        "delivery-ledger phase 17 row",
    )
    marker = (
        "The capability must not be marked complete merely because another site publishes a table. Conversely, community ownership is not a reason to refuse a complete reusable factual table: selected tables are imported and reconciled when evidence/reuse rules permit, while strategy opinion remains excluded and calculator eligibility remains separately gated.\n"
    )
    replacement = marker + (
        "\nThe Factual Governor Progression delivery queue is closed: every phase is Complete and no known Factual Governor Progression product feature is deferred. The calculator evidence gate remains independently closed; later calculator work requires its own qualifying evidence. Any regression or newly discovered factual-source requirement that invalidates an exit condition reopens the affected phase.\n"
    )
    text = replace_once(text, marker, replacement, "delivery-ledger closeout marker")
    write(path, text)


def main() -> None:
    close_contract()
    close_gap_analysis()
    close_delivery_ledger()
    print(f"Closed Factual Governor Progression against immutable implementation candidate {IMPLEMENTATION_SHA}.")


if __name__ == "__main__":
    main()
