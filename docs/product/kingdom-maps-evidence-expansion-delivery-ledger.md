# KingdomMaps Evidence Expansion — Delivery Ledger

Status: Implementation complete; CI/PR verification and merge pending

| Slice | State | Evidence / exit condition |
| --- | --- | --- |
| Product/evidence contract | Complete | Product contract + acceptance matrix committed; ksmapper reuse-rights fact recorded. |
| V2 source/provenance model | Complete | Schema V2 source registry, per-fact confidence/provenance, confidence ceilings, rights basis and lineage validation implemented. |
| V2 immutable release | Complete | V1 removed; `kingshot-evidence-backed-2026-09-06-v2` is the sole runtime release contract. |
| Researched official rules | Complete | Century Games rules represented as property/rule-level `official` facts without promoting unrelated community geometry. |
| Facility catalogue expansion | Complete | Immutable artifact contains 4 Fortresses + 12 Sanctuaries + 74 Outposts with provenance. |
| ksmapper spatial corpus | Complete | User-authorized community-observed facts record 6,499 resource nodes, 501 lakes and 1,948 mountains with explicit source-corpus state. |
| Typed rectangular geometry | Complete | Footprint/coverage width+height replaces scalar factual geometry in server, browser, Intelligence and reconciliation consumers. |
| 75% coverage | Complete | Shared area geometry reads the official Alliance-resource minimum ratio from released map facts. |
| Release diff/verification | Complete | Semantic diff, artifact checksum verification and source-lineage/rights verification implemented. |
| Direct loader tests | Complete | Production release, schema-V1 rejection, provenance, artifact, facility-count and 75%-coverage cases covered. |
| Browser/server parity | Complete — awaiting CI | Shared golden fixture and browser engine reconciled to V2 including Banner-HQ connectivity. |
| Operational commands | Complete | `kingdom-maps:list`, `validate`, `verify-sources`, and `diff` registered by `KingdomMapsServiceProvider`. |
| CI gate | Complete — awaiting run | Dedicated `KingdomMaps Assurance` workflow validates release/source/test/parity surfaces on PRs and `main`. |
| Documentation reconciliation | Complete | Architecture and expansion docs describe evidence-backed V2 and the ksmapper rights decision. |
| PR verification | Pending | Dedicated assurance plus repository-required checks green; review threads resolved. |
| Merge | Pending | PR merged to `main` only after verification passes. |

## Rights decision record

On 2026-09-06 the repository owner/user explicitly confirmed they possess the rights needed to use the ksmapper data in Kingshot Alliance. This closes the prior reuse-rights blocker for the ksmapper-derived resource-node and terrain data. The decision does not alter evidence confidence: these facts remain community-observed until independently verified.

## Fresh-deployment decision

This implementation intentionally removes V1 rather than carrying compatibility shims, aliases or dual reads. Schema V2 is the only runtime KingdomMaps contract.

## Closeout rule

Do not mark the PR/merge rows complete until the dedicated assurance workflow and the repository-required checks are green and all actionable review threads are resolved.
