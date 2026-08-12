# Kingdoms opt-in shared intelligence product increment

[← Kingdoms product and acceptance evidence](README.md)

**Status:** In progress — `K5-P0` Complete; `K5-P1` selected pending exact transition-head validation  
**Scope ID:** `KINGDOMS-005`  
**Owning domain:** `Kingdoms`  
**Baseline dependency:** Accepted `KINGDOMS-001` through `KINGDOMS-004`  
**Implementation sequence:** [KINGDOMS-005 implementation plan](kingdoms-shared-intelligence-implementation-plan.md)  
**P0 decisions:** [KINGDOMS-005 K5-P0 design decisions](kingdoms-shared-intelligence-p0-decisions.md)  
**P0 exit:** [KINGDOMS-005 K5-P0 exit report](kingdoms-shared-intelligence-p0-exit-report.md)

## 1. Purpose

`KINGDOMS-005` introduces explicit, revocable sharing of selected safe Kingdom game-Alliance observation facts between two platform Alliances that are operating in the same Kingdom.

The increment exists to let cooperating Alliances reduce duplicated intelligence collection while preserving the tenant boundary established by K1-K4. Sharing is a deliberately authorized read path over source-owned canonical Kingdoms facts. It is not a cross-tenant database merge, a public directory, a data marketplace, an automatic diplomacy system, or a new acquisition channel.

## 2. Current governed state

`K5-P0` is Complete at candidate `d9e05fd06bd08050e5489598406cfb556d5bc0ac`, which passed Dependency Review `31557697685`, CodeQL `31557697793`, and CI `31557697725` with Pint 529 files, PHPStan/Larastan 374/374 zero errors, and 429 tests / 9,809 assertions plus frontend, image, staging, backup/restore and scan.

`K5-P1` / Slice A is selected next, but runtime implementation may begin only after the exact containing P0 Complete / P1 Current transition head is independently protected-green.

Slice A is consent-foundation only. No observation/current/history sharing is authorized by P0 completion.

## 3. Initial product boundary

The initial increment is intentionally narrow:

- one platform Alliance is the **source Alliance**;
- one different platform Alliance is the **recipient Alliance**;
- both must be in the same current Kingdom when sharing is activated and used;
- sharing is directional; reverse sharing requires its own explicit agreement;
- both sides must opt in through manager actions;
- the source explicitly selects each tracked game-side Alliance whose safe observations may be shared;
- recipient reads remain read-only views of source-owned accepted observation facts; and
- revocation or context invalidation removes recipient access without copying the source history into recipient-owned canonical tables.

## 4. Shared data allowed in the initial increment

A later accepted shared projection may expose only safe factual game-Alliance observation information already suitable for ordinary Kingdoms intelligence presentation, such as:

- neutral game-Alliance current name/tag needed for display;
- accepted observed name/tag;
- accepted power when present;
- accepted member count when present;
- observation capture time;
- freshness/current-stale-missing presentation derived from accepted observations; and
- the source platform Alliance identity needed to explain who shared the facts.

A later slice may expose a bounded accepted observation history for an explicitly shared target, but only from the source-owned canonical K3 history and only while the share remains authorized.

Slice A does not expose any of these observation facts.

## 5. Data excluded from sharing

The increment does **not** share:

- player roster entries or player snapshots;
- transfer plans, participants, groups, readiness, blockers or completion state;
- diplomacy state, terms, rationale, review dates or transition history;
- diplomacy contacts, handles, verification data or notes;
- tracking manager notes;
- observation correction/invalidation reasons or actors;
- human actor identities or internal management provenance;
- K4 subscriptions, batches, candidates, cursors, adapter keys/versions, source record IDs or ingestion hashes;
- raw source responses or source credentials;
- audit/outbox internals;
- private manager text; or
- any score, ranking, threat/desirability assessment or recommendation.

## 6. Consent model

Sharing requires two-party manager consent.

The source Alliance creates a bounded invitation using `kingdoms.manage` plus recent password confirmation. The invitation is represented by an unguessable one-time bearer token whose secret value is shown only at creation and stored only as a cryptographic hash.

An authorized manager of another active Alliance redeems the invitation under their own current Alliance context. Acceptance binds the recipient Alliance explicitly and activates one directional source→recipient sharing agreement only after same-Kingdom validation.

The source may revoke an active agreement at any time. The recipient may leave/decline an agreement. Revocation/decline cannot grant additional access and must not be blocked by stale source data.

## 7. No Alliance-directory expansion

K5 does not create a public or member-visible platform Alliance directory merely to locate sharing partners.

The initial invitation-token handshake avoids exposing tenant IDs, manager lists, contact directories or searchable Alliance enumeration as a prerequisite for sharing. The product may display the source Alliance identity to the recipient after the recipient deliberately redeems the invitation, and each side may see the counterpart identity for its own accepted/pending agreement management.

## 8. Same-Kingdom boundary

A share captures one Kingdom context.

Activation and every shared-data read must verify:

- the source Alliance still exists and is authorized for the captured Kingdom;
- the recipient Alliance still exists and is authorized for the same captured Kingdom; and
- every shared `TrackedKingdomAlliance` belongs to that captured Kingdom.

If either platform Alliance changes Kingdom, recipient access fails closed. K5 does not silently retarget an agreement to the new Kingdom and does not use shared intelligence as a bridge across Kingdoms.

P0 locks recovery after drift as a new deliberate agreement, not implicit reactivation.

## 9. Source ownership and recipient read semantics

The source Alliance remains the sole owner of its tracking relationship and accepted observation history.

The recipient receives no ownership over source observations and cannot:

- edit or invalidate source observations;
- alter source tracking state;
- change source diplomacy/contact state;
- request K4 replay or source acquisition;
- copy source observations automatically into recipient canonical history;
- use the share to create/reactivate local tracking automatically; or
- reshare the source's data to another Alliance.

Recipient queries in later slices must cross the tenant boundary only through an active authorized share agreement and explicit shared target selection.

## 10. Correction, invalidation and freshness behavior

Shared reads are projections over the source's currently accepted canonical facts.

If the source later invalidates/corrects an observation, the recipient must not continue receiving the invalidated fact as accepted intelligence. K5 does not create an immutable recipient copy that outlives the source's canonical correction semantics.

Missing values remain distinct from zero. Freshness is descriptive only and creates no automatic decision or diplomacy state.

## 11. Authorization and member visibility

Share invitation creation, acceptance/decline, revocation and shared-target management require `kingdoms.manage` plus recent password confirmation.

Once a later sharing slice is active, the recipient may expose the approved safe shared projection to members with `alliance.view`, because the projection is restricted to member-safe factual fields. Management metadata about invitations, consent history, counterpart management state and revocation remains manager-only.

Submitted share, invitation, source-tracking and target identifiers must always be re-resolved beneath the active Alliance and applicable source/recipient relationship.

## 12. Audit and event boundary

Material consent and shared-target changes create attributable Audit/internal outbox evidence with safe IDs, states and timestamps only.

Invitation bearer secrets, private text, contact data and source ingestion details must never be copied into audit/outbox payloads or ordinary logs.

All K5 events remain `kingdoms.*` internal-only under the existing Integrations exclusion. K5 creates no public Kingdoms API scope, inbound endpoint, external webhook schema or public sharing feed.

## 13. Retention and revocation principle

K5 persists the minimum consent/grant metadata required for authorization, auditability and historical explanation.

Recipient access to shared observation payloads is authorization-dependent and must stop immediately when the agreement/item becomes unauthorized. K5 avoids materializing a second long-lived copy of source canonical observation history solely for sharing.

Expired invitation secrets and operational token material require bounded retention; accepted/revoked agreement metadata may be retained as audit/history without retaining shareable observation payloads.

## 14. Delivery slices

- `K5-P0` — **Complete**: consent, same-Kingdom, data-classification, revocation and non-capability contract locked.
- `K5-P1` / Slice A — **Current / selected pending transition-head validation**: sharing agreement foundation: hashed invitation, accept/decline/revoke, directional tenancy and same-Kingdom enforcement; no observation sharing.
- `K5-P2` / Slice B — Planned: explicit shared-target selection plus recipient current-fact projection.
- `K5-P3` / Slice C — Planned: bounded accepted shared history, freshness and correction/invalidation projection semantics.
- `K5-P4` / Slice D — Planned: first-party source/recipient UX, audit/internal-event evidence, drift/revocation hardening and accessibility.
- `K5-P5` / Slice E — Planned: privacy/retention/operations/capacity hardening.
- `K5-P6` — Planned: whole-increment acceptance.

## 15. Explicitly out of scope

`KINGDOMS-005` does not approve or reserve hidden runtime placeholders for:

- player/roster/snapshot sharing;
- transfer-plan sharing or transfer automation;
- diplomacy/contact sharing;
- automatic diplomacy/negotiation;
- public Alliance or contact directories;
- cross-Kingdom sharing;
- transitive/recursive reshare;
- anonymous/global feeds;
- public Kingdoms API/webhook sharing contracts;
- source acquisition/scraping/OCR/bots;
- arbitrary tenant-to-tenant file export;
- threat/desirability/risk/punitive scoring;
- ranking/recommendations/battle prediction; or
- AI-generated management/enforcement decisions.

## 16. Acceptance rule

K5 is implemented only through the gated plan. Every slice must preserve K1-K4 tenant ownership, stable-ID identity, K3 history semantics, K4 source isolation and existing public integration exclusions.

P0 has no runtime impact. Slice A may begin only after the exact containing P0 Complete / P1 Current status head passes Dependency Review, CodeQL and full CI. Whole-increment acceptance at K5-P6 remains repository/product acceptance only; production deployment/cutover remains separately governed.