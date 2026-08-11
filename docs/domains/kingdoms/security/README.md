# Kingdoms security profile

[← Kingdoms domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary security boundary:** Alliance-owned neutral-game workflows and K4 ingestion/promotion state, separated from tenant authorization, source secrets, public machine exposure, and decision automation

## 1. Security purpose and scope

Kingdoms protects Alliance-owned roster/history/import/intelligence, transfer/diplomacy state, tracked game-Alliance intelligence, and K4 ingestion. K4-P2 extends the accepted P1 control plane with existing-roster player-snapshot promotion only.

## 2. Assets and sensitive data

Tenant-private assets include roster/snapshot/import provenance, transfer/diplomacy/contact data, K4 operational state, and bounded machine snapshot provenance. Neutral `Kingdom`, `KingdomPlayer`, and `KingdomAlliance` identities are reference data, not authorization.

K4 still accepts no source credentials, arbitrary manager URLs, or canonical raw external responses.

## 3. Actors, authentication and authorization

Member reads require `alliance.view`; human Kingdoms management requires `kingdoms.manage` with recent password confirmation where applicable. Machine promotion uses validated tenant-owned subscription/candidate context and never a fabricated User actor.

## 4. Tenant and privacy boundaries

Global neutral identity never shares tenant history. P2 stable-ID resolution must end at an existing roster relation in the owning Alliance. Member snapshot output omits actor/import/machine provenance; manager history may expose only bounded source provenance.

## 5. Trust boundaries and data flows

P2 crosses K4 operational candidate state into K1 canonical snapshot history. The boundary rechecks subscription/batch/candidate context, current Kingdom, approved adapter version, stable player identity, owning-Alliance roster target, and shared snapshot validation before mutation.

No external network/source trust boundary exists because production adapter configuration remains empty.

## 6. Threats, abuse cases and controls

Controls address cross-tenant target confusion, name/tag identity guessing, automatic enrollment, fake-human attribution, replay multiplication, stale/revoked source context, destructive history coupling, secret leakage, and premature decision automation.

See [K4 Slice B security review](kingdoms-automated-ingestion-player-promotion-security-review.md).

## 7. Integrity, concurrency and idempotency

K4-P2 locks relevant operational/tenant rows, delegates through the accepted snapshot recorder, records safe promoted identity, and reuses append-history/idempotency semantics. Exact retry returns the existing snapshot; later capture time remains distinct history.

## 8. Secrets and credential handling

No K4 source secret lifecycle is implemented. Source credentials/raw responses must not enter Kingdoms tables, normalized facts, logs, audit/outbox, or support evidence. Concrete credentials remain a separate source-approval requirement.

## 9. Destructive operations, retention and deletion

Operational batches/candidates are not canonical history. Promoted snapshots carry copied bounded provenance without FK dependence on operational K4 rows so future P5 pruning cannot delete accepted history. Until P5, operators must not manually prune/rewrite K4 rows to force retries.

## 10. Auditability, observability and evidence

Machine-origin snapshots/promotion use null actor plus bounded IDs/adapter/source/hashes in internal evidence. Runtime candidate `37a7df3e0e88e2303f3c8fa74efaaed0b85fbd4f` passed DR `31538958810`, CodeQL `31538958745`, CI `31538958920`: Pint 512; PHPStan 364/364 zero errors; 412 tests / 9,564 assertions; image/staging/backup/scan success.

## 11. Residual risks and explicit non-capabilities

Real source correctness/permission/network/secrets/rate behavior remains unvalidated. No scheduler/worker, game-Alliance promotion, public Kingdoms API/webhook, cross-Alliance sharing, automatic roster/tracking, transfer/diplomacy automation, scoring/ranking, or recommendations are approved by P2.

## 12. Focused reviews and related documentation

### `KINGDOMS-001`–`003`

Existing focused/whole-increment Kingdoms security reviews remain historical accepted evidence.

### `KINGDOMS-004`

- [K4-P0 security/privacy review](kingdoms-automated-ingestion-p0-security-review.md)
- [K4-P1 ingestion foundation security review](kingdoms-automated-ingestion-foundation-security-review.md)
- [K4-P2 player-promotion security review](kingdoms-automated-ingestion-player-promotion-security-review.md)
- [Living automated-ingestion contract](../automated-ingestion.md)
- [Player snapshots](../snapshots.md)
- [K4 Slice B validation](../product/kingdoms-automated-ingestion-slice-b-validation.md)
- [Security baseline](../../../security/security-baseline.md)
