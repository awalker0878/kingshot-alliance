# Kingshot Alliance

Enterprise-ready, multi-alliance coordination platform for the Kingshot community.

## Current phase

**Phases 0–4 complete — Phase 5 (Contributions and reporting) is next**

The integrated product currently includes the engineering foundation, identity and multi-tenancy, content/public presence, events and rallies, and recruitment. The Phase 1–4 alignment audit is the current cross-domain ownership reference. Phase 5 functionality has not been introduced early as placeholders.

## Technology baseline

- PHP 8.5 and Laravel 13
- Inertia 3, Vue 3, TypeScript, Tailwind CSS 4, and Vite 8
- PostgreSQL 18
- Redis 8 with Laravel Horizon
- Laravel Pulse, Pennant, and Sanctum foundations
- Docker Compose for local development
- GitHub Actions for quality, security, test, and image validation

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

## Health endpoints

- `GET /up` — process liveness
- `GET /health/ready` — database and cache readiness

Every response receives a request ID and W3C `traceparent` correlation header.

## Documentation

- [Project wiki](docs/wiki/README.md)
- [Program implementation plan](docs/IMPLEMENTATION_PLAN.md)
- [Phases 1–4 alignment audit](docs/PHASES_1_4_ALIGNMENT_AUDIT.md)
- [Local development](docs/runbooks/local-development.md)
- [Architecture decisions](docs/architecture/README.md)
- [Definition of done](docs/DEFINITION_OF_DONE.md)
- [Security baseline](docs/SECURITY_BASELINE.md)
- [Release checklist](docs/RELEASE_CHECKLIST.md)
- [Contributing](CONTRIBUTING.md)
- [Security reporting](SECURITY.md)
