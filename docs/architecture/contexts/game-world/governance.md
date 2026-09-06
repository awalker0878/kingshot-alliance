# GameWorld — Governance

Status: Current — Architecture V3

Implementation target: `app/Contexts/GameWorld/Governance`

Governance owns Kingdom role definitions, role lifecycle, Player-scoped Kingdom role assignments/delegations, GameWorld Kingdom governance permission interpretation, exact owner-scoped role-permission reconciliation, administrator handoff/recovery state changes, and the owner-controlled write path for governance changes.

## Invariants

- Kingdom authority is Player-scoped and concrete-Kingdom-scoped.
- Platform Administrator is not a game-domain bypass and never becomes Kingdom game authority through recovery.
- Sensitive writes revalidate mutable governance state inside the owner transaction.
- Authorization services interpret permission vocabulary but do not acquire database locks.
- Governance owns Kingdom role and assignment persistence; consumers use Governance queries for effective authority facts.
- Governance does not interpret Operations or Intelligence permissions.
- Permission semantics remain owned by the context that defines the permission. A Kingdom role may carry recognized foreign-context permissions without transferring semantic ownership to Governance.
- Owner-aware permission reconciliation is exact only inside the selected permission-owner namespace. Reconciliation by one owner must not detach another owner's permissions.
- An effective Kingdom Administrator must remain after normal role removal/handoff. Time-bounded administrator delegation is allowed only when another administrator survives its expiry.
- Assignment authorization is based on current time (`effective_from`, `expires_at`, `revoked_at`) at the authorization read; expiry processing exists for audit/outbox lifecycle evidence, not correctness.
- System roles are policy-provisioned and protected. Custom roles are Kingdom-scoped, archiveable, and may delegate only recognized permissions the acting Player currently holds.

## System policy

The default roles remain:

- `kingdom_admin`
- `kingdom_event_coordinator`
- `kingdom_viewer`

Governance owns the `kingdom.roles.manage` permission and grants it to the default administrator role. Operations owns its Event/Territory permission vocabulary and reconciles its own exact grants onto these roles through `Workflows/KingdomGovernance`.

## Recovery and handoff

Initial bootstrap, normal handoff and break-glass recovery are separate processes:

1. **Bootstrap** establishes the first historical Kingdom administrator only.
2. **Handoff** is Player-authorized and may add another administrator or replace the acting administrator after the replacement exists.
3. **Recovery** requires Platform Administrator authority plus recent account authentication, an explicit reason and a target Player already in the Kingdom. Platform authority invokes a narrow Governance repair action; it does not acquire a Kingdom role.

## Read composition

`ReadModels/KingdomGovernance` composes effective-authority visibility, "who has this authority?", governance audit history and health/drift projections. These are read projections and do not own Governance persistence.

`Workflows/KingdomGovernance` coordinates processes spanning Governance plus another owner, such as Operations permission policy provisioning or Platform-authorized administrator recovery. Governance remains owner of Kingdom governance state.
