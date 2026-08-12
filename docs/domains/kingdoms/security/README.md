# Kingdoms security profile

[← Kingdoms domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary security boundary:** Alliance-owned neutral-game workflows and K4 ingestion/promotion/scheduler/operations state, separated from tenant authorization, source secrets, public machine exposure, and decision automation

## 1. Security purpose and scope

Kingdoms protects Alliance-owned roster/history/import/intelligence, transfer/diplomacy/contact state, tracked game-Alliance intelligence, and K4 ingestion. K4 through P5 permits approved generic acquisition plus delegated factual player/game-Alliance observation promotion, scheduled execution/replay, source-revocation reconciliation and bounded operational retention/health only.

## 2. Assets and sensitive data

Tenant-private assets include roster/snapshot/import provenance, transfer/diplomacy/contact data, K4 operational state, opaque source cursor/window identity, bounded scheduling/failure state, normalized candidate facts while retained, and machine provenance on promoted snapshots/observations. Neutral Kingdom/player/game-Alliance identities are reference data, not authorization.

K4 accepts no arbitrary manager source URL/header/cookie/credential or canonical raw external response archive. P5 reduces retained normalized operational payload lifetime while preserving canonical provenance.

## 3. Actors, authentication and authorization

Member reads require `alliance.view`; human Kingdoms management/replay requires `kingdoms.manage` with recent password confirmation where applicable. Machine acquisition/promotion/maintenance uses validated tenant-owned subscription/candidate context and never a fabricated User actor.

Human-only governance remains mandatory for roster/tracking lifecycle, K3 correction/invalidation, diplomacy, contacts, and transfer decisions.

## 4. Tenant and privacy boundaries

Every scheduled run re-resolves owning Alliance/current Kingdom/source version before staging/promotion. Player promotion terminates at an existing owning-Alliance roster relation; game-Alliance promotion terminates at an existing active owning-Alliance tracking relation.

Manager/operations presentation is limited to bounded source/scheduling/state/count/reason metadata. Candidate normalized bodies are not exposed through operational health; secrets/raw responses, diplomacy/contact private text and unrelated manager data remain excluded.

## 5. Trust boundaries and data flows

K4 crosses shared scheduler/queue infrastructure into an approved acquisition adapter, then into bounded staging and accepted K1/K3 promotion actions. Database/domain checks remain authoritative even though queue uniqueness/overlap controls reduce duplicate execution.

P5 adds two maintenance flows: source reconciliation from current repository/operator adapter registry into subscription operational state, and age-based retention from K4 operational rows. Neither flow acquires new business authority.

No concrete production external network/source trust boundary exists because production adapter configuration remains empty.

## 6. Threats, abuse cases and controls

Controls address cross-tenant target confusion, name/tag identity guessing, automatic roster/tracking creation/reactivation, machine correction, fake-human attribution, scheduler duplication, cursor rewind, replay multiplication, stale/revoked context, unbounded retry pressure, failure-detail leakage, excessive operational-data retention, destructive history coupling and premature decision automation.

See [K4 Slice E operations security/privacy review](kingdoms-automated-ingestion-operations-security-review.md).

## 7. Integrity, concurrency and idempotency

Due subscription claims, context validation, source reconciliation and cursor advancement use row locks where concurrent mutation matters. Jobs are unique/overlap-protected but source-window/candidate/promoted-record idempotency remains authoritative for at-least-once delivery.

Cursor advances only after Completed/Partial batch state. Exact replay of a completed window must return the same next cursor. Source removal/version drift disables rather than substitutes. Machine game-Alliance observations remain append-only and cannot carry correction/invalidation instructions.

## 8. Secrets and credential handling

K4 through P5 introduces no source-secret lifecycle. Source credentials/raw responses must not enter Kingdoms tables, normalized facts, logs, audit/outbox or support evidence. A concrete credential/network path remains a separate source-approval requirement.

## 9. Destructive operations, retention and deletion

Operational subscriptions/batches/candidates/scheduling state are not canonical history. Promoted K1/K3 records retain copied bounded provenance without FK dependence on operational K4 rows.

P5 default retention redacts terminal normalized payloads after 30 days, purges promoted/rejected candidates after 90 days, retains quarantined candidates for 180 days, purges terminal batches after 90 days only when candidate-free, and compacts disabled-subscription scheduling/failure state after 30 days while preserving the subscription row.

Operators must not manually delete/rewrite K4 rows to force retries. Retention never deletes promoted canonical observations or rewrites their source provenance.

## 10. Auditability, observability and evidence

Machine-origin observations use null actor plus bounded IDs/adapter/source/hashes. Human replay creates attributable audit/internal-outbox evidence. P5 aggregate health exposes active/revoked/overdue subscriptions, open circuits, stale pending candidates, quarantined candidates and recent failed batches without payload disclosure.

K4-P5 runtime candidate `eb706a96c9c875dd41e932e0691e4258f33e01f1` passed DR `31552113152`, CodeQL `31552113044`, CI `31552113042`: Pint 528; PHPStan 374/374 zero errors; 428 tests / 9,736 assertions; image/staging/backup/scan success.

## 11. Residual risks and explicit non-capabilities

Real source correctness/permission/network/secrets/rate/schema/cursor/revocation behavior remains unvalidated. No public Kingdoms API/webhook, cross-Alliance sharing, automatic roster/tracking/transfer/diplomacy/contact behavior, machine K3 correction/invalidation, scoring/ranking or recommendations are approved.

Repository P5 acceptance cannot be treated as production source approval while `ingestion_adapters` is empty.

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
- [Living automated-ingestion contract](../automated-ingestion.md)
- [K4 Slice E validation](../product/kingdoms-automated-ingestion-slice-e-validation.md)
- [Automated ingestion operations](../operations/kingdoms-automated-ingestion.md)
- [Security baseline](../../../security/security-baseline.md)
