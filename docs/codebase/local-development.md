# Local development

Status: Current

## Setup

```bash
cp .env.example .env
./bin/setup
```

The default local application is exposed at `http://localhost:8080` by the repository's development topology.

## Common checks

```bash
make check
make test
```

Useful targeted commands include Laravel test paths, Pint, Larastan, ESLint, Prettier, Vue TypeScript checks and the Vite production build.

## Database and services

The application baseline uses PostgreSQL and Redis-backed infrastructure for hosted environments. Local/test configuration may use lighter drivers where repository configuration explicitly allows it.

The V3 TestCase gives each test a unique cache prefix before application/provider boot. This isolates cached authority and rate-limit counters when parallel tests reuse account IDs in separate PostgreSQL databases but share Redis. The namespace stays stable for application reboots within that test and the original environment is restored after teardown or failed setup. The configured cache driver, real middleware and rate limits are retained; CI continues using Redis. Tests must not globally flush a shared Redis database to clean up their own keys.

## Before changing architecture

Read [Architecture](../architecture/README.md), [Module map](module-map.md) and the owning context document. Do not create a new context or cross-context dependency merely to avoid locating the real owner.
