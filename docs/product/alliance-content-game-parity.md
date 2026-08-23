# Alliance Content game-parity slice

Status: In progress — 2026-08-23

This document is the implementation contract for the small KingShot-parity Content slice that introduces **first-class Alliance Rules** and **lightweight Like / Dislike reactions on Alliance Notices**. The slice deliberately improves familiarity without creating a social-ranking system or coupling member reactions to Alliance publishing authority.

A delivery item is complete only when its behavior, persistence, authorization, idempotency where applicable, audit/observability, responsive UX, accessibility, localization, automated tests, architecture/contracts and visual proof are complete. Implementation must continue until every item in this contract and the delivery ledger is complete.

## Product outcomes

### Alliance Rules

Every active Alliance member can find one canonical **Alliance Rules** surface without searching the general Noticeboard or guessing that a generic `rule` content type exists. Authorized Content managers can create or update the canonical Rules document from that surface.

Rules are persistent Alliance guidance, not a time-oriented Alliance Notice. They reuse the existing `Alliance/Content` ownership and revision/audit infrastructure rather than creating a new domain or duplicate content store.

### Alliance Notice reactions

Every active Alliance member who can read a published Alliance Notice can express one lightweight reaction:

- Like;
- Dislike;
- no reaction.

A member can switch Like → Dislike, Dislike → Like, or remove the current reaction. Repeating the same requested state is idempotent.

Reaction counts are informational context only. They never become a score, ranking, recommendation, moderation signal or publishing authority input.

## Capability ownership

`Alliance/Content` continues to own:

- canonical Alliance Rules content and revisions;
- Alliance Notice publication state;
- member Notice reactions;
- reaction invariants and reaction read summaries;
- Content audit/outbox semantics for material writes.

`Alliance/Access` continues to own Alliance scope, active-membership write state and the `ContentManage` permission. This slice does not create a new permission model merely for parity.

The frontend and HTTP controllers orchestrate requests only. They do not decide whether a Governor may publish, edit Rules or react.

No new top-level context, social domain, engagement service, reputation service or popularity read model is introduced.

## Terminology

- **Alliance Rules** — the one canonical member-visible Rules document for an Alliance.
- **Alliance Notice** — a published `ContentType::Announcement` readable by the active member.
- **Reaction** — exactly one of `like` or `dislike` for one Player + Alliance Notice pair.
- **No reaction** — absence of a reaction row; it is not stored as a third enum value.

The product must not introduce `score`, `net score`, `approval ratio`, `engagement`, `trending`, `popular`, `top`, `karma`, or similar derived reaction terminology.

## Alliance Rules contract

### Canonical identity and persistence

Alliance Rules reuse `content_items` and `content_revisions`.

The canonical document is identified by:

- current Alliance scope;
- `type = rule`;
- reserved slug `alliance-rules`.

The existing database uniqueness of `(alliance_id, slug)` provides the persistence-level singleton invariant for the canonical Rules document. The dedicated Rules workflow exclusively owns that canonical identity.

The dedicated Rules Action must also acquire the Alliance aggregate's exclusive write lock before looking up or creating the canonical row. A row-level `FOR UPDATE` lookup cannot serialize a row that does not exist yet; the aggregate lock therefore makes competing first-save requests deterministic and prevents a unique-key race from becoming user-visible failure. The database uniqueness constraint remains the final persistence invariant.

Generic Content management therefore must not:

- create a generic item with the reserved `alliance-rules` slug;
- mutate or rename an existing canonical Rules item by Content-item ID;
- publish or schedule the canonical Rules item through the generic publication Action;
- archive the canonical Rules item through the generic archive Action;
- restore one of its revisions through the generic revision-restore Action;
- present the canonical Rules item as an editable item in the generic Noticeboard-management inventory.

Those owner-side restrictions are required even if the current UI does not expose a generic control, so crafted requests cannot bypass the first-class Rules workflow.

The canonical Rules document is:

- member-visible (`visibility = members`);
- published immediately by the dedicated Rules workflow;
- never configured to notify members;
- not a broadcast schedule target;
- revisioned using the existing Content revision writer;
- auditable using the existing Content audit infrastructure.

Supplementary generic `rule` content may continue to exist under other slugs, but only the reserved canonical document appears as the first-class Alliance Rules surface.

### Rules authorization

Read:

- requires the normal authenticated, verified, active Alliance context;
- does not require `ContentManage`;
- must have executable HTTP behavior coverage proving an ordinary active member can render the Rules surface while management UI/authority remains absent.

Write:

- revalidates active Player + Alliance write scope inside the Action transaction;
- acquires the Alliance aggregate's exclusive write lock so canonical create/update is serialized;
- requires `AlliancePermission::ContentManage`;
- remains behind the repository's existing password-confirm boundary for Content-management writes.

Rules editing authority is unrelated to Notice reaction authority.

### Rules mutation

The dedicated Action accepts scalar/value input only:

- Alliance ID;
- actor Player ID;
- body;
- locale.

It creates or updates the canonical Rules document transactionally, increments the revision when changed, sanitizes content with the existing Content sanitizer, records the revision, and emits the repository-standard audit/outbox record.

Required validation:

- body is required after trimming and is bounded to 10,000 characters;
- locale uses the existing Content locale format and is bounded to 16 characters;
- the reserved slug cannot be occupied by a non-Rule item.

These validation invariants belong to the owner Action and must be enforced there even when the HTTP request layer performs the same checks. Controller validation is defense in depth, not the authoritative domain/application boundary. Direct Action tests must prove invalid body/locale input cannot create or mutate Rules, revisions, audit records or outbox messages.

Repeated submission of identical Rules content must not manufacture a new revision/audit event. The Action returns the existing canonical item unchanged when the sanitized body and locale have not changed.

### Rules UX states

The `/alliance/rules` member surface must provide:

1. **Empty** — clear message that no Alliance Rules have been posted yet.
2. **Published** — readable, whitespace-preserving Rules text and last-updated context.
3. **Editable** — only Content managers see the edit form/action.
4. **Submitting** — save control is disabled and exposes an accessible busy state.
5. **Validation failure** — errors are associated with their fields and remain keyboard/screen-reader reachable.
6. **Success** — the current saved Rules are rendered after the Inertia redirect.
7. **Unauthorized** — server authorization remains authoritative even if a client manufactures a write request.

The page must be usable without horizontal overflow on mobile and with keyboard-only navigation.

## Alliance Notice reaction contract

### Eligible target

A reaction can target only a Content item that, at mutation time:

- belongs to the actor's current Alliance;
- has `type = announcement`;
- has `status = published`;
- has a non-null `published_at` not later than now;
- is not archived;
- has visibility `public` or `members`.

Draft, scheduled, archived, foreign-Alliance and non-Announcement Content cannot be reacted to.

### Reaction persistence

Create `alliance_notice_reactions` with:

- ULID primary key;
- Alliance ID;
- Content item ID;
- Player ID;
- reaction enum value (`like` or `dislike`);
- timestamps.

Required database invariants:

- foreign Alliance and Content relationships cascade safely with Alliance/Content deletion;
- Player deletion follows the repository's Player referential-integrity convention;
- `(content_item_id, player_id)` is unique, enforcing at most one reaction per Governor per Notice;
- the stored reaction is validated by the application enum and never accepts arbitrary score values.

### Reaction authorization boundary

Reaction writes revalidate the active Player + Alliance scope through `AllianceWriteState` but **must not call `AllianceAuthorization` for `ContentManage` or any publishing permission**.

Therefore:

- a normal active member may react;
- a Content manager may react only because they are also an active member;
- losing active membership removes reaction-write eligibility regardless of historical publishing authority;
- reacting never grants or changes create/edit/publish/archive/broadcast permissions.

This separation must have automated behavior coverage.

### Reaction idempotency

For one Player + Notice:

| Current state | Requested state | Result |
| --- | --- | --- |
| none | Like | Like |
| none | Dislike | Dislike |
| Like | Like | unchanged |
| Dislike | Dislike | unchanged |
| Like | Dislike | Dislike |
| Dislike | Like | Like |
| Like/Dislike | remove | none |
| none | remove | unchanged |

No-op requests do not create duplicate rows, extra domain meaning or duplicate audit records.

### Reaction read contract

Member Noticeboard reads may expose only:

- `likes` count;
- `dislikes` count;
- the current Player's reaction (`like`, `dislike`, or null).

Counts are composed without N+1 per-Notice queries.

Reaction data must **not** affect the existing Content query ordering (`sort_order`, then publication recency), filtering, visibility, prominence or public pages. No query may order or recommend Content by reaction totals.

### Reaction UX states

Published Announcement cards and detail pages expose compact Like and Dislike controls.

Each control must:

- show its count;
- expose a localized accessible name containing the count;
- expose selected state with `aria-pressed` and a visible non-color-only state;
- disable while its request is processing;
- allow switching reactions;
- allow removing the selected reaction by pressing it again;
- preserve scroll/state across the Inertia mutation;
- avoid optimistic count/selection changes before the server confirms the mutation;
- on validation/mutation failure, re-enable the controls, preserve the prior server-backed reaction state, expose localized inline alert feedback, and allow the Governor to retry immediately.

Non-Announcement Content does not show reaction controls. Public unauthenticated Alliance pages do not expose reaction writes or private member reaction identity.

## Explicit anti-ranking invariant

Reactions are not popularity data.

The implementation must not add or derive:

- net reaction score;
- approval/disapproval percentage;
- popularity rank;
- "most liked" / "most disliked" lists;
- trending or recommendation behavior;
- moderation action from Dislike;
- notification fan-out from Like/Dislike;
- badges, reputation, achievements or Governor scoring;
- sorting or pinning based on reactions.

A regression test must prove Noticeboard ordering remains the existing Content ordering regardless of reaction totals.

## HTTP/application boundaries

Member read routes:

- `GET /alliance/rules` — first-class Rules surface.

Member reaction routes, outside the password-confirm/Content-management authority group but inside active Alliance context:

- `PUT /alliance/content/{content}/reaction` — set Like or Dislike;
- `DELETE /alliance/content/{content}/reaction` — remove current reaction.

Rules write route, inside the existing password-confirm boundary:

- `PUT /alliance/rules` — create/update canonical Alliance Rules; Action enforces `ContentManage`.

Controllers pass scalar IDs/enums/value input to owner Actions. Eloquent models are loaded and locked inside the owning Action.

HTTP contract tests must cover methods, URIs and middleware composition, while behavior tests must separately prove the authorization result at runtime. Route metadata alone is not sufficient evidence that a non-manager can read Rules or that reaction authority is independent from Content management.

## Audit and observability

Rules:

- changed Rules produce one audit record and one outbox record with Alliance/content/revision identity and non-sensitive metadata;
- identical no-op saves do not create a revision or duplicate audit/outbox record.

Reactions:

- state changes produce a privacy-minimal audit record identifying the content target and reaction transition;
- no-op set/remove requests do not emit duplicate audit records;
- raw reaction aggregates are not written into logs as popularity telemetry;
- standard request/exception monitoring remains the operational failure signal.

No notification or broadcast is emitted from reactions.

## Localization and accessibility

All new player-facing copy belongs in the existing Content localization catalogue. Required concepts include:

- Alliance Rules;
- Rules empty/edit/save/help/updated states;
- Like;
- Dislike;
- remove reaction semantics;
- accessible count labels;
- reaction failure/retry feedback;
- saving/validation copy.

`Alliance/Rules/*` Inertia pages must load the existing `content` localization domain through the page-domain registry before render. Source-level localization checks alone are insufficient: automated frontend contract coverage must prove that the Rules route family resolves the Content catalogue so localized semantic names are present at runtime.

No new hardcoded English player-facing copy is introduced in the Vue surfaces.

Keyboard operation, focus visibility, semantic headings, associated labels/errors, `aria-pressed`, `aria-busy` and mobile layout are part of acceptance—not follow-up polish.

## Acceptance criteria

The slice is accepted only when all of the following are true:

1. `/alliance/rules` is a first-class member destination.
2. A Content manager can create and update the canonical Rules document.
3. A non-manager can read Rules but cannot update them, with executable HTTP behavior coverage rather than route-metadata assertions alone.
4. Canonical Rules reuse Content persistence/revisions/audit rather than a duplicate Rules store.
5. Generic Content creation cannot claim the reserved `alliance-rules` slug, and generic save/publish/schedule/archive/restore flows cannot mutate an existing canonical Rules item; the generic manager inventory omits that item.
6. Canonical Rules create/update acquires an Alliance aggregate exclusive write lock so concurrent first-save requests cannot race on the singleton row.
7. Rules no-op saves are idempotent.
8. Rules body/locale invariants are enforced at the owner Action boundary as well as the HTTP boundary, and invalid direct Action calls leave Content/revision/audit/outbox state unchanged.
9. Published Alliance Notices expose Like/Dislike to active members.
10. One member can have at most one reaction per Notice.
11. Like, Dislike, switching, toggling off and repeated no-op requests behave deterministically.
12. Reaction writes do not require or consult publishing/Content-management permission.
13. Draft/scheduled/archived/non-Announcement/foreign-Alliance targets reject reaction writes, and losing active membership removes reaction-write authority.
14. Noticeboard Index and Show expose counts + current-member state without per-card N+1 queries.
15. Reaction totals never influence Content ordering, visibility, pinning, moderation, recommendations or public-page ranking.
16. Reaction mutation failures preserve the prior server-backed state, re-enable controls, expose localized inline feedback and remain immediately retryable.
17. New UX is responsive, keyboard-safe, screen-reader meaningful and localized through the Content catalogue, including explicit `Alliance/Rules/*` page-domain registration.
18. Behavior, authorization, idempotency, persistence invariants, architecture boundaries and HTTP/frontend contracts have automated coverage.
19. Desktop/mobile visual regression covers Rules empty/populated/editable state and Notice reactions on both Noticeboard card and detail surfaces with stable accepted fingerprints.
20. Product/reference/architecture/frontend capability documentation is reconciled wherever implementation changes those contracts.
21. The repository's applicable quality/release gates are green on one immutable implementation head before the slice is marked Complete.

## Delivery queue

| Phase | Status | Slice | Exit condition |
| --- | --- | --- | --- |
| 1 | Complete | Product contract and ownership | This contract defines scope, ownership, authorization/data invariants, UX states, acceptance criteria and anti-ranking boundaries before application-code changes. |
| 2 | In progress | First-class Alliance Rules | Canonical Rules Action/read surface, reserved identity, revisions, audit/outbox, owner-boundary validation, aggregate-lock singleton serialization, dedicated-workflow isolation from generic Content mutations, read/write authorization and idempotency are implemented and behavior-tested, including ordinary-member HTTP read proof. |
| 3 | In progress | Alliance Notice reactions | Reaction enum/model/schema/actions enforce active-member authorization, target validity, uniqueness, switching/removal/idempotency and audit semantics. |
| 4 | In progress | Read composition and UX | Notice reads include bounded reaction summaries; Rules/reaction UI, navigation, mobile/accessibility/localization/failure-retry states and no-ranking ordering are complete; all eight desktop/mobile visual surfaces have accepted stable fingerprints. |
| 5 | In progress | Verification and closeout | Automated behavior/contract/architecture/visual coverage and affected product/reference/architecture/frontend docs are reconciled; applicable repository quality gates are green on one immutable head. |

## Reconciliation findings

Implementation is required to close findings discovered after the initial contract was written rather than treating them as follow-up work:

- the Rules owner Action must independently enforce the body/locale limits already present at the HTTP boundary;
- ordinary-member Rules read authorization requires executable HTTP behavior proof, not only route registration/middleware inspection;
- `Alliance/Rules/*` must be registered to load the `content` localization domain at runtime;
- the Rules page must preserve a single page-level heading hierarchy while keeping document/edit sections semantically named;
- deterministic visual coverage must include published Rules, empty Rules, Noticeboard reactions and Notice-detail reactions on desktop and mobile;
- Notice reaction failure must follow the repository mutation UX: no optimistic state drift, localized inline feedback, controls re-enabled and retry available;
- scheduled/archived targets and membership-loss reaction denial require direct behavior coverage rather than code inspection alone;
- the canonical Rules identity must be isolated from every generic Content mutation path and omitted from the generic management inventory, not merely protected against generic creation with the reserved slug;
- canonical Rules creation must serialize on the Alliance aggregate because locking a missing Content row cannot prevent competing first-create attempts;
- the frontend capability map must explicitly describe first-class Alliance Rules and informational non-ranking Notice reactions so UI documentation agrees with the shipped capability.

These findings are part of the current product scope and keep the corresponding delivery phases open until implemented and verified.

## Completion rule

Do not close this delivery program with an incomplete phase, undocumented follow-up, compatibility shim or "future enhancement" required by the contract. If implementation exposes a missing edge case required for correctness, usability, security, operability or architectural integrity, add it here and implement it before closeout.
