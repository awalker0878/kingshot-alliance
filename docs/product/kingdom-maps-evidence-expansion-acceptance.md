# KingdomMaps Evidence Expansion — Acceptance Matrix

Status: Active acceptance contract

| ID | Acceptance criterion |
| --- | --- |
| KM-001 | Runtime accepts only KingdomMaps schema version 2. |
| KM-002 | V1 dataset/runtime fallback is removed; no compatibility shim or dual-read path remains. |
| KM-003 | Dataset identity matches its immutable file name and checksum pinning remains enforceable. |
| KM-004 | Nested coordinate-system, bounds, source registry, object, zone, structure, rule, resource and terrain shapes are validated. |
| KM-005 | Invalid confidence/status/source references fail with a KingdomMaps validation error rather than an enum `ValueError`. |
| KM-006 | Every factual structure/rule/layer references valid provenance entries. |
| KM-007 | Source registry records source type, URI, lineage, confidence ceiling and reuse-rights basis. |
| KM-008 | ksmapper reuse rights are recorded as user-authorized on 2026-09-06 while confidence remains community-observed. |
| KM-009 | Century Games-sourced 285 Banner limit is represented as official. |
| KM-010 | Century Games-sourced 75% Alliance-resource territory requirement is represented as official and executable. |
| KM-011 | Century Games Banner/HQ connectivity rule is represented as official. |
| KM-012 | HQ legal zones are Badland/Plains and Fertile Land is rejected using official evidence. |
| KM-013 | Plains/Fertile progression prerequisites are represented without inventing unsupported precision. |
| KM-014 | Badland HQ and Plains HQ are distinct factual object definitions. |
| KM-015 | Object definitions use rectangular footprint metadata, not a scalar-only footprint contract. |
| KM-016 | Facility catalogue includes 4 Fortresses, 12 Sanctuaries and 74 researched Outposts. |
| KM-017 | Facility coordinates retain property-level provenance and truthful confidence. |
| KM-018 | ksmapper corpus metadata records 6,499 resource nodes, 501 lakes and 1,948 mountains as authorized community-observed facts. |
| KM-019 | Resource/terrain facts can be consumed without being promoted to official. |
| KM-020 | Semantic release diff reports source, object, zone, structure, rule and layer additions/removals/changes. |
| KM-021 | Production dataset loader is directly tested with the checked-in release. |
| KM-022 | Invalid schema version, malformed nested values, dangling provenance and duplicate keys are directly tested. |
| KM-023 | Territory coverage uses an area-ratio threshold of 75% for the official resource-ownership rule instead of four-corner heuristics. |
| KM-024 | Existing placement checks remain deterministic: bounds, fixed structures, exclusions, zone restrictions, caps and object collisions. |
| KM-025 | PHP/browser geometry parity remains part of required frontend checks. |
| KM-026 | CI runs KingdomMaps dataset validation and tests on every pull request. |
| KM-027 | Published TerritoryPlan revisions remain pinned to the exact map dataset ID/checksum. |
| KM-028 | Product, architecture and delivery documentation describe the same final capability truth. |
