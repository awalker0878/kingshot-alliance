# Local Development Runbook

## Prerequisites

- Docker Engine with Docker Compose v2
- Git
- At least 4 GB of free memory for the development stack

PHP, Composer, Node, PostgreSQL, and Redis run inside containers.

## First setup

```bash
cp .env.example .env
./bin/setup
```

The setup command builds the images, installs dependencies, creates the application key, runs migrations, and starts the stack.

## Services

| Service | Purpose | Local endpoint |
|---|---|---|
| nginx | Web entry point | `http://localhost:8080` |
| app | PHP-FPM application | internal |
| node | Vite development server | `http://localhost:5173` |
| worker | Horizon queue workers | internal |
| scheduler | Laravel scheduler | internal |
| postgres | PostgreSQL | `localhost:5432` |
| redis | Cache and queues | `localhost:6379` |

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
docker compose run --rm app composer install
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
