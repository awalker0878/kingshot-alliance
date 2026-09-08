# ADR-0016: Keep console commands with their owning application package

Status: Accepted

Date: 2026-09-07

## Context

`routes/console.php` had become a second application layer. It contained substantial command adapters for Gift Codes, notification delivery, Kingdom ingestion, platform administration, recruitment and other capabilities. Those closures parsed arguments, managed cursors, called application services and rendered operational results while the corresponding owner code lived elsewhere.

KingdomMaps introduced class-based commands under its capability and registered them through `KingdomMapsServiceProvider`. Intelligence Evidence already used the same pattern for `EvidenceDiagnosticsCommand`. Keeping both patterns would make command ownership depend on when a feature was implemented rather than on an architectural rule.

The repository also has application actions owned by read models and cross-context workflows. Placing a CLI adapter for one of those actions inside an unrelated context would invert the dependency direction merely to satisfy a directory convention.

## Decision

Console commands are application adapters owned by the application package whose action, query or service they expose.

- Context capability command implementations live under the owning capability at `app/Contexts/.../Console/Commands`.
- Commands that directly expose a read-model action live under that read model at `app/ReadModels/.../Console/Commands`.
- Commands that directly expose cross-context workflow orchestration live under that workflow at `app/Workflows/.../Console/Commands`.
- Each command is registered from a service provider in the same owning application package.
- A command class may parse CLI arguments and options, perform CLI-specific validation and cursor handling, invoke the owner's Actions, Queries or Services, render output and select an exit code.
- Domain and application behavior stays in Actions, Queries, Services and workflows; command classes do not become a new business-logic layer.
- `routes/console.php` remains the centralized location for global scheduling and may contain only deliberately small application-wide closure commands.
- [ADR-0017](0017-single-scheduler-registry.md) enforces one scheduler registry: bootstrap and providers do not register additional schedules; recurring workloads invoke owner commands.
- Shared infrastructure commands may live with the infrastructure component that owns them and are registered by its infrastructure provider.
- Existing command names, options, outputs and schedules are preserved when moving a closure into a class. No compatibility shim is required.

The application-wide closure commands currently permitted in `routes/console.php` are `app:config-check` and `app:launch-check`.

## Enforcement

`ConsoleCommandOwnershipV3Test` enforces the decision by:

1. allowing only the explicit application-wide closure-command whitelist in `routes/console.php`;
2. forbidding Context imports from that file;
3. requiring Context, ReadModel and Workflow command adapters to extend `Illuminate\Console\Command`; and
4. requiring each command to be referenced by a service provider inside the same owning application package.

The existing architecture dependency tests continue to enforce that Context command adapters cannot import ReadModels, Workflows or foreign Context models. A command must therefore move to the correct owner instead of gaining an exception.

Adding another closure command to `routes/console.php` therefore requires an explicit architectural change rather than silently reintroducing application logic there.

## Consequences

Console entry points become discoverable from the application package that owns them, dependency direction remains visible, command adapters can be tested as classes, and `routes/console.php` no longer accumulates unrelated application behavior.

This decision supersedes earlier implementation notes that described `routes/console.php` as the normal home for capability command definitions, including the Gift Code source-acquisition plan.
