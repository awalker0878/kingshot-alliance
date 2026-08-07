# Kingshot Alliance

Enterprise-ready, multi-alliance coordination platform for the Kingshot community.

## Current phase

**Phase 0 — Engineering Foundation**

The repository currently contains the Laravel 13 application foundation, Vue and TypeScript frontend, PostgreSQL and Redis runtime, Docker development environment, quality gates, security controls, observability baseline, architecture decisions, and operational runbooks. Product-domain implementation begins only after the Phase 0 exit gate is satisfied.

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

- [Program implementation plan](docs/product/IMPLEMENTATION_PLAN.md)
- [Local development](docs/operations/runbooks/local-development.md)
- [Architecture decisions](docs/adr/README.md)
- [Definition of done](docs/product/DEFINITION_OF_DONE.md)
- [Security baseline](docs/security/SECURITY_BASELINE.md)
- [Release checklist](docs/operations/RELEASE_CHECKLIST.md)
- [Phase 0 exit report](docs/product/PHASE_0_EXIT_REPORT.md)
- [Contributing](CONTRIBUTING.md)
- [Security reporting](SECURITY.md)
