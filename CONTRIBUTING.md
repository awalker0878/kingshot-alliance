# Contributing

## Start with ownership

Before writing code, identify the owning bounded context and capability in [Architecture](docs/architecture/README.md). Architecture V2 uses Accounts, GameWorld, Alliance, Operations, Intelligence, Communications and Platform.

Do not create a new bounded context merely because a feature has its own model, route, table, controller or folder.

## Development workflow

1. Start from the intended branch/release baseline and keep unrelated work out of the change.
2. Identify the owning context/capability and any cross-context contracts.
3. Implement the smallest complete vertical slice.
4. Use `app/Workflows` for genuine multi-context command orchestration and `app/ReadModels` for cross-context reads.
5. Keep `app/Shared` business-neutral and keep business behavior inside the canonical architecture boundaries.
6. Run the relevant targeted checks, then `make check` when appropriate.
7. Update the authoritative documentation using the [change impact guide](docs/governance/change-impact.md).
8. Open/review the pull request with schema, security, operational and rollback impact made explicit.

## Engineering conventions

- Use strict PHP types.
- Keep controllers and middleware thin; business decisions belong in owning actions/services/policies/queries.
- Use dependency injection rather than hidden global state.
- Store timestamps in UTC and convert at presentation boundaries.
- Treat the authenticated User as account identity and the active Player as the game-domain security principal.
- Never aggregate game authority across all Players owned by one User.
- Platform Administrator is not an Alliance/Kingdom/Operations/Intelligence bypass.
- Revalidate mutable write authority inside transactions after required scope locks.
- Do not mutate another context's aggregate through persistence reach-through; use supported contracts/workflows.
- Queue retryable side effects only after commit or persist transactional outbox intent with the owning transaction.
- Make jobs, webhook handlers and delivery consumers idempotent.
- Never log secrets, credentials, MFA material, raw authentication tokens or unnecessarily sensitive payloads.
- Prefer structured log fields over interpolated prose.
- Address accessible names, keyboard/focus behavior, responsive behavior and localization impact for interface changes.

## Documentation

Documentation is organized by reader intent:

- `docs/architecture` — ownership, boundaries, invariants and decisions;
- `docs/codebase` — physical implementation and developer navigation;
- `docs/operations` — deployment, observability, runbooks and recovery;
- `docs/product` — implemented user outcomes;
- `docs/governance` — engineering/security/documentation/approval rules;
- `docs/reference` — lookup-oriented facts.

Follow [Documentation standard](docs/governance/documentation-standard.md). Update one canonical owner rather than copying the same rule into multiple files.

## Required checks

Use the checks relevant to the change, including:

- Composer validation and dependency audit
- Laravel Pint
- Larastan
- PHPUnit and Architecture V2 suites
- ESLint and Prettier
- Vue TypeScript checks
- Vite production build
- Docker/container validation where affected
- CodeQL and dependency review through repository CI

The nine permanent architecture contracts are defined in [Architecture V2 compliance](docs/governance/architecture-compliance.md).

A green repository does not by itself approve real production. Production status is controlled by [Production approval](docs/governance/production-approval.md).
