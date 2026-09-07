# ADR-0016: Keep capability console commands with their owning capability

Status: Accepted

Date: 2026-09-07

## Context

`routes/console.php` had become a second application layer. It contained substantial command adapters for Gift Codes, notification delivery, Kingdom ingestion, platform administration, recruitment and other capabilities. Those closures parsed arguments, managed cursors, called capability services and rendered operational results while the corresponding capability code lived elsewhere.

KingdomMaps introduced class-based commands under its capability and registered them through `KingdomMapsServiceProvider`. Intelligence Evidence already used the same pattern for `EvidenceDiagnosticsCommand`. Keeping both patterns would make command ownership depend on when a feature was implemented rather than on an architectural rule.

## Decision

Capability console commands are application adapters owned by the capability they expose.

- Command implementations live under the owning capability at `app/Contexts/.../Console/Commands`.
- Each capability registers its command classes from a provider under the same capability.
- A command class may parse CLI arguments and options, perform CLI-specific validation and cursor handling, invoke the capability's Actions, Queries or Services, render output and select an exit code.
- Domain and application behavior stays in the capability's Actions, Queries and Services; command classes do not become a new business-logic layer.
- `routes/console.php` remains the centralized location for global scheduling and may contain only deliberately small application-wide closure commands.
- Shared infrastructure commands may live with the infrastructure component that owns them and are registered by its infrastructure provider.
- Existing command names, options, outputs and schedules are preserved when moving a closure into a class. No compatibility shim is required.

The application-wide closure commands currently permitted in `routes/console.php` are `app:config-check` and `app:launch-check`.

## Enforcement

`ConsoleCommandOwnershipV3Test` enforces the decision by:

1. allowing only the explicit application-wide closure-command whitelist in `routes/console.php`;
2. forbidding `App\Contexts` imports from that file;
3. requiring capability command classes to extend `Illuminate\Console\Command`; and
4. requiring each capability command to be referenced by a service provider inside the same capability.

Adding another closure command to `routes/console.php` therefore requires an explicit architectural change rather than silently reintroducing capability logic there.

## Consequences

Console entry points become discoverable from the capability that owns them, dependency direction is visible, command adapters can be tested as classes, and `routes/console.php` no longer accumulates unrelated application behavior.

This decision supersedes earlier implementation notes that described `routes/console.php` as the normal home for capability command definitions, including the Gift Code source-acquisition plan.
