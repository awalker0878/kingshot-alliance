# KINGDOMS-005 Slice A sharing foundation security review

[← Kingdoms security profile](README.md)

**Scope:** `K5-P1` / Slice A — directional sharing consent/agreement foundation  
**Status:** Complete  
**Runtime candidate:** `9ef1d46b1db69708d575e82d8548145cf7769e68`

## 1. Review purpose

Slice A is the first K5 runtime state that can later authorize a cross-tenant read. It therefore implements only the consent foundation and deliberately stops before any tracked-target or observation disclosure exists.

The primary security decision is that possessing or activating an agreement does not itself expose tenant intelligence. A later Slice B must add an independently authorized explicit target and safe projection.

## 2. Invitation-secret controls

Invitation secrets are generated from 32 cryptographically random bytes and encoded as 64 lowercase hexadecimal characters. Only SHA-256 hashes are persisted; the model hides the hash from ordinary serialization.

Plaintext is returned only in the authenticated source creation response, then is not retained in K5 persistence, Audit or outbox payloads. Invitations have bounded expiry, defaulting to 72 hours and clamped by repository configuration to 1–168 hours.

The token is a one-time human consent bootstrap, not an API credential. Exact replay after successful acceptance/decline fails closed.

## 3. Authentication and authorization

Every P1 HTTP mutation is under the existing authenticated/verified active-Alliance context and `password.confirm` middleware. Domain actions independently require `kingdoms.manage`.

This layered design prevents route placement from becoming the sole authorization control and protects direct action use in tests/other first-party code.

There is no P1 GET/list/current/history sharing route and no public API/webhook endpoint.

## 4. Tenant binding and confused-deputy controls

Source invitation creation re-resolves/locks the source Alliance and captures its current Kingdom.

Acceptance resolves the pending share by token hash and locks it. Source and recipient platform Alliance rows are then locked in deterministic ID order. Acceptance requires:

- source and recipient are different Alliances;
- source still exists;
- recipient is the active tenant invoking acceptance;
- source current Kingdom equals captured Kingdom;
- recipient current Kingdom equals captured Kingdom; and
- no other active directional source→recipient agreement exists for that Kingdom.

Global neutral Kingdom/game-Alliance identity is not involved in P1 authorization.

## 5. Token-consumption and failure semantics

Failed acceptance does not consume the invitation because token-use and active-state mutation occur inside the same transaction after all validations succeed.

Expired/used/unknown tokens return the same bounded invalid-token validation path rather than revealing invitation state. Different-Kingdom and self-share rejection leave the pending token unconsumed so the source may still intentionally share with a valid partner until expiry/revocation.

Decline consumes a valid invitation because it is an explicit terminal recipient decision.

## 6. Access-reducing transitions and drift

Source revoke, recipient decline and recipient leave reduce or prevent future authorization. They intentionally do not require same-Kingdom context to remain valid.

This means an agreement cannot become undeletable merely because source/recipient context drifted. Source revoke resolves by source tenant ownership; recipient leave resolves by recipient tenant ownership. Unrelated submitted share IDs fail closed.

Terminal declined/revoked state is never reactivated by P1 actions. Future collaboration requires a new invitation/agreement.

## 7. Cross-tenant actor privacy

A successful acceptance is a human action by the recipient manager. The recipient tenant's Audit entry remains attributable to that manager.

The corresponding source-tenant acceptance evidence intentionally records a null actor instead of the recipient manager's global User ID. The source receives safe agreement/source/recipient/Kingdom/state metadata without cross-tenant manager identity disclosure.

This preserves explainability while avoiding unnecessary tenant-member metadata leakage.

## 8. Persistence minimization

The only P1 table is `kingdom_intelligence_shares`, which stores consent/authorization metadata.

It does not store:

- tracked game-Alliance share items;
- observation IDs or payload/history;
- player/roster/transfer/diplomacy/contact data;
- tracking manager notes;
- K4 adapter/subscription/batch/candidate/cursor/provenance; or
- raw source credentials/responses.

There is no recipient copy of source canonical observations in Slice A.

## 9. Event and logging boundary

Consent events remain internal `kingdoms.*` events and therefore fall under the existing external-webhook exclusion.

Safe evidence includes share/source/recipient/Kingdom/state/timing identifiers. Invitation plaintext and later-shareable observation payloads are excluded.

No background processing or operator command is added in P1, reducing opportunities for token or tenant state to be exposed through scheduler diagnostics.

## 10. Concurrency and duplicate-agreement behavior

Acceptance uses row locks on the pending share and deterministic source/recipient Alliance locking. It checks for another active directional agreement before activation.

Exact token retry cannot activate twice because the first successful transaction moves the share from pending and sets `invitation_used_at`. Source revoke/recipient leave also use row locks and terminal idempotency semantics.

The P1 contract does not claim database-level uniqueness across all possible concurrent independent invitation acceptances; the transactional action is the accepted application boundary. If later concurrency/load evidence identifies a stronger database constraint requirement, it must preserve terminal-history semantics and be added before wider sharing reads.

## 11. Residual risk and Slice B gate

P1 still exposes no shared observation data, so the high-risk recipient data-projection boundary remains unimplemented.

Before Slice B can be accepted, its security review/tests must prove recipient-first authorization through an active/context-valid agreement plus an explicitly selected source-owned target, strict safe-field projection, no private/K4 data leakage, immediate revoke/drift/item-removal failure, no recipient canonical copy and no reshare.

## 12. Validation evidence

Runtime candidate `9ef1d46b1db69708d575e82d8548145cf7769e68` passed Dependency Review `31559012856`, CodeQL `31559012854`, and full CI `31559012861`: Pint 541 files, PHPStan/Larastan 384/384 zero errors, 434 tests / 9,911 assertions, clean migrations, frontend/build, immutable image, staging, backup/restore, image scan and cleanup.

Focused tests prove token secrecy/expiry/single-use, password/permission enforcement, same-Kingdom/self/duplicate rejection, access-reducing drift-tolerant terminal transitions, unrelated-tenant share-ID rejection, absence of shared observation routes/schema and migration rollback/reapply.

See [Slice A validation](../product/kingdoms-shared-intelligence-slice-a-validation.md) and [living shared-intelligence contract](../shared-intelligence.md).
