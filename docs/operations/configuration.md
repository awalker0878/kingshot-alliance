# Runtime configuration

Status: Current

Runtime configuration is defined by `.env.example`, `deploy/staging.env.example`, `config/*.php` and the hosted validation implemented by `App\Contexts\Platform\Services\RuntimeConfigurationValidator`.

## Validation

Use the application configuration and launch-check commands/scripts before hosted deployment. The validator enforces stronger rules for staging/production than for local development.

Hosted rules include:

- non-placeholder application version and 40-character release SHA;
- valid 32-byte application encryption key;
- PostgreSQL as the default database;
- Redis cache, queue and sessions;
- encrypted server-side session payloads;
- `lax` or `strict` SameSite cookies;
- approved private filesystem choices;
- Pulse recording disabled until explicitly introduced;
- bounded Horizon supervisor process counts;
- explicit trusted-proxy policy;
- HTTPS/secure-cookie requirements, with only the explicit loopback staging exception;
- production debug disabled;
- production content media on S3-compatible storage;
- encrypted PostgreSQL transport in production.

## Secrets

Never commit application keys, database/Redis credentials, API tokens, webhook signing material, MFA material or production endpoint secrets. Documentation may describe variable purpose and ownership but must not become a secret inventory.

## Configuration changes

A new environment variable is supported only when application configuration actually consumes it. Update example configuration, validation, operational documentation and recovery implications together when introducing a material runtime dependency.

## Gift Code rollout

Gift Code moderation, approved-source ingestion, and notification fan-out are separate rollback controls. Hosted launch configuration enables all three explicitly; production readiness reports disabled flags and missing/unavailable source adapters. Batch, timeout, catalogue, Governor, and transition-campaign bounds are defined in `.env.example` and `deploy/staging.env.example`.

See [Gift Code operations](gift-codes.md) for source-feed configuration, commands, health checks, replay, and rollback.
