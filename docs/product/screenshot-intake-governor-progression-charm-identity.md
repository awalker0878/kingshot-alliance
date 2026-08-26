# Screenshot Intake: Governor Progression — Governor Charm Identity Boundary

**Status:** Normative product-contract amendment — 2026-08-26  
**Applies to:** `docs/product/screenshot-intake-governor-progression.md`  
**Source of truth:** This amendment is binding for the current implementation and must be consolidated into the primary Governor Progression product contract during final reconciliation.

## Requirement discovered during implementation verification

The current pinned `GameWorld/Progression` release provides factual Governor Charm level progression, but it does not provide canonical Governor Charm identity IDs for OCR-visible names such as a named seal or sigil. Screenshot Intake must therefore preserve those visible names as Evidence provenance without manufacturing canonical identity by slugifying or otherwise transforming OCR text.

### GP-CHARM-IDENTITY-01 — No synthetic canonical Charm IDs

For the v1 `governor_charms` screenshot schema, an OCR-visible Charm name is an observed Evidence field only. It MUST NOT be converted into, reviewed as, or committed as a canonical `charm_id` unless the exact pinned Progression release contains an explicit canonical identity with that ID.

The application MUST NOT create canonical-looking IDs by lowercasing, slugifying, hashing or otherwise transforming observed Charm names.

### GP-CHARM-IDENTITY-02 — V1 Roster meaning is catalogue-provable only

With the current Progression release, the reviewed `governor_charms` destination payload contains only the explicit Charm slot and observed Charm level that are supported by the screenshot schema and factual catalogue. The raw/normalized OCR Charm name remains retained in Evidence provenance and review context but is not destination-owned canonical identity.

Missing canonical identity remains unknown; it is not inferred from display text.

### GP-CHARM-IDENTITY-03 — Future identity support requires explicit catalogue and schema evolution

A future immutable Progression release may introduce canonical Charm identity IDs. Supporting those identities in Roster observations requires an explicit schema/validator evolution that validates the reviewed ID against the exact pinned release. Existing v1 observations and Evidence are not silently reinterpreted.

### GP-CHARM-IDENTITY-04 — Validation and regression coverage

The v1 destination validator MUST reject unexpected `charm_id` input rather than accepting a synthetic identity. Automated coverage MUST prove that slot/level observations are accepted while an invented Charm identity cannot cross the Evidence → Roster boundary.

## Delivery-ledger reconciliation

This requirement is part of GP-06 (dataset-pinned normalization), GP-10 (closed typed destination payloads), GP-11 (destination validation), GP-19 (automated tests) and GP-20 (repository-wide release verification). None may be marked complete until synthetic Charm identity generation is removed and regression-protected.
