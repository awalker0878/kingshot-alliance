# KingdomMaps Evidence Expansion — Delivery Ledger

Status: Verification complete; merge ready

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
| Browser/server parity | Complete | V2 parity fixture, browser engine and deterministic Territory visual contract verified by KingdomMaps Assurance #46 and Visual Regression #3297. |
| Operational commands | Complete | `kingdom-maps:list`, `validate`, `verify-sources`, and `diff` registered by `KingdomMapsServiceProvider`. |
| CI gate | Complete | Source head `750e8ebd184b7915d45bc6107037490eb1a5347d` passed KingdomMaps Assurance #46, CI #5325, Architecture V3 #2330, Intelligence #2167, Visual #3297, Gift Code #47, CodeQL #5321 and Dependency Review #5093. |
| Documentation reconciliation | Complete | Architecture, product, acceptance, delivery and source/confidence-matrix docs describe the same evidence-backed V2 truth and ksmapper rights decision. |
| PR verification | Complete | PR #154 is mergeable; all named checks are green; GitHub reports no reviews, inline review comments or conversation comments. |
| Merge readiness | Complete | Delivery evidence is closed and PR #154 is ready to merge to `main`; merge verification is performed after the PR transition. |

## Rights decision record

On 2026-09-06 the repository owner/user explicitly confirmed they possess the rights needed to use the ksmapper data in Kingshot Alliance. This closes the prior reuse-rights blocker for the ksmapper-derived resource-node and terrain data. The decision does not alter evidence confidence: these facts remain community-observed until independently verified.

## Fresh-deployment decision

This implementation intentionally removes V1 rather than carrying compatibility shims, aliases or dual reads. Schema V2 is the only runtime KingdomMaps contract.

## Closeout record

Closed for merge on 2026-09-07 after all repository verification named above completed successfully and the PR review audit found no actionable threads. The final merge state is verified against GitHub after PR #154 is merged.
