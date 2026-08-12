# Opt-in shared Kingdom intelligence

[← Kingdoms domain](README.md)

**Document type:** Living capability contract  
**Status:** Current — `KINGDOMS-005` through `K5-P1` / Slice A runtime validated; consent foundation only  
**Owning domain:** `Kingdoms`

## 1. Purpose

Opt-in shared Kingdom intelligence provides a deliberately authorized source-Alliance → recipient-Alliance consent boundary for later sharing of selected safe game-Alliance observation facts.

`K5-P1` establishes only the sharing agreement and invitation lifecycle. It does **not** share tracked game Alliances, current facts, observation history or any other tenant intelligence yet.

## 2. Scope and non-scope

Current scope is limited to directional sharing invitations/agreements: source invitation creation, recipient acceptance/decline, source revocation and recipient leave.

Out of scope at P1: shared-target selection; any recipient observation/current/history read; roster/player/snapshot sharing; transfer sharing; diplomacy/contact sharing; cross-Kingdom sharing; tenant directory/search; reshare; public API/webhooks; scoring/ranking/recommendations; and automatic decisions.

## 3. Model and state

`KingdomIntelligenceShare` is a tenant-consent record that captures:

- source platform Alliance;
- optional recipient platform Alliance once redeemed/declined;
- one captured Kingdom;
- hash-only invitation token identity;
- pending/active/declined/revoked state;
- bounded invitation expiry/use timestamps; and
- human actor/timestamps required to explain consent transitions.

There is no P1 shared-target table and no copied observation payload/history on the sharing agreement.

## 4. Invariants

1. Sharing is directional; reverse sharing requires another agreement.
2. Source and recipient must be different Alliances.
3. Acceptance requires both Alliances to still be in the invitation's captured Kingdom.
4. Invitation secrets are 32 cryptographically random bytes represented as 64 lowercase hexadecimal characters; only SHA-256 hash is persisted.
5. Invitation plaintext is returned only at creation and excluded from model serialization, Audit and outbox payloads.
6. Invitation expiry defaults to 72 hours and is repository-bounded to 1–168 hours.
7. Invitation redemption is single-use.
8. Terminal declined/revoked agreements do not reactivate.
9. Revoke/leave/decline remain available as access-reducing actions even when Kingdom context later drifts.
10. One active directional source→recipient agreement per captured Kingdom is enforced by the acceptance action.
11. Global neutral `KingdomAlliance` identity grants no sharing authorization.
12. P1 exposes no shared observation/current/history read path.
13. P1 creates no tenant directory or recipient discovery surface.
14. All K5 events remain internal/public-webhook ineligible.

## 5. Workflows

A source manager creates an invitation under their active Alliance. The action re-resolves/locks the source Alliance, requires a current Kingdom, issues the one-time token and persists only its hash plus consent metadata.

A recipient manager submits the token under their own active Alliance. Acceptance locks the pending share plus source/recipient Alliance rows in deterministic order, rejects self-sharing, requires both current Kingdoms to equal the captured Kingdom, rejects a duplicate active directional agreement, binds the recipient and consumes the invitation.

A recipient may decline an unused unexpired invitation without needing a same-Kingdom match because decline only reduces potential access. A source may revoke a pending or active agreement and an active recipient may leave; these terminal access-reducing transitions remain valid after Kingdom drift.

## 6. Authorization, tenancy and privacy

All P1 HTTP mutations are authenticated first-party routes inside the active Alliance context and the existing `password.confirm` group. Domain actions independently require `kingdoms.manage`.

Source-side mutations resolve shares under `source_alliance_id`; recipient leave resolves under `recipient_alliance_id`. Submitted share IDs from unrelated Alliances therefore fail closed.

When acceptance is recorded for the source tenant, Audit uses a null actor rather than exposing the recipient manager's cross-tenant User ID. The recipient tenant's acceptance audit remains attributable to its own manager.

No observation payload, tracking notes, diplomacy/contact data, roster/player data, K4 provenance, source secret or private text crosses the sharing boundary in P1.

## 7. Persistence and query semantics

`kingdom_intelligence_shares` is the only K5 runtime table in P1. It stores consent/authorization metadata, not shared intelligence payloads.

The migration depends on `alliances` and `kingdoms` and participates in the full Kingdoms rollback/reapply chain after the K4 scheduling migration. The invitation token hash is hidden from ordinary model serialization.

P1 has no recipient shared-data query. Later shared-data queries must remain recipient-first and authorization-joined through an active/context-valid agreement plus an explicitly shared target.

## 8. Events/integrations/background processing

P1 emits only internal consent lifecycle evidence:

- `kingdoms.shared_intelligence_invitation_created`;
- `kingdoms.shared_intelligence_accepted`;
- `kingdoms.shared_intelligence_declined`;
- `kingdoms.shared_intelligence_revoked`; and
- `kingdoms.shared_intelligence_left`.

Audit/outbox metadata contains safe share/source/recipient/Kingdom/state/timing identifiers only. Invitation plaintext is excluded.

P1 adds no job, scheduler, operator command, public API, inbound endpoint, public sharing feed or external webhook contract.

## 9. Failure, idempotency and concurrency

Invalid/expired/used tokens fail closed. Different-Kingdom acceptance and self-share fail without consuming the invitation. Duplicate active directional agreements fail closed.

Acceptance locks source/recipient Alliance rows in deterministic ID order. Revoke and leave are tenant-scoped row-locked transitions. Repeated revoke/leave on already terminal state returns terminal state without reactivation.

A failed acceptance does not consume the token unless the transaction successfully commits the active agreement.

## 10. Operations and observability

P1 introduces no operational monitoring surface or background workload. Safe diagnostics are limited to share ID, source/recipient Alliance IDs where authorized, captured Kingdom, state and consent timestamps.

Do not log invitation plaintext. Do not use database edits to accept, retarget, reactivate or transfer an agreement. Later retention/cleanup of expired/used invitation material is owned by K5-P5.

## 11. Tests and validation

P1 runtime candidate `9ef1d46b1db69708d575e82d8548145cf7769e68` passed Dependency Review `31559012856`, CodeQL `31559012854`, and CI `31559012861`.

CI passed Pint for 541 files, PHPStan/Larastan 384/384 with zero errors, 434 tests / 9,911 assertions, frontend/build, clean PostgreSQL migrations including `2026_08_12_010000_create_kingdom_intelligence_shares`, immutable image build, ephemeral staging, backup/restore, image scan and cleanup.

Focused tests prove hash-only bounded invitation storage, no P1 sharing payload/schema/read route, password/permission enforcement, same-Kingdom acceptance, self/duplicate/expired rejection, single-use token behavior, different-Kingdom decline, cross-tenant share-ID rejection, drift-tolerant revoke/leave and full Kingdom migration rollback/reapply.

## 12. Related documentation

- [KINGDOMS-005 product increment](product/kingdoms-shared-intelligence-increment.md)
- [KINGDOMS-005 implementation plan](product/kingdoms-shared-intelligence-implementation-plan.md)
- [K5-P0 decisions](product/kingdoms-shared-intelligence-p0-decisions.md)
- [K5-P0 exit report](product/kingdoms-shared-intelligence-p0-exit-report.md)
- [K5 Slice A validation](product/kingdoms-shared-intelligence-slice-a-validation.md)
- [K5 Slice A security review](security/kingdoms-shared-intelligence-foundation-security-review.md)
- [Alliance intelligence and diplomacy](alliance-intelligence.md)
- [Kingdoms interfaces](interfaces/README.md)
- [Kingdoms testing/evidence](testing/README.md)
