# Getting Started

This page provides the shortest path to a working local Kingshot Alliance development environment. For full operational detail, use the canonical [local development runbook](../runbooks/local-development.md).

## Prerequisites

- Docker Engine with Docker Compose v2
- Git
- At least 4 GB of free memory for the development stack

PHP, Composer, Node, PostgreSQL, and Redis run inside containers. The committed lockfiles are part of the reproducible build and should not be bypassed.

## First setup

```bash
cp .env.example .env
./bin/setup
```

Open the application at `http://localhost:8080`.

The setup workflow verifies lockfiles, builds images, installs locked dependencies, creates the application key, runs migrations, and starts the stack.

## Development services

| Service | Purpose | Local endpoint |
|---|---|---|
| nginx | Web entry point | `http://localhost:8080` |
| app | PHP-FPM application | Internal |
| node | Vite development server | `http://localhost:5173` |
| worker | Horizon queue workers | Internal |
| scheduler | Laravel scheduler | Internal |
| postgres | PostgreSQL | `localhost:5432` |
| redis | Cache and queues | `localhost:6379` |

The default development ports bind to `127.0.0.1`. Do not broaden PostgreSQL or Redis to all host interfaces just for convenience.

## Common commands

```bash
make up
make down
make shell
make check
make test
docker compose run --rm app composer test:parallel
docker compose logs -f app worker
```

## Repository structure

```text
app/
  Application/       use cases and orchestration
  Domain/            business rules by domain
  Http/              delivery adapters
  Infrastructure/    database, messaging, storage, external adapters
  Providers/         composition root
```

See [Architecture](Architecture.md) before introducing a new domain or cross-domain dependency.

## Before submitting a change

At minimum:

1. Run the relevant automated tests.
2. Run formatting, linting, and static analysis through the repository quality gates.
3. Verify tenant isolation for alliance-scoped behavior.
4. Update user, operational, security, or architecture documentation when behavior changes.
5. Avoid partially introducing future-phase tables, permissions, routes, or abstractions.

The full acceptance rules are in [Definition of Done](../DEFINITION_OF_DONE.md) and the [implementation plan](../IMPLEMENTATION_PLAN.md).

## Troubleshooting

### Empty Composer vendor volume

```bash
docker compose run --rm app composer install --no-interaction --no-progress --prefer-dist
```

### Empty node_modules volume

```bash
docker compose run --rm node npm ci --no-audit --no-fund
```

### Stale Laravel configuration

```bash
docker compose exec app php artisan optimize:clear
```

### Reset local data

```bash
docker compose down -v
./bin/setup
```

This reset deletes local PostgreSQL, Redis, vendor, and node-module volumes.
