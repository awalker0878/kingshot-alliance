# KINGDOMS-003 whole-increment security review

[← Security documentation](README.md)

**Scope:** `KINGDOMS-003` / `K3-P6`  
**Status:** **Accepted**  
**Validated implementation SHA:** `068c4086744f71d33453734f1f1b05fe1430cbff`

## Review objective

Validate the complete Kingdom/alliance intelligence and diplomacy increment as one security boundary after the tracking, observation, diplomacy, contact and descriptive-intelligence slices were composed together.

The review focuses on tenant isolation, neutral-reference confusion, identity ambiguity, historical integrity, private management data, object-ID tampering, Kingdom drift, abuse/scoring risk, internal-event egress and public API exposure.

## Assets and trust boundaries

Protected tenant-owned assets include:

- `TrackedKingdomAlliance` state and manager notes;
- `KingdomAllianceObservation` history/provenance/correction evidence;
- current diplomacy relationship and transition history;
- diplomacy terms/rationale;
- diplomacy contacts and verification/notes;
- descriptive intelligence derived from those tenant records; and
- audit/outbox evidence for privileged K3 mutations.

`Kingdom` and `KingdomAlliance` are global neutral references. They are not authorization principals and never carry one tenant's observations, diplomacy, contacts or derived intelligence.

The active platform `Alliance` remains the security/tenant boundary.

## Identity-confusion controls

The accepted identity contract is preserved end to end:

- stable game alliance ID within one Kingdom is the only automatic game-alliance identity match key;
- name/tag collisions do not merge neutral references;
- contact display names/handles are coordination labels, not identity keys;
- a diplomacy contact does not create/link a `KingdomPlayer`, `User`, membership, role or permission; and
- sharing a neutral `KingdomAlliance` reference across tenants grants no access to another tenant's tracking/intelligence.

Whole-increment acceptance tests create two tenants that deliberately share one stable neutral game-side reference and verify their private state remains separate.

## Tenancy and object-ID isolation

Every tenant-owned query/mutation begins from active Alliance context or re-resolves submitted objects underneath it.

Controls verified across the increment include:

- cross-tenant tracking ID rejection;
- cross-tenant observation ID rejection;
- cross-tenant contact ID rejection;
- tenant-first dashboard aggregation;
- no access inheritance through a shared neutral reference; and
- no arbitrary client-supplied ID path for aggregate dashboard projection.

Ordinary safe reads require `alliance.view`. Privileged K3 mutations require `kingdoms.manage` plus recent password confirmation.

## Historical integrity and correction controls

Observation and diplomacy history is append-oriented rather than destructively rewritten.

Observation correction creates a replacement record while invalidating/preserving the original. Invalidated observations are excluded from current/member projections but remain historical evidence for managers.

Diplomacy transitions preserve prior/current state history. A same-state material metadata change appends history, while an exact repeat is idempotent.

Contact lifecycle uses active/inactive preservation rather than ordinary destructive deletion.

These controls reduce both accidental history loss and the ability to silently rewrite the basis for later intelligence views.

## Kingdom-drift controls

Tracked records capture Kingdom context.

When the platform Alliance later changes Kingdom:

- old tracking/observation/diplomacy/contact history remains readable to authorized users;
- normal privileged mutations fail closed;
- records are not silently retargeted to the new Kingdom; and
- archival remains the explicit recovery/terminal action for the stale tracking relationship.

The final P6 workflow re-exercises observation, diplomacy and contact mutation rejection after drift before archival recovery.

## Private-data controls

Manager-private data includes tracking notes, observation correction/invalidation reasons, diplomacy terms/rationale, contact details/notes and management provenance.

Acceptance verifies:

- ordinary member payloads omit those fields;
- the member dashboard contains only approved safe facts, freshness/trends, current diplomacy label and review status;
- manager dashboard contact diagnostics are aggregate counts/verification state and do not copy contact text;
- private tracking/correction/diplomacy/contact strings are absent from K3 audit metadata; and
- the same private strings are absent from K3 outbox payloads.

Contact records intentionally exclude phone, home-address, credential/private-secret and authorization-link fields.

## Abuse and decision-automation review

The complete K3 runtime remains descriptive rather than prescriptive.

Architecture tests protect against contracts for:

- threat scores;
- desirability/target/composite scores;
- best/worst punitive rankings;
- automated recommendations;
- automatic diplomacy transitions;
- automated negotiation;
- automatic transfer behavior;
- scraping/OCR/bot ingestion;
- automated game ingestion; and
- cross-tenant shared-intelligence shortcuts.

Observed power/member changes remain factual comparisons. They do not automatically produce relationship or transfer decisions.

## External integration and egress review

K3 audit/outbox events are internal durability evidence only.

The wildcard-webhook regression test now includes representative K3 event types and confirms zero external deliveries for:

- tracking start;
- observation record/correction;
- diplomacy transition; and
- contact save/deactivation.

No public K3 API route or scope exists. Architecture guards assert K3 alliance-intelligence/diplomacy concepts do not appear in `routes/api.php`.

## Query and availability considerations

The dashboard uses bounded, tenant-first queries rather than per-row history loading. The accepted realistic-volume performance test exercises 120 tracked alliances, 600 observations, 120 diplomacy relationships and 60 active contacts with a manager projection budget of at most 10 SELECT statements.

Bounded query shape reduces N+1/resource-exhaustion risk while preserving factual history semantics.

## Migration and recovery review

The final gate includes a K3-only rollback/reapply test to the accepted `KINGDOMS-002` baseline.

The K3 schema can be removed in reverse dependency order while accepted K2 roster/snapshot/transfer tables remain intact, then reapplied in forward order.

The repository CI additionally proves immutable-image build, ephemeral staging, backup/restore and image scanning on the exact accepted implementation SHA.

## Residual boundaries

Repository acceptance does not prove external infrastructure controls such as production firewalling, DNS, secret stores, live alert routing or real recovery infrastructure. Those remain production-launch responsibilities.

Future automated ingestion, cross-alliance/shared intelligence and public integration contracts require separately approved scopes and security reviews; K3 acceptance does not authorize them.

## Verification evidence

Validated implementation SHA: `068c4086744f71d33453734f1f1b05fe1430cbff`.

- Dependency Review `31430279647` — success;
- CodeQL `31430279652` — success;
- CI `31430279638` — success;
- Pint — 483 files;
- PHPStan/Larastan — 345/345, zero errors;
- ParaTest/PHPUnit — 359 tests / 4,824 assertions;
- frontend dependency/lint/format/type/build gates — success;
- immutable image, staging, backup/restore, vulnerability scan and cleanup — success.

## Disposition

No repository-controlled security blocker remains for `KINGDOMS-003` repository/product acceptance.

`KINGDOMS-003` is **Accepted** within its approved scope and explicit non-capabilities. Production cutover remains separately not approved.
