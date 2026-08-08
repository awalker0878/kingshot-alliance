# Identity, Tenancy, and Membership

[← Domain documentation](README.md)

## Purpose

This guide describes the current identity and alliance-tenancy boundary used by every alliance-scoped feature. It covers global user identity, alliance creation and selection, membership lifecycle, invitations, fixed alliance roles and permissions, privileged confirmation, and the audit/outbox behavior that accompanies security-relevant changes.

The runtime sources of truth are the Identity, Alliances, Memberships, and Authorization domains. In particular, `PermissionKey` defines the permission vocabulary and `DefaultAllianceRole` defines the built-in role templates. This document explains those contracts; it does not replace the runtime enums or authorization tests.

## Ownership boundaries

- **Identity** owns the global user account, authentication, email verification, password/session lifecycle, TOTP MFA, and recovery codes.
- **Alliances** owns the alliance aggregate, alliance creation, activation/switching, and request-scoped active-alliance context.
- **Memberships** owns the relationship between a user and an alliance, invitation lifecycle, membership status, leave/removal behavior, and administration safety rules.
- **Authorization** owns alliance roles, permission keys, role assignment/removal, and permission evaluation.
- **Audit** records attributable security and business changes.
- **Platform** owns the transactional outbox used to publish durable domain-change events after persistence.

A user is global. Alliance access is not. A single user may belong to multiple alliances, but every alliance-scoped request must resolve one explicit active alliance and an active membership in that alliance.

## Global identity and alliance tenancy

The application stores one user identity regardless of how many alliances that user joins. Alliance-owned records are keyed by `alliance_id`; the user account is not duplicated per tenant.

Creating an alliance is transactional. The application creates the alliance in the active lifecycle state, creates an active membership for the creator, provisions all built-in alliance role templates, assigns the creator the Owner role, provisions platform defaults, records an audit event, and creates an outbox message.

The creator therefore enters the alliance as an active owner rather than through a separate invitation flow.

## Active-alliance context

Authenticated users select one alliance as the active alliance. The selected alliance ID is stored in the session and revalidated on every route protected by `alliance.context`.

The request fails closed when the context is not valid:

- no selected alliance produces a conflict response requiring an active alliance;
- a missing, suspended, left, removed, or otherwise non-active membership clears the saved selection and denies access;
- a non-active alliance clears the saved selection and denies access;
- successful resolution attaches `alliance_id`, `alliance_membership_id`, and a serializable tenant-context snapshot to the request.

The in-memory alliance context and request tenant snapshot are cleared after the request. Long-running work must carry an explicit tenant snapshot or tenant identifier rather than depending on request-global state.

## Membership lifecycle

The membership status vocabulary is:

| Status | Meaning in the current implementation |
| --- | --- |
| `invited` | Defined in the lifecycle vocabulary. Membership invitations themselves are stored separately; the normal invitation-acceptance flow activates the membership rather than leaving it in this state. |
| `active` | The user may establish active-alliance context and receive permissions from assigned alliance roles. |
| `suspended` | The relationship is retained but cannot establish active-alliance access. |
| `left` | Set by the member's explicit leave action. Assigned roles are removed. |
| `removed` | Set by an authorized administrator. Assigned roles are removed. |

Administrative status changes are intentionally narrower than the enum. The current management action can set `active`, `suspended`, or `removed`. A user changes their own active membership to `left` through the dedicated leave action.

When a previously inactive membership is activated and has no role assignment, the application restores the built-in Member role. Removal and voluntary leave strip role assignments so dormant privileges cannot silently reappear later.

## Membership administration safety

Membership management requires an active membership carrying `membership.manage`.

The administration guard also enforces role hierarchy:

| Effective role rank | Rank |
| --- | ---: |
| Owner | 100 |
| Leader | 80 |
| Officer | 60 |
| Recruiter / Event Coordinator / Content Manager | 40 |
| Member | 10 |

A non-owner administrator may only manage a membership below their own effective rank. Administrators cannot use the general membership-status action to change their own membership; self-removal uses the leave workflow.

An alliance must retain at least one active Owner. Suspending, removing, or voluntarily leaving as the last active owner is rejected until another active owner exists.

## Invitations

Alliance invitations are separate bearer-token records owned by Memberships.

Creating an invitation requires `invitations.manage`. The email address is normalized, an already-active member cannot be invited again, and member-capacity entitlement checks are enforced. Only the token hash is stored. The default lifetime is 72 hours unless `identity.invitation_ttl_hours` is configured differently.

Issuing a new pending invitation for the same alliance and email serializes against the alliance and revokes earlier pending invitations for that email. Resending an eligible invitation rotates the bearer token and refreshes its expiry. Accepted or revoked invitations cannot be resent.

Acceptance requires:

- a pending, unexpired token;
- the authenticated user's normalized email to match the invitation email; and
- the invitation and membership changes to complete transactionally.

Acceptance creates or reactivates the membership, assigns the built-in Member role if required, marks the invitation accepted, records audit evidence, and emits an outbox event.

Invitation links should therefore be treated as secrets. Revoke or resend rather than attempting to recover an old token.

## Authorization model

Authorization is permission based, not controller-name based. `AllianceAuthorization` grants a permission only when the user has an **active membership in the target alliance** and at least one assigned role in that same alliance grants the requested permission.

A membership may hold more than one role. Effective permissions are the union of permissions provided by its assigned roles.

The application currently exposes fixed, system-defined role templates. There is no supported surface for arbitrary custom role templates or editing the permission vocabulary.

### Permission reference

The current permission vocabulary is:

| Permission | Capability |
| --- | --- |
| `alliance.view` | View alliance member areas. |
| `alliance.manage` | Manage alliance settings and integration administration surfaces. |
| `membership.manage` | Manage alliance membership status. |
| `roles.manage` | Assign and remove alliance roles. |
| `invitations.manage` | Create, revoke, and resend membership invitations. |
| `content.manage` | Manage alliance content and public-presence content. |
| `events.manage` | Manage events and rally configuration. |
| `recruitment.manage` | Manage recruitment workflows. |
| `contributions.manage` | Manage contribution records, reporting, exports, and report schedules. |

`app/Domain/Authorization/Enums/PermissionKey.php` is authoritative if this table ever drifts from runtime behavior.

### Built-in RBAC matrix

The built-in templates currently resolve as follows:

| Role | View | Alliance | Membership | Roles | Invitations | Content | Events | Recruitment | Contributions |
| --- | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| Owner | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Leader | ✓ | ✓ | ✓ | — | ✓ | ✓ | ✓ | ✓ | ✓ |
| Officer | ✓ | — | ✓ | — | ✓ | — | ✓ | — | — |
| Member | ✓ | — | — | — | — | — | — | — | — |
| Recruiter | ✓ | — | — | — | ✓ | — | — | ✓ | — |
| Event Coordinator | ✓ | — | — | — | — | — | ✓ | — | — |
| Content Manager | ✓ | — | — | — | — | ✓ | — | — | — |

The abbreviated columns map, in order, to `alliance.view`, `alliance.manage`, `membership.manage`, `roles.manage`, `invitations.manage`, `content.manage`, `events.manage`, `recruitment.manage`, and `contributions.manage`.

`app/Domain/Authorization/Enums/DefaultAllianceRole.php` is authoritative for this matrix. Notably, only the Owner template currently includes `roles.manage`; Leaders can manage memberships and invitations but cannot assign or remove roles through the supported authorization contract.

## Role assignment and removal

Role changes require `roles.manage`. Both the target membership and role are re-resolved inside the active alliance before mutation.

Only active memberships can receive a role. Assigning a role that is already present is a no-op rather than creating duplicate state. Role changes are audited and emit unique outbox events so legitimate assign/remove/reassign cycles do not collide with prior events.

Owner-role removal remains subject to the last-active-owner safety rule.

## Verification, password confirmation, and MFA

Email verification is required before alliance creation, alliance activation, invitation acceptance, and alliance-scoped authenticated workflows.

Security-sensitive mutations are protected by recent password confirmation at the HTTP boundary. This includes membership/invitation/role administration and the privileged management actions in Content, Events, Recruitment, Contributions, Integrations, and platform administration where applicable.

TOTP MFA and one-time recovery codes belong to the global Identity account. MFA management itself requires verified identity and recent password confirmation. Alliance authorization does not treat MFA as a substitute for tenant membership or permission checks.

Platform-administrator access is a separate cross-tenant privilege model and additionally requires MFA; it is not an alliance role. See [Platform scale and administration](platform-scale-and-administration.md).

## Audit and outbox invariants

Security-relevant identity, alliance, invitation, membership, and role transitions record attributable audit evidence. Persisted business transitions also write transactional outbox messages when downstream publication is required.

The outbox is at-least-once. Consumers must use message idempotency rather than assuming publication occurs exactly once.

Examples of durable alliance-security events include alliance creation, invitation creation/revocation/resend/acceptance, membership status/leave changes, and role assignment/removal.

## Tenant-isolation rules for feature domains

Every feature domain must preserve these invariants:

1. Resolve an explicit alliance context before alliance-scoped work.
2. Re-resolve mutable identifiers under that `alliance_id`; do not trust a submitted object ID by itself.
3. Require an active membership for member access.
4. Require the owning permission for privileged access.
5. Carry tenant identity explicitly into jobs, cache keys, exports, storage paths, logs, and integration work.
6. Fail closed when tenant context is missing, stale, suspended, or inconsistent.
7. Keep global identity separate from alliance-owned persistence.

See the [security baseline](../security/security-baseline.md) for the consolidated security requirements and the [domain boundary audit](domain-boundary-audit.md) for current cross-domain ownership evidence.

## Troubleshooting

If an alliance page returns that an active alliance is required, select an alliance from the dashboard and retry. If the selected alliance is immediately rejected, verify that both the alliance and the user's membership are active.

If a management action is forbidden, verify the active alliance, membership status, and the role-to-permission mapping above. Do not assume that a leadership-sounding role implies every administrative permission; for example, `roles.manage` is Owner-only in the current default templates.

If an invitation fails, verify that it is pending, unexpired, and being accepted by the account whose normalized email matches the invitation. Issue or resend a controlled invitation instead of manually changing token or membership persistence.
