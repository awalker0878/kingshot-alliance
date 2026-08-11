# Rallies security profile

[← Rallies domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current  
**Owning domain:** Rallies  
**Code owner:** `app/Domain/Rallies`  
**Primary security boundary:** authenticated Alliance-private coordination with active-member assignment and `events.manage` for privileged Rally mutations

## 1. Security purpose and scope

Rallies protects Alliance-private guidance, saved formations, recommended formations, groups, assignments, standby state, and Rally participation from cross-tenant access or unauthorized coordination.

The current domain is a first-party coordination feature; it does not automate game actions or expose a public machine interface.

## 2. Assets and sensitive data

Assets include member saved formations/notes, effective-dated guidance/rationale, Event-specific recommended formations, group capacity, member lead/joiner/standby assignments, and participation/no-show state.

These records are Alliance private. Guidance text should not contain unrelated personal data, secret material, or credentials.

## 3. Actors, authentication and authorization

Authenticated verified active members with `alliance.view` may access permitted Rally guidance/formations through Event/member surfaces. Coordination/mutation requires `events.manage` and recent password confirmation where applicable.

Workflow assignment as a Rally lead/coordinator never creates application permission by itself.

## 4. Tenant and privacy boundaries

Rally records are scoped to the active Alliance and applicable Event occurrence. Submitted occurrence/group/assignment/membership/formation identifiers are re-resolved under that Alliance.

Only active same-Alliance memberships may be assigned to Rally roles. Participation and saved formations are not public data.

## 5. Trust boundaries and data flows

Material flows are authenticated member/coordinator browser → Rally surfaces, Events occurrence identity → Rally coordination context, Memberships active-member identity → assignment, and privileged Rally mutation → Audit/outbox evidence.

No current boundary connects Rallies directly to an automated game client or public external API.

## 6. Threats, abuse cases and controls

Threats include cross-Alliance IDOR, assigning a member from another Alliance, unauthorized coordination, overbooking joiner capacity, malformed formation ratios, private notes/participation disclosure, hard-coded hidden guidance, and treating a workflow role as authorization.

Controls include tenant-scoped re-resolution, active-membership checks, `events.manage`, exact 100% formation validation, standby rather than silent overbooking, effective-dated/source-attributed guidance, and private first-party surfaces.

## 7. Integrity, concurrency and idempotency

Formation ratios must total exactly 100%. Group capacity produces deterministic standby behavior rather than silently exceeding configured joiner limits. Assignment and participation state remain linked to one Alliance/Event occurrence.

Repeated privileged actions must respect current state and must not bypass authorization or create cross-occurrence duplicate assignments through stale UI identifiers.

## 8. Secrets and credential handling

Rallies owns no authentication, API, webhook, invitation, or game-bot credential. Guidance, notes, formation data, logs, audit metadata, and outbox payloads must not be used to store secret material.

Any future automated game integration would create a new credential/external trust boundary and require a focused security review before implementation.

## 9. Destructive operations, retention and deletion

Rally coordination changes use supported lifecycle operations rather than ad hoc database deletion. Broader account/Alliance retention and anonymization are coordinated by Platform while preserving legitimate participation/history semantics.

No automated destructive in-game action exists.

## 10. Auditability, observability and evidence

Privileged Rally mutations are attributable where required. Operators diagnose occurrence/tenant identity, active-member eligibility, guidance effective dates, capacity/standby state, and participation separately.

Tests cover tenant isolation, active same-Alliance assignment, ratio validation, group capacity/standby, participation, authorization, and the architecture boundary with Events. See [Security baseline](../../../security/security-baseline.md).

## 11. Residual risks and explicit non-capabilities

Member-entered guidance/notes can still contain inappropriate private information unless users follow data-minimization expectations; the domain therefore does not treat free text as a secret store.

Rallies does not own Event attendance/registration, send reminders, expose a public write API, derive permission from Rally assignment, or execute automated game actions.

## 12. Focused reviews and related documentation

No focused living Rallies security review is required for the current authenticated first-party model.

- [Rallies domain contract](../README.md)
- [Events security profile](../../events/security/README.md)
- [Memberships security profile](../../memberships/security/README.md)
- [Authorization security profile](../../authorization/security/README.md)
- [Security baseline](../../../security/security-baseline.md)
- [Phase 3 threat model](../../../security/phase-3-threat-model.md)
