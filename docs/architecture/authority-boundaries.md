# V3 authority and persistence boundaries

## Enforcement principle

Identity crosses boundaries. Eloquent models do not carry trusted authority across request, workflow, job, event, or application write boundaries. Current authority is loaded and validated where the protected operation actually occurs.

## Request/security context

Request-scoped context is passive and immutable. It may contain scalar IDs, enums, value objects, and immutable identity/scope references. It must not contain an Eloquent model, a repository/query method, or mutable permission state that can be mistaken for current authorization.

The stable request scope is:

- authenticated account identity (`userId`) for account/platform concerns;
- active Player identity (`playerId`, `kingdomId`);
- optional Alliance scope (`allianceId`, `membershipId`).

Alliance rank, specialist roles, Kingdom roles, and permissions are not durable request authority. If a read projection includes them, the field name must make observation-time semantics explicit. Protected writes must re-read current authority.

## Protected writes

Public write contracts accept IDs, commands, immutable references, enums, and value objects. They do not accept request-loaded Eloquent authority objects.

Inside an owning capability, Eloquent is normal and expected. A protected write should generally:

1. start the transaction;
2. load the owner rows it needs by scoped IDs;
3. acquire required locks;
4. revalidate current membership/rank/role/aggregate state;
5. enforce invariants;
6. mutate owner state;
7. return an ID/reference/result DTO when returning an Eloquent model would leak persistence state into another layer.

An owner-internal mutation context may contain locked Eloquent models when it never crosses the capability/application boundary.

## Context ownership

A Context owns a distinct business language and consistency boundary. A Capability is a cohesive business area inside that Context. Technical folders live inside capabilities, never directly at a Context root.

Cross-context collaboration uses owner Actions, Queries, immutable projections, and scalar foreign IDs. Cross-context Eloquent relationships are prohibited. A foreign `*_id` is a reference, not permission to navigate or mutate the foreign aggregate.

## Workflows and read models

Workflows coordinate owner APIs and own no business persistence, permission vocabulary, aggregate repositories, or foreign transaction state.

ReadModels may compose reads across contexts but own no writes and must never be passed into protected write contracts.

## User and Player authority

`User` owns Players and carries account/platform-administration authority only. Game-domain authority derives from the active `Player`.

Do not reintroduce convenience authority such as `user->alliance`, `user->rank`, `user->kingdomRole`, or `user->membership`.
