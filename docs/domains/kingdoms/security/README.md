# Kingdoms security profile

[← Kingdoms domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current — `KINGDOMS-004` Accepted; `KINGDOMS-005` through K5-P5 retention/capacity hardening validated  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary security boundary:** Alliance-owned Kingdoms data, K4 source isolation, and K5 directional consent/explicit grants/current+bounded-history reads/presentation plus bounded operational retention separated from private tenant data and public exposure

## 1. Security purpose and scope

Kingdoms protects roster/history/import/intelligence, transfer/diplomacy/contact state, game-Alliance observations and K4 ingestion. K5 now includes explicit two-party consent, per-target grants, bounded safe current facts, bounded accepted history, complete first-party member/manager presentation and bounded operational retention/capacity hardening.

K5 remains source-owned, recipient-first authorized, explicitly granted, same-Kingdom, non-copying and non-transitive. `K5-P6` whole-increment acceptance is selected but not writable until the P5 Complete / P6 Current transition gate is green.

## 2. Assets and sensitive data

Tenant-private assets remain roster/player data, transfer state, tracking notes, diplomacy/contact data, correction rationale/actors/linkage, source tracking/stable game identifiers, observation IDs, K4 operational/source provenance and source secrets/raw responses.

K5 persists consent/grant metadata only. Pending invitations persist a one-way token hash; accepted/declined/revoked invitations erase that hash. Source observations remain canonical in K3 tables; no recipient observation-history copy is stored.

History continuation cursors are encrypted transient read state and are not persisted as business data.

P5 retention applies only to eligible old K5 operational sharing rows. It never targets active shares/grants, source tracking/observations, Audit events or outbox messages.

## 3. Actors, authentication and authorization

Member-safe existing reads use `alliance.view`; Kingdoms management uses `kingdoms.manage`. K5 consent and target mutations require recent password confirmation plus domain-level `kingdoms.manage` where defined.

Source invitation/revoke/target actions are source-tenant scoped. Recipient accept/decline/leave acts under the recipient active Alliance. Recipient current/history facts authorize from recipient Alliance → active agreement → active target grant → valid source tracking/context.

The manager workspace requires `kingdoms.manage`; ordinary members can receive only the accepted safe current/history projections. Every history page repeats live authorization; a cursor alone never grants access.

The retention command is an internal operator surface. It cannot create, grant or reactivate sharing and does not bypass live read authorization.

There is no anonymous/public K5 data interface.

## 4. Tenant and privacy boundaries

Agreement acceptance requires different source/recipient Alliances whose current Kingdoms equal the captured invitation Kingdom. Target grants require source-owned active tracking in that same Kingdom.

Global neutral `KingdomAlliance` identity remains reference data only and grants no K5 access.

Current/history projections whitelist source Alliance ID/name, neutral/current game-Alliance name/tag, accepted observed name/tag, optional power/member count, capture time and descriptive freshness only.

Manager props are bounded consent/grant/display metadata only. Source tracking IDs, stable game IDs, observation IDs, manager notes, diplomacy/contact data, roster/transfer data, actors/reasons/correction linkage, K4 provenance and private free text stay source-private.

Counterpart Audit records use null actors where necessary to prevent cross-tenant manager User-ID disclosure.

Retention cleanup does not change tenant ownership or move canonical observations between Alliances.

## 5. Trust boundaries and data flows

P1 flow remains source manager → one-time invitation → authorized recipient manager → active directional agreement.

P2 adds source manager → explicit source-owned target grant → recipient-first safe current-fact projection.

P3 adds recipient-safe accepted history for one active explicit target, using an encrypted target-bound continuation cursor and a fixed history snapshot.

P4 adds authenticated first-party presentation: member-safe current/history page and manager-only consent/grant workspace. Invitation plaintext is returned only from the creation POST and held in component memory; it is never an Inertia/session prop.

P5 adds internal scheduled/operator cleanup for old K5 operational metadata only. Candidate IDs are bounded and destructive predicates are rechecked at deletion time.

An active agreement without a grant exposes nothing. A grant does not transfer ownership. Current/history reads do not create reusable recipient canonical data or an upstream reshare object.

K4 source/network trust boundaries remain unchanged; production adapters remain empty.

## 6. Threats, abuse cases and controls

Controls address tenant enumeration, token leakage/replay, secret-derived data retention, self-share, different-Kingdom activation, cross-tenant share/tracking/target substitution, duplicate active agreement, wildcard sharing, source-model/page-prop over-serialization, private/K4 field leakage, copied recipient history, reshare/confused-deputy use, stale access after remove/revoke/drift, implicit access resume after returning to a Kingdom, unbounded history extraction, cursor tampering, stale-cursor reuse after authorization loss, UI reopening of arbitrary old history windows, destructive retention of active/canonical state, and stale retention-candidate deletion after state change.

History cursor state is encrypted/authenticated, target-bound and capped to one 250-record traversal. The first-party UI exposes no arbitrary user-controlled `asOf` selector.

Retention controls use one total 1–2000 work budget, state/timestamp eligibility, delete-time predicate rechecks, active/canonical exclusions and durable Audit/outbox preservation.

Tests prove invitation creation alone creates zero grants, explicit-target-only visibility, no recipient canonical copy, no reshare, bounded accepted history, manager/member prop isolation, invitation plaintext non-persistence, bounded/idempotent retention and no public K5 data API.

See [P0 security review](kingdoms-shared-intelligence-p0-security-review.md), [Slice A security review](kingdoms-shared-intelligence-foundation-security-review.md), [Slice B security review](kingdoms-shared-intelligence-current-facts-security-review.md), [Slice C security review](kingdoms-shared-intelligence-history-security-review.md), [Slice D presentation security review](kingdoms-shared-intelligence-presentation-security-review.md), and [Slice E retention/capacity security review](kingdoms-shared-intelligence-retention-security-review.md).

## 7. Integrity, concurrency and idempotency

Successful consent redemption remains single-use. Accept, decline and revoke erase the stored invitation hash immediately. Target add is idempotent while active; a removed target requires deliberate re-grant.

Relevant mutation locking aligns to Alliance(s) → share → target where Kingdom drift can race with consent/grant changes. Source/recipient Alliances are locked in deterministic ID order for acceptance/grant operations.

Source invalidation remains canonical: invalidated observations stop participating in current/history immediately; corrected replacements participate only as their own accepted observations.

History ordering is deterministic by `captured_at DESC, id DESC`; encrypted cursor state captures one target, fixed `asOf`, keyset position and accepted-record count.

Supported Kingdom drift terminalizes affected agreements, preventing silent reactivation if the Alliance later returns.

P5 retention is idempotent after eligible rows are gone. It first selects bounded candidate IDs and then repeats state/cutoff eligibility in the delete statement so a row that becomes non-eligible before deletion is preserved.

## 8. Secrets and credential handling

K5 invitation plaintext is generated from 32 random bytes, shown only in the authenticated creation response/component memory and persisted only as SHA-256 hash while pending. Default expiry is 72 hours and repository-bounded to 1–168 hours.

Accept, decline and revoke clear the persisted hash. The forward P4 schema migration makes the hash nullable; rollback uses deterministic retired placeholders only to satisfy the accepted P1 non-null schema and never reconstructs a credential. Reapply restores terminal null values.

Invitation plaintext/hash material is excluded from normal page props, Audit/outbox/logging. History cursors are encrypted/authenticated and must not be logged or exposed as public reusable credentials.

P5 retention is not delayed secret cleanup: terminal/consumed hashes are already erased immediately. Expired pending invitation rows become operational-retention candidates only after the configured post-expiry window.

K5 adds no external provider credential lifecycle.

## 9. Destructive operations, retention and deletion

Revoke/decline/leave/target removal reduce authorization only; they do not delete or mutate source canonical observations.

P5 adds bounded cleanup of:

- pending invitation rows whose expiry is older than the configured 30-day default post-expiry window;
- declined/revoked share rows older than the configured 180-day default terminal window; and
- removed target rows older than the configured 90-day default removal window.

One invocation owns one total work budget, default 500 and clamped to 1–2000. The scheduled command runs daily at 04:30 on one server without overlap.

Active agreements/grants and canonical tracking/observations are ineligible. Audit/outbox evidence is not purged by this action. Delete-time state/cutoff rechecks preserve rows that become non-eligible after candidate selection.

Operators must not reactivate/retarget agreements or grants, fabricate timestamps or delete canonical/Audit/outbox state by database edit.

See the [shared-intelligence retention runbook](../operations/kingdoms-shared-intelligence-retention.md).

## 10. Auditability, observability and evidence

K5 events use safe share/target/source/recipient/Kingdom/state/timing/reason metadata. Invitation plaintext/hash material and private source observation content remain excluded from durable operational evidence.

Target/context events remain internal and external-webhook ineligible. Current/history/presentation adds no public mutation event; payload bodies and cursors are not Audit/outbox payloads.

Retention command evidence may include release identity, timestamp/environment, bounded limit, and returned per-class/total counts. It must not include secret material or shared observation payload bodies.

P5 runtime candidate `b47f639a275652590304fccef051f78997a0153c` passed Dependency Review `31570931190`, CodeQL `31570931290`, and CI `31570931267`: Pint 559 files, PHPStan/Larastan 394/394 zero errors, 451 tests / 10,230 assertions, clean migrations, frontend lint/format/type/build, immutable image, staging, backup/restore, scan and cleanup.

## 11. Residual risks and explicit non-capabilities

P6 must independently re-prove the whole K5 seam on one exact candidate, including unrelated-tenant failure, immediate authorization loss, retention/canonical preservation and public/non-sharing exclusions.

Current runtime provides no arbitrary historical-window selection, roster/player sharing, transfer sharing, diplomacy/contact sharing, cross-Kingdom sharing, public API/webhook, tenant directory, recipient copy/reshare, scoring/ranking/recommendations or automatic decisions.

P5 introduces no authorization cache/materialized recipient read store. Any future caching/materialization requires separate review.

## 12. Focused reviews and related documentation

### `KINGDOMS-001`–`004`

Existing focused/whole-increment Kingdoms security reviews remain historical accepted evidence.

### `KINGDOMS-005`

- [K5-P0 security/privacy review](kingdoms-shared-intelligence-p0-security-review.md)
- [K5-P1 sharing foundation security review](kingdoms-shared-intelligence-foundation-security-review.md)
- [K5-P2 current-facts security review](kingdoms-shared-intelligence-current-facts-security-review.md)
- [K5-P3 shared-history security review](kingdoms-shared-intelligence-history-security-review.md)
- [K5-P4 presentation security review](kingdoms-shared-intelligence-presentation-security-review.md)
- [K5-P5 retention/capacity security review](kingdoms-shared-intelligence-retention-security-review.md)
- [K5-P0 decisions](../product/kingdoms-shared-intelligence-p0-decisions.md)
- [K5-P0 exit report](../product/kingdoms-shared-intelligence-p0-exit-report.md)
- [K5-P1 validation](../product/kingdoms-shared-intelligence-slice-a-validation.md)
- [K5-P2 validation](../product/kingdoms-shared-intelligence-slice-b-validation.md)
- [K5-P3 validation](../product/kingdoms-shared-intelligence-slice-c-validation.md)
- [K5-P4 validation](../product/kingdoms-shared-intelligence-slice-d-validation.md)
- [K5-P5 validation](../product/kingdoms-shared-intelligence-slice-e-validation.md)
- [K5-P5 retention operations](../operations/kingdoms-shared-intelligence-retention.md)
- [Living shared-intelligence contract](../shared-intelligence.md)
- [K5 implementation plan](../product/kingdoms-shared-intelligence-implementation-plan.md)

- [Security baseline](../../../security/security-baseline.md)
