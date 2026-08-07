# Local Development Runbook

## Prerequisites

- Docker Engine with Docker Compose v2
- Git
- At least 4 GB of free memory for the development stack

PHP, Composer, Node, PostgreSQL, and Redis run inside containers. The committed `composer.lock` and `package-lock.json` files are required so local development, CI, and production images use the same dependency graph.

## First setup

```bash
cp .env.example .env
./bin/setup
```

The setup command verifies the lockfiles, builds the images, installs locked dependencies, creates the application key, runs migrations, and starts the stack.

## Services

All published development ports bind explicitly to `127.0.0.1`. PostgreSQL, Redis, Vite, and the web application are not exposed on other host interfaces by the default Compose configuration.

| Service | Purpose | Local endpoint |
|---|---|---|
| nginx | Web entry point | `http://localhost:8080` |
| app | PHP-FPM application | internal |
| node | Vite development server | `http://localhost:5173` |
| worker | Horizon queue workers | internal |
| scheduler | Laravel scheduler | internal |
| postgres | PostgreSQL | `localhost:5432` |
| redis | Cache and queues | `localhost:6379` |

Do not replace the loopback bindings with all-interface mappings such as `5432:5432` or `6379:6379`. The local Redis service has no authentication because it is intended to remain reachable only from the Compose network and the developer's loopback interface.

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

## Troubleshooting

### Empty vendor volume

```bash
docker compose run --rm app composer install --no-interaction --no-progress --prefer-dist
```

### Empty node_modules volume

```bash
docker compose run --rm node npm ci --no-audit --no-fund
```

### Stale configuration

```bash
docker compose exec app php artisan optimize:clear
```

### Reset local data

```bash
docker compose down -v
./bin/setup
```

This deletes local PostgreSQL, Redis, vendor, and node module volumes.
