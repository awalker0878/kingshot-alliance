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

## Before changing architecture

Read [Architecture](../architecture/README.md), [Module map](module-map.md) and the owning context document. Do not create a new context or cross-context dependency merely to avoid locating the real owner.