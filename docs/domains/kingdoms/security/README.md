# Kingdoms security profile

[← Kingdoms domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current runtime through `KINGDOMS-004` Accepted; `KINGDOMS-005` is planning-only at K5-P0  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary security boundary:** Alliance-owned neutral-game workflows and K4 ingestion/promotion/scheduler/operations state, separated from tenant authorization, source secrets, public machine exposure, decision automation, and any not-yet-approved cross-Alliance sharing

## 1. Security purpose and scope

Kingdoms protects Alliance-owned roster/history/import/intelligence, transfer/diplomacy/contact state, tracked game-Alliance intelligence, and accepted K4 ingestion. K4 permits approved generic acquisition plus delegated factual player/game-Alliance observation promotion, scheduled execution/replay, source-revocation reconciliation and bounded operational retention/health only.

`KINGDOMS-005` is currently a no-runtime planning increment. Its P0 security review defines conditions for a future explicit cross-tenant authorization bridge; until a later runtime slice is separately accepted, cross-Alliance shared intelligence remains unavailable.

## 2. Assets and sensitive data

Tenant-private assets include roster/snapshot/import provenance, transfer/diplomacy/contact data, K4 operational state, opaque source cursor/window identity, bounded scheduling/failure state, normalized candidate facts while retained, and machine provenance on promoted snapshots/observations. Neutral Kingdom/player/game-Alliance identities are reference data, not authorization.

K4 accepts no arbitrary manager source URL/header/cookie/credential or canonical raw external response archive. Retention reduces normalized operational payload lifetime while preserving canonical provenance.

Any future K5 runtime must treat source tracking notes, diplomacy/contact data, player/roster data, transfer state, correction rationale and K4 operational/source provenance as source-private and non-shareable under the initial scope.

## 3. Actors, authentication and authorization

Member reads require `alliance.view`; human Kingdoms management/replay requires `kingdoms.manage` with recent password confirmation where applicable. Machine acquisition/promotion/maintenance uses validated tenant-owned subscription/candidate context and never a fabricated User actor.

Human-only governance remains mandatory for roster/tracking lifecycle, K3 correction/invalidation, diplomacy, contacts, and transfer decisions.

K5 planning does not change these permissions. Its proposed consent actions would require `kingdoms.manage` plus recent password confirmation on both source and recipient sides before any later shared-data slice could exist.

## 4. Tenant and privacy boundaries

Every scheduled run re-resolves owning Alliance/current Kingdom/source version before staging/promotion. Player promotion terminates at an existing owning-Alliance roster relation; game-Alliance promotion terminates at an existing active owning-Alliance tracking relation.

Manager/operations presentation is limited to bounded source/scheduling/state/count/reason metadata. Candidate normalized bodies are not exposed through operational health; secrets/raw responses, diplomacy/contact private text and unrelated manager data remain excluded.

Global neutral `KingdomAlliance` identity never grants cross-tenant access. The proposed K5 contract requires recipient-first authorization through a distinct accepted source→recipient agreement and explicit shared target; that path does not exist in the current runtime.

## 5. Trust boundaries and data flows

K4 crosses shared scheduler/queue infrastructure into an approved acquisition adapter, then into bounded staging and accepted K1/K3 promotion actions. Database/domain checks remain authoritative even though queue uniqueness/overlap controls reduce duplicate execution.

Maintenance flows reconcile the current repository/operator adapter registry into subscription operational state and age-based retention into K4 operational rows. Neither flow acquires new business authority.

No concrete production external network/source trust boundary exists because production adapter configuration remains empty. No K5 cross-tenant runtime trust boundary exists yet; P0 is documentation-only.

## 6. Threats, abuse cases and controls

Controls address cross-tenant target confusion, name/tag identity guessing, automatic roster/tracking creation/reactivation, machine correction, fake-human attribution, scheduler duplication, cursor rewind, replay multiplication, stale/revoked context, unbounded retry pressure, failure-detail leakage, excessive operational-data retention, destructive history coupling and premature decision automation.

K5 P0 additionally identifies tenant enumeration, invitation-secret leakage, confused-deputy access through neutral IDs, stale-cache access after revocation, cross-Kingdom sharing and transitive reshare as threats that must fail closed before runtime sharing is accepted.

See [K4 Slice E operations security/privacy review](kingdoms-automated-ingestion-operations-security-review.md), the [K4 exit report](../product/kingdoms-automated-ingestion-exit-report.md), and [K5-P0 security/privacy review](kingdoms-shared-intelligence-p0-security-review.md).

## 7. Integrity, concurrency and idempotency

Due subscription claims, context validation, source reconciliation and cursor advancement use row locks where concurrent mutation matters. Jobs are unique/overlap-protected but source-window/candidate/promoted-record idempotency remains authoritative for at-least-once delivery.

Cursor advances only after Completed/Partial batch state. Exact replay of a completed window must return the same next cursor. Source removal/version drift disables rather than substitutes. Machine game-Alliance observations remain append-only and cannot carry correction/invalidation instructions.

Any future K5 agreement/revocation checks must remain authoritative at read time; cache hits or global neutral identity cannot substitute for recipient/source agreement authorization.

## 8. Secrets and credential handling

K4 introduces no source-secret lifecycle. Source credentials/raw responses must not enter Kingdoms tables, normalized facts, logs, audit/outbox or support evidence. A concrete credential/network path remains a separate source-approval requirement.

K5 P0 proposes only a human consent-bootstrap invitation secret: plaintext shown once, cryptographic hash persisted, bounded expiry/single use, and exclusion from logs/audit/outbox. This remains a planning constraint until Slice A exists.

## 9. Destructive operations, retention and deletion

Operational subscriptions/batches/candidates/scheduling state are not canonical history. Promoted K1/K3 records retain copied bounded provenance without FK dependence on operational K4 rows.

Default retention redacts terminal normalized payloads after 30 days, purges promoted/rejected candidates after 90 days, retains quarantined candidates for 180 days, purges terminal batches after 90 days only when candidate-free, and compacts disabled-subscription scheduling/failure state after 30 days while preserving the subscription row.

Operators must not manually delete/rewrite K4 rows to force retries. Retention never deletes promoted canonical observations or rewrites their source provenance.

K5 planning explicitly avoids recipient copies of source observation history; future revocation must remove access while retaining only minimum consent/audit metadata.

## 10. Auditability, observability and evidence

Machine-origin observations use null actor plus bounded IDs/adapter/source/hashes. Human replay creates attributable audit/internal-outbox evidence. Aggregate health exposes active/revoked/overdue subscriptions, open circuits, stale pending candidates, quarantined candidates and recent failed batches without payload disclosure.

K4 whole-increment candidate `3e0976e8bdd32207bd6314011c26b94fa0f3c118` passed Dependency Review `31556412455`, CodeQL `31556412413`, and CI `31556412468`: Pint 529 files; PHPStan 374/374 zero errors; 429 tests / 9,799 assertions; image/staging/backup/scan success. The P6 acceptance test explicitly proves source revocation plus canonical provenance survival after operational pruning.

K5 has no runtime evidence yet. P0 must itself pass protected documentation/architecture validation before Slice A is authorized.

## 11. Residual risks and explicit non-capabilities

Real source correctness/permission/network/secrets/rate/schema/cursor/revocation behavior remains unvalidated because no concrete production source is approved.

Current runtime still provides no cross-Alliance shared intelligence, public Kingdoms API/webhook, automatic roster/tracking/transfer/diplomacy/contact behavior, machine K3 correction/invalidation, scoring/ranking or recommendations. K5 planning does not change that statement.

Repository K4 acceptance cannot be treated as production source approval while `ingestion_adapters` is empty, and K5 P0 planning cannot be treated as approval of a cross-tenant runtime read path.

## 12. Focused reviews and related documentation

### `KINGDOMS-001`–`003`

Existing focused/whole-increment Kingdoms security reviews remain historical accepted evidence.

### `KINGDOMS-004`

- [K4-P0 security/privacy review](kingdoms-automated-ingestion-p0-security-review.md)
- [K4-P1 ingestion foundation security review](kingdoms-automated-ingestion-foundation-security-review.md)
- [K4-P2 player-promotion security review](kingdoms-automated-ingestion-player-promotion-security-review.md)
- [K4-P3 game-Alliance-promotion security review](kingdoms-automated-ingestion-alliance-promotion-security-review.md)
- [K4-P4 scheduler/replay security review](kingdoms-automated-ingestion-scheduler-security-review.md)
- [K4-P5 operations security/privacy review](kingdoms-automated-ingestion-operations-security-review.md)
- [K4-P6 exit report](../product/kingdoms-automated-ingestion-exit-report.md)
- [Living automated-ingestion contract](../automated-ingestion.md)
- [K4 Slice E validation](../product/kingdoms-automated-ingestion-slice-e-validation.md)
- [Automated ingestion operations](../operations/kingdoms-automated-ingestion.md)

### `KINGDOMS-005` — planning only

- [K5 scope](../product/kingdoms-shared-intelligence-increment.md)
- [K5 implementation plan](../product/kingdoms-shared-intelligence-implementation-plan.md)
- [K5-P0 decisions](../product/kingdoms-shared-intelligence-p0-decisions.md)
- [K5-P0 security/privacy review](kingdoms-shared-intelligence-p0-security-review.md)

- [Security baseline](../../../security/security-baseline.md)
