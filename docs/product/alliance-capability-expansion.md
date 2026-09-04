# Alliance Capability Expansion

Status: Selected extension — implementation in progress

Baseline: `main` at `0a71a3cd4b61fe19ffd799b45ff74b23bd4038ba`.

This program closes the gap between the documented Alliance Lifecycle boundary and the officer-facing implementation, then extends existing Alliance capabilities without creating another Alliance bounded context. The application is a fresh deployment: the implementation keeps one canonical schema and one canonical write path. No compatibility shims, dual reads/writes, legacy aliases or migration bridges are permitted.

## Outcomes

1. **Alliance Settings & Identity** — authorized officers manage application-owned Alliance name, slug, default language and timezone through `Alliance/Lifecycle`.
2. **Specialist Role Administration** — `Alliance/Access` owns Alliance-local specialist-role definitions, bounded permission delegation and member assignments. R1–R5 remains `Alliance/Membership` state.
3. **Membership Governance History** — authorized read models project durable invitation, membership, rank, role and leadership audit facts without persisting another membership timeline.
4. **Alliance Roster Screenshot Reconciliation** — `Intelligence/Evidence` owns screenshot provenance/review; accepted facts become `Intelligence/Roster` observations; reconciliation never directly changes authoritative membership.
5. **Bulk Rank & Role Operations** — bounded preview/commit workflows reuse the existing per-member owner actions and re-check current authority at commit.
6. **Recruitment Re-entry Controls** — Alliance-local private recruiting restrictions/review dates live in `Alliance/Recruitment`; there is no global blacklist.
7. **Alliance Governance Timeline** — an authorized ReadModel composes consequential Alliance administration from owner audit facts.
8. **Existing composition integration** — Alliance Hall, Member Capability Profile, Command Overview and Alliance Assistant consume the new owner/read-side contracts without gaining direct cross-context writes.

## Ownership

- `Alliance/Lifecycle` — Alliance application identity/settings and lifecycle semantics.
- `Alliance/Membership` — membership, invitation, R1–R5 rank and leadership state.
- `Alliance/Access` — Alliance permission vocabulary, specialist roles and authorization interpretation.
- `Alliance/Recruitment` — application intake, review, decisions, onboarding and re-entry controls.
- `Alliance/Content` — public profile, Rules, notices and media. Public profile does not move into Lifecycle.
- `Intelligence/Evidence` — screenshot artifact, security/classification/extraction/review provenance and commit receipts.
- `Intelligence/Roster` — accepted observed roster facts.
- `ReadModels` — governance history, reconciliation and cross-owner composition only.
- `Platform/AllianceAdministration` — platform tenant lifecycle/entitlement operations only; it is never an Alliance game-domain authorization bypass.

## Authority

Alliance authority remains Player-scoped. Every protected write receives scalar IDs/value objects, reacquires the current active Alliance/membership state inside the owner transaction and authorizes the concrete Alliance scope. Authority is never aggregated across a User's Players and Platform Administrator is not a game-domain bypass.

## Settings contract

Officer-manageable Lifecycle settings are `name`, `slug`, `language` and `timezone`. Kingdom association remains a GameWorld/Kingdom-owned reference workflow and is not silently changed by generic settings. Platform-only lifecycle fields such as suspension, closure, retention and deletion are not exposed through officer settings.

Slugs are normalized, unique and protected from application-reserved paths. Languages must be supported application locales and timezones must be valid IANA identifiers. Settings writes record audit/outbox facts containing changed fields and old/new values.

## Specialist-role contract

Specialist roles remain Alliance-local. System roles cannot be archived or have their protected semantics mutated. User-created roles have stable keys, editable names, an archive state and permissions chosen only from the existing closed Alliance permission vocabulary.

An actor may delegate only permissions that the actor currently possesses. A role mutation or assignment may not be used for self-escalation. R5 can be transferred only through the Membership leadership-transfer workflow.

## History contract

History is derived from existing owner audit/outbox facts. It may show factual entries such as invitation issued/accepted, membership status change, rank change, role assignment/removal, leadership transfer, settings change and recruitment control change. It does not infer motivation, performance or intent.

## Roster screenshot contract

Roster screenshot intake is embedded in the Alliance Roster workflow. Initial reviewed fields are limited to visible evidence: Governor name, visible game ID, visible R1–R5 rank, visible power and capture time. Missing fields remain missing.

Accepted evidence creates immutable observed roster facts. Reconciliation may derive `observed_new`, `observed_missing`, `name_changed`, `rank_changed`, `power_changed`, `matches_membership`, `membership_without_observation`, `observation_without_membership` and `ambiguous_identity`. These are factual comparison states, not membership commands.

Only an explicit authorized Membership action may add/remove/suspend/promote/demote a membership.

## Bulk-operation contract

Bulk rank/role operations require explicit IDs, a maximum of 50 targets, preview, per-target stable outcome codes and confirmation. Commit repeats current authorization/invariant checks through the single-target owner actions. Preview is never an authorization cache.

## Recruitment re-entry contract

Recruitment candidates may carry one private Alliance-local control: `normal`, `do_not_invite`, `reapply_after` or `review_required`, with optional reason and review/expiry date. Controls participate in candidate merge and conversion/invitation checks and obey existing unsuccessful-candidate retention/anonymization rules.

## Explicit exclusions

This program does not add Alliance donation totals, Alliance Gift Level, arbitrary resource balances, global/Alliance leaderboards, generic Alliance power ranking, unsupported game mechanics or automatic strategic conclusions.

## Delivery order

0. Documentation and acceptance contract.
1. Alliance Settings.
2. Specialist Role Administration.
3. Membership Governance History.
4. Roster Screenshot Intake & Reconciliation.
5. Bulk Rank/Role Operations.
6. Recruitment Re-entry Controls.
7. Alliance Governance Timeline.
8. Existing composition integration.
9. Product-document reconciliation.
10. Verification and delivery-ledger closeout.

Detailed acceptance criteria are in `alliance-capability-expansion-acceptance.md`; progress is tracked in `alliance-capability-expansion-delivery-ledger.md`.