# KingdomMaps Evidence Expansion — Acceptance Matrix

Status: Verified; release ready

| ID | Acceptance criterion | State |
| --- | --- | --- |
| KM-001 | V1 runtime dataset is removed and V2 is the sole supported KingdomMaps schema. | Implemented |
| KM-002 | Unsupported schema versions fail explicitly. | Implemented |
| KM-003 | V2 nested schema validation covers sources, coordinate system, bounds, object definitions, variants, zones, structures, placement rules and resource/terrain layers. | Implemented |
| KM-004 | Confidence supports `official`, `verified_observation`, `community_observed`, `disputed`, and `unknown`. | Implemented |
| KM-005 | Property/fact provenance cannot reference missing sources. | Implemented |
| KM-006 | Fact confidence cannot exceed source confidence ceilings. | Implemented |
| KM-007 | Source registry records type, URI, rights basis and lineage. | Implemented |
| KM-008 | ksmapper source records `user_authorized_reuse_2026-09-06` while retaining a `community_observed` ceiling. | Implemented |
| KM-009 | Shared ksmapper lineage does not count as independent corroboration. | Implemented |
| KM-010 | Current released V2 dataset is immutable and checksum-pinned. | Implemented |
| KM-011 | Immutable facility artifact identity and SHA-256 are validated before hydration. | Implemented |
| KM-012 | Facility catalogue contains 4 Fortresses, 12 Sanctuaries and 74 Outposts. | Implemented |
| KM-013 | V2 records the authorized ksmapper corpus facts for 6,499 resource nodes. | Implemented |
| KM-014 | V2 records 501 lake and 1,948 mountain corpus facts. | Implemented |
| KM-015 | Century Games 285 Banner maximum is represented as an `official` fact. | Implemented |
| KM-016 | Century Games 75% Alliance-resource territory requirement is represented as an `official` rule. | Implemented |
| KM-017 | Banner-to-HQ disconnection semantics are represented as an `official` rule. | Implemented |
| KM-018 | HQ Badland/Plains legality and Fertile prohibition are represented without promoting zone geometry to official. | Implemented |
| KM-019 | Plains/Fertile progression requirements are represented without inventing unsupported finer mappings. | Implemented |
| KM-020 | Object and structure geometry uses rectangular width/height footprints, not scalar factual `size`. | Implemented |
| KM-021 | Server placement validation consumes V2 rectangular geometry. | Implemented |
| KM-022 | TerritoryPlanning coverage/analysis consumes shared V2 geometry. | Implemented |
| KM-023 | Intelligence spatial-observation bounds consume V2 footprints. | Implemented |
| KM-024 | Territory Reconciliation absence/bounds reasoning consumes V2 footprints. | Implemented |
| KM-025 | Official 75% resource ownership is area-based and data-driven from the release rule. | Implemented |
| KM-026 | PHP/browser geometry parity is reconciled to V2 including Banner-HQ connectivity. | Verified |
| KM-027 | Direct production-release tests cover schema, rights, provenance, artifacts, facility counts and 75% geometry. | Verified |
| KM-028 | `kingdom-maps:list`, `validate`, `verify-sources`, and `diff` are registered and the dedicated assurance workflow gates relevant PR/main changes. | Verified |

## Verification exit

Satisfied on 2026-09-07 at source head `750e8ebd184b7915d45bc6107037490eb1a5347d`: KingdomMaps Assurance #46, CI #5325, Architecture V3 Verification #2330, Intelligence Verification #2167, Visual Regression #3297, Gift Code Verification #47, CodeQL #5321 and Dependency Review #5093 all completed successfully. PR #154 had no reviews, inline review comments or conversation comments, so there were no actionable review threads to resolve. The delivery ledger is reconciled for merge.
