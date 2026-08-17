# Business contexts

Business behavior is owned by exactly seven bounded contexts:

- `Accounts`
- `GameWorld`
- `Alliance`
- `Operations`
- `Intelligence`
- `Communications`
- `Platform`

Capabilities remain inside their owning context. A model, route, table, controller or implementation folder does not create a new bounded context.

Cross-context commands are coordinated through `app/Workflows`, cross-context reads through `app/ReadModels`, and business-neutral technical concerns through `app/Shared`.

Architecture boundaries are enforced by the tests under `tests/v3/Architecture`.
