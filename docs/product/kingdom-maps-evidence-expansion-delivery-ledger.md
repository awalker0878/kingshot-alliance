# KingdomMaps Evidence Expansion — Delivery Ledger

Status: In progress

| Slice | State | Evidence / exit condition |
| --- | --- | --- |
| Product/evidence contract | Complete | Product contract + acceptance matrix committed; ksmapper reuse-rights fact recorded. |
| V2 source/provenance model | Pending | Schema V2 source registry, confidence and rights validation implemented/tested. |
| V2 immutable release | Pending | V1 removed; one validated V2 release is the runtime source of truth. |
| Researched official rules | Pending | Century Games rules represented with `official` provenance. |
| Facility catalogue expansion | Pending | 4 Fortresses + 12 Sanctuaries + 74 Outposts represented with provenance. |
| ksmapper spatial corpus | Pending | Authorized community-observed resource/terrain metadata/facts represented and consumable. |
| Typed rectangular geometry | Pending | Footprint/coverage width+height replaces scalar-only factual geometry. |
| 75% coverage | Pending | Territory/resource coverage uses evidence-backed area ratio. |
| Release diff/verification | Pending | Semantic diff + source-lineage/rights verification implemented. |
| Direct loader tests | Pending | Production release and malformed/dangling cases covered. |
| Browser/server parity | Pending | Existing parity checks reconciled to V2 and green. |
| CI gate | Pending | KingdomMaps validation/test path required by CI. |
| Documentation reconciliation | Pending | Architecture/product/codebase docs match implementation. |
| Merge | Pending | Required checks green and PR merged to `main`. |

## Rights decision record

On 2026-09-06 the repository owner/user explicitly confirmed they possess the rights needed to use the ksmapper data in Kingshot Alliance. This closes the prior reuse-rights blocker for the ksmapper-derived 6,499 resource-node corpus and terrain data. The decision does not alter evidence confidence: these facts remain community-observed until independently verified.

## Closeout rule

Do not mark this ledger complete while any acceptance item KM-001 through KM-028 is unverified. Do not merge a compatibility fallback to V1 for convenience; this project is a fresh deployment and V2 is the sole supported runtime contract.
