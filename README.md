# Kingshot Alliance

Enterprise-ready coordination platform for the KingShot community, currently evolving on the `architecture-v2` code architecture.

## Technology baseline

- PHP 8.5 and Laravel 13
- Inertia 3, Vue 3, TypeScript, Tailwind CSS 4 and Vite 8
- PostgreSQL 18
- Redis 8 with Laravel Horizon
- Laravel Pulse, Pennant and Sanctum foundations
- Docker Compose for local development
- GitHub Actions for quality, security and Architecture V2 verification

## Local setup

```bash
cp .env.example .env
./bin/setup
```

Open `http://localhost:8080`.

Useful commands:

```bash
make check
make test
docker compose run --rm app composer test:parallel
make backup
CONFIRM_RESTORE=YES make restore FILE=backups/database-....sql.gz
```

## Architecture V2

Business behavior is organized into bounded contexts under `app/Contexts`:

- Accounts
- GameWorld
- Alliance
- Operations
- Intelligence
- Communications
- Platform

Capabilities live inside those contexts. Cross-context commands are coordinated by `app/Workflows`, cross-context reads by `app/ReadModels`, and business-neutral infrastructure by `app/Shared`.

A User is the account principal; the active Player is the game-domain principal. Platform Administrator is User-scoped platform authority and is not a game-domain bypass.

See the [architecture overview](docs/architecture/README.md) and [codebase module map](docs/codebase/module-map.md).

## Health endpoints

- `GET /up` — process liveness
- `GET /health/ready` — dependency readiness

Requests receive request/correlation identifiers suitable for structured diagnostics.

## Documentation

- [Documentation home](docs/README.md)
- [Architecture](docs/architecture/README.md)
- [Codebase](docs/codebase/README.md)
- [System operations](docs/operations/README.md)
- [Product](docs/product/README.md)
- [Governance](docs/governance/README.md)
- [Reference](docs/reference/README.md)
- [Definition of Done](docs/governance/definition-of-done.md)
- [Security requirements](docs/governance/security-requirements.md)
- [Production approval](docs/governance/production-approval.md)
- [Contributing](CONTRIBUTING.md)
- [Security reporting](SECURITY.md)

## Production status

Repository/application hardening does not by itself approve a real production cutover. The authoritative go/no-go record is [Production approval](docs/governance/production-approval.md); it remains **not yet approved** until the required real-environment evidence is recorded.