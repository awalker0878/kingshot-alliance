# V2 bounded contexts

Architecture V2 business behavior lives under this root.

Current target contexts:

- `Accounts`
- `GameWorld`
- `Alliance`
- `Operations`
- `Intelligence`
- `Communications`
- `Platform`

Capabilities are modules inside a context; a capability does not become a peer top-level context merely because it has models, routes, or tests.

New V2 code must not import `App\Domain\*`. Superseded V1 call sites are rewritten directly and old code is deleted rather than bridged.

Dependency direction is enforced by `tests/Architecture/ArchitectureV2DependencyTest.php`.