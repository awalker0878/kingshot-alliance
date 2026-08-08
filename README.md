# Kingshot Alliance

Enterprise-ready, multi-alliance coordination platform for the Kingshot community.

## Current status

**Phases 0–6 complete · repository production hardening accepted · production cutover pending**

The implementation plan is complete through Phase 6. The repository includes identity and multi-tenancy, public/content management, events and rallies, recruitment, contributions/reporting, platform administration and integrations, plus repository-controlled production hardening.

A real production cutover is **not yet approved**. Infrastructure and operational evidence must still satisfy the production launch approval record before deployment is treated as approved.

## Technology baseline

- PHP 8.5 and Laravel 13
- Inertia 3, Vue 3, TypeScript, Tailwind CSS 4, and Vite 8
- PostgreSQL 18
- Redis 8 with Laravel Horizon
- Laravel Pulse, Pennant, and Sanctum foundations
- Docker Compose for local development
- GitHub Actions for quality, security, test, image, staging, and recovery validation

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

- [Documentation home](docs/README.md)
- [Program implementation plan](docs/product/implementation-plan.md)
- [Local development](docs/operations/runbooks/local-development.md)
- [Architecture decisions](docs/adr/README.md)
- [Definition of done](docs/product/definition-of-done.md)
- [Security baseline](docs/security/security-baseline.md)
- [Release checklist](docs/operations/release-checklist.md)
- [Production launch approval](docs/product/production-launch-approval.md)
- [Phase 0 exit report](docs/product/phase-0-exit-report.md)
- [Contributing](CONTRIBUTING.md)
- [Security reporting](SECURITY.md)
