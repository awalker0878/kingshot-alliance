# Kingdoms testing and evidence

[← Kingdoms domain](../README.md)

**Document type:** Living domain testing and evidence profile  
**Status:** Current  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary validation boundary:** Neutral game identity, tenant-owned roster/snapshots/import/transfer/diplomacy state, realistic-volume query bounds, and explicit no-public-API/webhook/automation boundaries  
**P5 evidence decision:** Living suite map with accepted KINGDOMS-001/002/003 validation, accessibility, migration and performance evidence reused

## 1. Critical claims and validation ownership

Kingdoms validation must prove separation among application identity, Alliance membership and neutral game identity; Alliance ownership of roster/snapshots/imports/transfers/tracking/observations/diplomacy/contacts; stable-ID-only automatic identity matching; append-oriented history; explicit human-controlled transfer/diplomacy transitions; member/manager disclosure separation; and the absence of public API/webhook/ingestion/scoring automation.

## 2. Executable suite mapping

All six PHPUnit evidence classes are material: `Architecture`, `Feature`, `Integration`, `Performance`, `TenantIsolation`, and `Unit`.

Architecture protects identity/ownership/non-capabilities; Feature protects member/manager workspaces; Integration protects persistence/outbox/cross-domain workflows; Performance protects realistic-volume query gates; TenantIsolation protects Alliance-owned state around shared neutral references; Unit protects deterministic parsers, projections, matching and value/state behavior.

## 3. Architecture and domain-boundary validation

Architecture evidence protects the neutral `Kingdom`, `KingdomPlayer`, and `KingdomAlliance` reference model; tenant-owned relationships and history; no name/tag/handle automatic merge; no public Kingdoms API/scope; no wildcard webhook exposure; and no scraper/OCR/bot/automated-ingestion or scoring/recommendation placeholders.

The current [Kingdoms domain](../README.md), security, operations and interfaces contracts remain living ownership truth.

## 4. Authorization, tenancy, security and privacy validation

Feature/Integration/TenantIsolation evidence covers `alliance.view` member reads, `kingdoms.manage` privileged mutations, recent password confirmation, submitted-ID re-resolution, shared neutral references without shared tenant data, manager-private notes/reasons/contacts, and Alliance-Kingdom drift behavior.

[Kingdoms security](../security/README.md) and accepted K1–K3 security reviews define privacy/abuse boundaries that regression evidence must preserve.

## 5. Feature, interface and integration validation

Feature coverage spans roster/history/intelligence/import/export, transfer plans/groups/readiness/blockers/completion, game-Alliance tracking/observations/diplomacy/contacts/intelligence, and member-versus-manager payloads.

Integration evidence covers Audit/Platform outbox, Alliance→Kingdom context, Membership linkage, CSV preview/commit and explicit webhook exclusion. [Kingdoms interfaces](../interfaces/README.md) remains the current interface inventory.

## 6. Idempotency, concurrency and asynchronous validation

Accepted evidence protects exact snapshot/observation retry idempotency, append-only correction/invalidation, CSV preview/commit identity, transfer completion idempotency, explicit readiness/blocker history and outbox retry separation.

Kingdoms has no accepted autonomous scheduler/ingestion worker; shared outbox publication retries must not replay the originating Kingdoms mutation.

## 7. Persistence, migration, rollback and recovery evidence

K1–K3 acceptance includes dependency-ordered migration rollback/reapply evidence. K3 specifically rolls its migrations back to the accepted K2 baseline and reapplies them while preserving K2 tables.

Current CI still runs clean PostgreSQL migration and database backup/restore. Domain-specific history/drift/recovery behavior is documented in [Kingdoms operations](../operations/README.md); database recovery does not imply external game-state ingestion because none is accepted.

## 8. Performance, query and capacity evidence

Kingdoms has explicit accepted realistic-volume performance gates:

- K1: 150 tracked players / 450 snapshots with bounded current/7-day/30-day intelligence query shape;
- K2: 150 transfer participants / 20 groups with bounded plan/group/readiness/blocker/completion projections; and
- K3: 120 tracked game Alliances / 600 observations / 120 diplomacy relationships / 60 active contacts with the manager intelligence projection bounded to **10 or fewer SELECT statements**.

These are regression gates for accepted query shape, not generic service-level latency promises.

## 9. Accessibility and frontend evidence

Accepted K1–K3 accessibility records are indexed from [Kingdoms product evidence](../product/README.md). Source-level guards cover settings, roster, history, intelligence, CSV migration, transfer planning, tracking/observations/diplomacy/contacts and intelligence surfaces.

Current `npm run check` remains frontend quality evidence but does not replace deployment-specific accessibility checks.

## 10. Historical accepted evidence

Whole-increment immutable gates are:

- `KINGDOMS-001`: implementation `7f743507b70865692290f517cd2de494ec54abae` — DR `31288932532`, CodeQL `31288932537`, CI `31288932560`; final head `9e71427e081928d9a91d986048c03ee3116bff7c` — DR `31289567298`, CodeQL `31289567296`, CI `31289567297`.
- `KINGDOMS-002`: implementation `64189559c66e15dc56ec31f9b340284c89c30e6c` — DR `31337595942`, CodeQL `31337595933`, CI `31337595937`.
- `KINGDOMS-003`: implementation `068c4086744f71d33453734f1f1b05fe1430cbff` — DR `31430279647`, CodeQL `31430279652`, CI `31430279638`.

Detailed slice/exit/accessibility evidence is retained under [Kingdoms product evidence](../product/README.md).

## 11. Evidence identity, retention and supersession

K1–K3 SHAs/run IDs/query counts remain immutable historical evidence. Current Kingdoms behavior follows current code/tests/living contracts and this validation map.

Future Kingdoms increments must record exact validated/final SHAs and protected run IDs under [testing/evidence standard](../../../product/testing-evidence-standard.md), while retaining earlier accepted increment records.

## 12. Gaps, non-capabilities and related documentation

No public Kingdoms API/webhook, scraping/OCR/bot/automated ingestion, automatic transfer execution, automatic diplomacy, cross-Alliance shared intelligence, or scoring/ranking/recommendation behavior is accepted. Testing explicitly protects those absences rather than treating them as missing coverage.

Related documentation:

- [Kingdoms domain](../README.md)
- [Kingdoms security](../security/README.md)
- [Kingdoms operations](../operations/README.md)
- [Kingdoms interfaces](../interfaces/README.md)
- [Kingdoms product evidence](../product/README.md)
- [P5 evidence matrix](../../../product/testing-evidence-coverage-matrix.md)
