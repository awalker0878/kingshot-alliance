# KINGDOMS-005 Slice B current-facts security review

[← Kingdoms security profile](README.md)

**Scope:** `K5-P2` / Slice B — explicit shared targets and safe recipient current facts  
**Status:** Complete  
**Runtime candidate:** `1a022e909cd246197510449a761a4856ce12b118`

## 1. Review purpose

Slice B is the first K5 runtime that discloses source-owned intelligence to another platform Alliance. The security boundary therefore depends on explicit target grants, recipient-first authorization, strict safe-field projection, source ownership, immediate authorization loss and non-transitive sharing.

P2 is acceptable because it introduces a bounded live projection rather than copying source canonical observations into recipient-owned state.

## 2. Authorization bridge

A neutral `KingdomAlliance` or source tracking ID is never sufficient authority.

Recipient current reads require all of the following:

- the caller's active recipient Alliance;
- an active directional agreement whose `recipient_alliance_id` matches that tenant;
- source and recipient Alliances still active;
- both current Kingdoms equal the agreement's captured Kingdom;
- an explicit active grant row beneath that agreement;
- source tracking belongs to the agreement source Alliance;
- source tracking belongs to the captured Kingdom; and
- source tracking remains active.

The recipient-first SQL join is the authorization path. Global neutral identity is reference/display data only.

## 3. Target-grant mutation boundary

Only a source manager with `kingdoms.manage` plus recent password confirmation can add/remove target grants.

Add requires active valid source/recipient context and re-resolves tracking beneath the source Alliance. Submitted tracking IDs from another tenant fail closed. An active agreement does not create wildcard sharing; every target is individually selected.

Removal is deliberately available as an access-reducing operation even when the agreement/context has since become stale. Removed grants stop authorization immediately and retain only grant-history metadata.

## 4. Safe-field projection and data minimization

P2 constructs a fixed projection rather than serializing source Eloquent models/resources wholesale.

Allowed fields are limited to:

- source Alliance ID/name;
- neutral/current game-Alliance name/tag;
- accepted observed name/tag;
- optional power/member count;
- capture time; and
- current/stale/missing freshness.

Excluded fields include source tracking ID, stable game-side ID, tracking notes, diplomacy/contact state and private text, roster/player/transfer state, observation actor/correction/invalidation reason, K4 adapter/subscription/batch/candidate/cursor/source-record/hash provenance, raw source data/secrets and Audit/outbox internals.

## 5. Accepted-observation and correction semantics

The projection selects only source observations with `invalidated_at IS NULL` and uses accepted K3 ordering by `captured_at DESC, id DESC`.

When the source invalidates the current observation, it stops participating immediately. The projection may fall back to the latest older still-accepted observation. Private invalidation reason/actor never crosses tenants.

This avoids materializing recipient copies that would preserve invalidated data beyond source canonical semantics.

## 6. Missing-versus-zero and freshness integrity

A target with no accepted source observation is explicitly `missing` with a null observation payload. It is not transformed into a zero power/member count.

Freshness uses the existing K3 30-day descriptive boundary. It does not trigger diplomacy, transfer, roster, enforcement or recommendation actions.

## 7. No-copy and no-reshare controls

Recipient reads create no recipient-owned `TrackedKingdomAlliance` or `KingdomAllianceObservation` records.

A recipient cannot re-share received intelligence by passing the upstream source tracking ID into its own outbound agreement because target grants always resolve tracking beneath the outbound source Alliance. Received share-target grants are not valid upstream data sources.

P2 therefore remains directional and non-transitive.

## 8. Revocation, removal and persistent drift controls

Target removal and agreement revocation immediately make the recipient query return no data for those grants.

P0 required Kingdom drift to fail closed without implicit reactivation. P2 integrates that rule with the supported Alliance→Kingdom mutation:

- source-side pending/active K5 agreements are terminalized;
- recipient-side active agreements are terminalized;
- counterpart evidence uses null actor to avoid cross-tenant manager identity leakage; and
- returning to the old Kingdom does not reactivate the old agreement.

Authorization checks remain live in the recipient query even after persistent terminalization, providing defense in depth.

## 9. Concurrency and lock ordering

P2 aligns relevant mutation lock ordering to Alliance(s) → share → target where Kingdom drift can race with consent/grant changes.

Acceptance and target grant lock source/recipient Alliances in deterministic ID order. Kingdom changes already lock the changed Alliance before invalidating K5 agreements. This removes the identified share-first/Alliance-first lock cycle.

Target removal is access-reducing and cannot grant new data authority.

## 10. Query bounding and denial-of-service posture

The current projection is capped at 250 targets and uses a bounded two-query pattern under the focused fixture.

The first query performs recipient/share/grant/context authorization. The second retrieves latest accepted observations for the authorized source tracking set. No per-target N+1 history lookup occurs.

P2 does not claim a production throughput SLO; realistic-volume capacity and authorization-safe caching, if any, remain P5 work.

## 11. Events, logging and public exposure

Target-shared/removed and context-invalidated events remain `kingdoms.*` internal events and external-webhook ineligible.

Counterpart events use safe share/target/Alliance/Kingdom/state/reason identifiers only. Source manager identity is not copied into recipient-tenant Audit evidence.

P2 adds no public API, webhook, anonymous sharing URL/feed, recipient GET route or external machine credential. The first-party full sharing UI remains P4.

## 12. Residual risk and P3 gate

P2 intentionally exposes current facts only. It does not yet expose bounded observation history.

Before P3 acceptance, shared-history queries must remain recipient-first, bounded/paginated, accepted-only, safe-field-only, correction-aware, immediately revoke/remove/drift sensitive, non-copying and non-transitive. History cannot include private correction/invalidation metadata or K4 operational provenance.

## 13. Validation evidence

Runtime candidate `1a022e909cd246197510449a761a4856ce12b118` passed Dependency Review `31562753429`, CodeQL `31562753422`, and full CI `31562753430`: Pint 550 files, PHPStan/Larastan 390/390 zero errors, 440 tests / 10,025 assertions, clean PostgreSQL migrations, frontend/build, immutable image, staging, backup/restore, image scan and cleanup.

Focused tests prove explicit-target-only visibility, safe-field whitelisting, accepted-observation invalidation fallback, missing-vs-zero semantics, no recipient canonical copy, no reshare, source-only grant mutation, target removal/revocation/drift loss of access, no implicit access resume after returning to a Kingdom, two-SELECT current projection and migration rollback/reapply integrity.

See [Slice B validation](../product/kingdoms-shared-intelligence-slice-b-validation.md) and [living shared-intelligence contract](../shared-intelligence.md).
