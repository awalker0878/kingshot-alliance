# Kingdoms security profile

[← Kingdoms domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary security boundary:** Alliance-owned neutral-game workflows and K4 ingestion/promotion state, separated from tenant authorization, source secrets, public machine exposure, and decision automation

## 1. Security purpose and scope

Kingdoms protects Alliance-owned roster/history/import/intelligence, transfer/diplomacy state, tracked game-Alliance intelligence, and K4 ingestion. K4 through P3 permits delegated factual player and game-Alliance observation promotion only to existing tenant relationships.

## 2. Assets and sensitive data

Tenant-private assets include roster/snapshot/import provenance, transfer/diplomacy/contact data, K4 operational state, and bounded machine provenance on promoted snapshots/observations. Neutral `Kingdom`, `KingdomPlayer`, and `KingdomAlliance` identities are reference data, not authorization.

K4 accepts no source credentials, arbitrary manager URLs, or canonical raw external responses.

## 3. Actors, authentication and authorization

Member reads require `alliance.view`; human Kingdoms management requires `kingdoms.manage` with recent password confirmation where applicable. Machine promotion uses validated tenant-owned subscription/candidate context and never a fabricated User actor.

Human-only governance remains mandatory for roster/tracking lifecycle, K3 correction/invalidation, diplomacy, contacts, and transfer decisions.

## 4. Tenant and privacy boundaries

Global neutral identity never shares tenant history. Player promotion must terminate at an existing owning-Alliance roster relation; game-Alliance promotion must terminate at an existing active owning-Alliance tracking relation.

Member history omits actor/import/machine provenance. Managers may see bounded source provenance only. Candidate normalized bodies, secrets/raw responses, diplomacy/contact private text, and unrelated manager data remain excluded.

## 5. Trust boundaries and data flows

K4 crosses operational candidate state into accepted K1/K3 canonical history. Both promotion paths recheck subscription/batch/candidate context, current Kingdom, approved adapter version, stable identity, and the owning-Alliance relationship before delegating to accepted business recorders.

No external network/source trust boundary exists because production adapter configuration remains empty.

## 6. Threats, abuse cases and controls

Controls address cross-tenant target confusion, name/tag identity guessing, automatic roster/tracking creation or reactivation, machine correction of accepted K3 history, fake-human attribution, replay multiplication, stale/revoked source context, destructive history coupling, secret leakage, and premature decision automation.

See [K4 Slice B security review](kingdoms-automated-ingestion-player-promotion-security-review.md) and [K4 Slice C security review](kingdoms-automated-ingestion-alliance-promotion-security-review.md).

## 7. Integrity, concurrency and idempotency

K4 promotion locks relevant operational/tenant state, delegates through accepted K1/K3 recorders, records safe promoted identity, and reuses append-history/idempotency semantics. Exact retry returns the existing promoted record; later capture remains distinct history.

Machine game-Alliance observations cannot carry correction/invalidation instructions. Human K3 correction behavior remains unchanged.

## 8. Secrets and credential handling

No K4 source-secret lifecycle is implemented. Source credentials/raw responses must not enter Kingdoms tables, normalized facts, logs, audit/outbox, or support evidence. Concrete credentials remain a separate source-approval requirement.

## 9. Destructive operations, retention and deletion

Operational batches/candidates are not canonical history. Promoted K1/K3 records carry copied bounded provenance without FK dependence on operational K4 rows so future P5 pruning cannot delete accepted history. Until P5, operators must not manually prune/rewrite K4 state to force retries.

## 10. Auditability, observability and evidence

Machine-origin observations/promotion use null actor plus bounded IDs/adapter/source/hashes in internal evidence. K4-P3 runtime candidate `8186af9fd7276a20889ca3a25b80172c6fe824d9` passed DR `31541291512`, CodeQL `31541291470`, CI `31541291501`: Pint 515; PHPStan 365/365 zero errors; 417 tests / 9,628 assertions; image/staging/backup/scan success.

## 11. Residual risks and explicit non-capabilities

Real source correctness/permission/network/secrets/rate behavior remains unvalidated. No scheduler/worker/cursor/retry loop, public Kingdoms API/webhook, cross-Alliance sharing, automatic roster/tracking/transfer/diplomacy/contact behavior, machine K3 correction/invalidation, scoring/ranking, or recommendations are approved through P3.

## 12. Focused reviews and related documentation

### `KINGDOMS-001`–`003`

Existing focused/whole-increment Kingdoms security reviews remain historical accepted evidence.

### `KINGDOMS-004`

- [K4-P0 security/privacy review](kingdoms-automated-ingestion-p0-security-review.md)
- [K4-P1 ingestion foundation security review](kingdoms-automated-ingestion-foundation-security-review.md)
- [K4-P2 player-promotion security review](kingdoms-automated-ingestion-player-promotion-security-review.md)
- [K4-P3 game-Alliance-promotion security review](kingdoms-automated-ingestion-alliance-promotion-security-review.md)
- [Living automated-ingestion contract](../automated-ingestion.md)
- [Alliance intelligence and diplomacy](../alliance-intelligence.md)
- [K4 Slice C validation](../product/kingdoms-automated-ingestion-slice-c-validation.md)
- [Security baseline](../../../security/security-baseline.md)
