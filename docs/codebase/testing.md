# Testing

Status: Current — Architecture V3 — 2026-08-30

Architecture V3 verification must test both **source structure** and **business behavior**. A passing directory check alone is not architecture certification.

`/tests` and GitHub Actions enforce this contract on every pull request. The `Architecture V3 Verification` workflow validates source structure, boots routes, migrates the V3 schema, runs PHPStan and executes the full PHPUnit suite; CI also proves a clean PostgreSQL installation through every migration and runs formatting, frontend quality/build, dependency review, CodeQL and visual-regression checks.

## Structural architecture verification

V3 verification should derive rules from the architecture rather than maintain a second hardcoded capability registry.

It should cover, at minimum:

- exactly seven business contexts under `app/Contexts`;
- no context-root `Actions`, `Models`, `Queries`, `Services`, `Policies` or `Http` directories;
- no `*MutationAuthority` classes/references;
- GameWorld Player has no Accounts User Eloquent relationship;
- contexts do not import Workflows;
- Workflows contain no business Models, migrations, repositories, owner permission enums or direct foreign writes;
- ReadModels perform no writes;
- HTTP adapters contain no business transactions, direct persistence or business locks;
- Communications contains no Event/KingPerk-specific delivery classes or direct source-context model dependencies;
- cross-context Eloquent navigation is prohibited;
- authorization services do not acquire locks;
- write Actions/services do not interpret foreign permission vocabularies.

## Behavior verification

Behavior verification should continue protecting high-risk identity, authority, scope, transaction, concurrency, retry/idempotency and business invariants.

Important areas include:

- User -> many Players and active Player selection;
- Player-scoped Alliance, Kingdom, Operations and Intelligence authority;
- Alliance membership and leadership changes;
- Kingdom governance and transfer behavior;
- Event execution, participation, planning and results;
- KingPerks occupancy, cooldown and scheduling rules;
- Intelligence historical attribution;
- generic Communications delivery idempotency/retry;
- Platform authority isolation.

The Phase 13–24 closeout matrix adds three cross-surface contracts:

- `CapabilitySurfaceHttpMatrixV3Test` verifies R5/R4/R3/R1 HTTP/Inertia payloads, cross-account/Alliance/Kingdom rejection and bounded member history;
- `CapabilityReadModelBudgetAndTelemetryV3Test` verifies reviewed query ceilings plus identifier/count/reason/duration-only diagnostics;
- `CapabilityAcceptanceMatrix.spec.ts` verifies Rally Builder, Member Capability Profile, Transfer Campaign, Intelligence Timeline, Alliance Command, Officer Briefs and Alliance Assistant across desktop/mobile, keyboard focus, accessible names, overflow and reviewed visual fingerprints.

## Visual regression baselines

Playwright visual checks run against the Chrome runtime already supplied by the GitHub-hosted runner. A baseline may be refreshed only when the rendered change is intentional and has been visually reviewed; unrelated snapshots must remain byte-for-byte unchanged. Dynamic dates and database-generated UUID/ULID text are normalized only inside the captured locator so visual hashes measure the intended rendered contract while semantic assertions still verify the underlying provenance surface.

## Fresh-install verification

The primary backend job starts from an empty PostgreSQL service and runs `php artisan migrate:fresh --force` followed by `php artisan migrate:status --no-interaction`. This is the installation contract for an undeployed application: migrations must create a usable schema without a compatibility path, manual repair, seed dependency, or pre-existing database state.

## Full architecture certification

Final V3 certification must inspect more than tests named `Architecture*`:

```text
directories
namespaces
imports
Eloquent relationships
database ownership
controllers
routes
actions
permissions
transactions
events
listeners
tests
documentation
CI
```

Any change to a context boundary, cross-context contract, route ownership or persistence rule must update the relevant architecture tests and this documentation in the same pull request.
