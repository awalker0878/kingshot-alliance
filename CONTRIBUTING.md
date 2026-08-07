# Contributing

## Delivery model

Work is organized by the phase gates in `docs/IMPLEMENTATION_PLAN.md`. Implement only the active phase. Do not add compatibility shims, unused domain placeholders, speculative tables, or partial features from later phases.

## Development workflow

1. Create a branch from the latest `main`.
2. Link the change to a phase and an issue.
3. Implement the smallest complete vertical slice.
4. Run `make check`.
5. Update tests, documentation, ADRs, and runbooks.
6. Open a pull request using the repository template.
7. Resolve all review and CI findings before merge.

## Engineering conventions

- Use strict PHP types.
- Keep controllers and middleware thin.
- Put business decisions in explicit actions, services, policies, and query objects.
- Use dependency injection rather than hidden global state.
- Store timestamps in UTC and convert at the presentation boundary.
- Carry alliance context explicitly after tenancy is introduced.
- Queue side effects only after the owning transaction commits.
- Make jobs and integration handlers idempotent.
- Never log secrets, credentials, message bodies containing sensitive data, or raw authentication tokens.
- Prefer structured log fields over interpolated prose.
- Add accessible names, keyboard behavior, focus states, and responsive behavior to interface changes.

## Commit and pull-request expectations

Commits should be focused and explain intent. Pull requests must document risk, migration impact, operational impact, validation, and rollback.

## Required checks

- Composer validation and dependency audit
- Laravel Pint
- Larastan
- PHPUnit
- ESLint
- Prettier
- Vue TypeScript checks
- Vite production build
- Docker Compose validation
- Production container build and vulnerability scan
- CodeQL and dependency review
