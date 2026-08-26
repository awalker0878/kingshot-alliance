# Screenshot Intake: Governor Progression — Evidence Interface Boundary

**Status:** Normative product-contract amendment — 2026-08-26  
**Applies to:** `docs/product/screenshot-intake-governor-progression.md`  
**Source of truth:** This amendment is part of `/docs/product` and is binding for the current implementation until consolidated into the primary product contract during final reconciliation.

## Requirement discovered during implementation verification

Governor Progression screenshot delivery exposed a cross-family contract coupling in the Evidence reference boundary. The general Evidence reference contract used by existing destination contexts must remain narrow and family-neutral. A new Screenshot Intake family must not add family-specific methods to a shared contract merely because the implementation class can answer both kinds of questions.

### GP-BOUNDARY-01 — Interface segregation is mandatory

`Intelligence/Evidence` MUST expose Governor Progression review provenance validation through a dedicated Evidence-owned contract. The existing general Evidence reference contract used by Transfer and other Evidence consumers MUST retain only its existing family-neutral operations.

Governor Progression MUST NOT extend a general/shared Evidence reference interface with methods that require Governor-specific concepts such as:

- Roster entry identity;
- Governor/player identity;
- Governor Progression Evidence kind;
- Governor Progression schema version;
- pinned Progression dataset ID;
- pinned Progression dataset checksum; or
- a Governor Progression review identifier.

### GP-BOUNDARY-02 — Destination validation remains strict

The dedicated Governor Progression Evidence provenance contract MUST allow `Intelligence/Roster` to verify, before every destination write, that the referenced reviewed Evidence is the exact approved review for the expected:

- Evidence record;
- Alliance;
- Roster entry;
- Governor/player;
- explicit Governor Progression Evidence kind;
- schema version;
- Progression dataset ID; and
- Progression dataset checksum.

This separation MUST NOT weaken destination authorization, provenance validation, dataset pinning, semantic duplicate handling, or idempotency.

### GP-BOUNDARY-03 — Family isolation is regression-protected

Existing Evidence consumers such as `GameWorld/KingdomTransfers` MUST remain compilable and testable without implementing Governor Progression-specific contract methods. Verification MUST include the pre-existing Transfer Evidence boundary tests as regression protection for this rule.

## Implementation consequence

The current implementation is incomplete until it:

1. restores the general `EvidenceReferenceLookup` contract to its family-neutral shape;
2. introduces a dedicated Evidence-owned Governor Progression review/provenance lookup contract;
3. binds the dedicated contract inside `Intelligence/Evidence`;
4. makes the Roster Governor Progression destination writer depend on the dedicated contract rather than the general reference contract;
5. preserves the existing Evidence query implementation as the owner-side adapter when appropriate; and
6. passes the existing Transfer Evidence boundary verification unchanged.

## Delivery-ledger reconciliation

This requirement is part of the existing architecture, destination-handoff, automated-test, and repository-verification ledger items. No affected GP delivery item may be marked complete until this boundary is implemented and the Transfer regression test passes without weakening or deleting the test.
