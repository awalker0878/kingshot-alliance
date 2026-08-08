# Runtime Configuration Reference

[← Operations documentation](README.md)

## Purpose

This guide describes the current operational configuration contract for local, staging, and production deployments. It is not a secret inventory and must never contain real credentials.

The runtime sources of truth are:

- `.env.example` for the local/reference environment shape;
- `deploy/staging.env.example` for the hosted staging shape;
- `config/*.php` for variables actually consumed by the application;
- `App\Domain\Platform\Services\RuntimeConfigurationValidator` for hosted fail-closed requirements;
- `config/horizon.php` for worker partitions/process limits; and
- `bin/deploy` for deployment-host-only controls.

When a variable in an example file is not consumed by runtime configuration, fix the example rather than treating it as supported configuration.

## Configuration validation

Run:

```sh
php artisan app:config-check
```

The command validates the current environment. Staging and production enforce additional hosted controls. A successful check proves repository-controlled configuration shape; it does not prove external network, DNS, certificate, secret-manager, backup, capacity, or alerting configuration.

Production launch additionally uses:

```sh
php artisan app:launch-check --json
```

See the [production launch runbook](production-launch-runbook.md) for the go/no-go boundary.

## Application and release identity

| Variable | Purpose | Hosted requirement / notes |
| --- | --- | --- |
| `APP_NAME` | Display/application name. | Non-secret. |
| `APP_ENV` | Laravel environment. | Use `staging` or `production` for hosted releases. |
| `APP_KEY` | Laravel encryption key. | Hosted releases require a valid 32-byte AES-256 key. Treat as a recovery-critical secret. |
| `APP_DEBUG` | Detailed error output. | Must be `false` in production. |
| `APP_URL` | Canonical application URL. | Production must use HTTPS. Staging must use HTTPS except the explicit loopback-only CI exception. |
| `APP_TIMEZONE` | Application default time zone. | Default/reference value is UTC. Domain workflows may have explicit alliance/user time zones. |
| `APP_VERSION` | Immutable release version. | In deployable images this is baked into the image; do not override it in the runtime env file. Must not be `dev` for hosted releases. |
| `RELEASE_SHA` | Source Git commit for the immutable image. | Baked into the image; do not override in runtime env. Hosted value must be a 40-character lowercase Git SHA. |

`Dockerfile` sets `APP_VERSION` and `RELEASE_SHA` from build arguments and records them as OCI image labels. `bin/deploy` rejects runtime values that disagree with the immutable image metadata.

## Logging

| Variable | Purpose | Recommended hosted posture |
| --- | --- | --- |
| `LOG_CHANNEL` | Laravel default log channel. | `stack`. |
| `LOG_STACK` | Channels used by the stack. | `stderr` for containerized hosted runtime. |
| `LOG_LEVEL` | Minimum log level. | `info` is the staging example; choose production level through an operational decision. |
| `LOG_DEPRECATIONS_CHANNEL` | Optional deprecation channel. | Do not route sensitive payloads into logs. |
| `LOG_DEPRECATIONS_TRACE` | Include deprecation traces. | Enable only when operationally justified. |

The repository `stderr` channel uses JSON formatting. See [Observability](observability.md) for correlation and privacy rules.

## PostgreSQL

| Variable | Purpose | Hosted requirement / notes |
| --- | --- | --- |
| `DB_CONNECTION` | Database driver. | Hosted releases must use `pgsql`. |
| `DB_URL` | Optional connection URL. | If used, protect as a secret because it may contain credentials. |
| `DB_HOST` | PostgreSQL host. | Required by the runtime validator. |
| `DB_PORT` | PostgreSQL port. | Defaults to `5432`. |
| `DB_DATABASE` | Database name. | Required by the runtime validator. |
| `DB_USERNAME` | Database user. | Required by the runtime validator. |
| `DB_PASSWORD` | Database password. | Required for hosted releases; secret. |
| `DB_CHARSET` | PostgreSQL charset. | Defaults to `utf8`. |
| `DB_SSLMODE` | PostgreSQL TLS policy. | Production must be `require`, `verify-ca`, or `verify-full`; stronger certificate verification is preferred where supported. |

The repository's staging Compose topology uses a private PostgreSQL container for demonstration/testing. Production should use the approved production data-service topology and must still satisfy the application validator.

## Redis, cache, queues, and sessions

| Variable | Purpose | Hosted requirement / notes |
| --- | --- | --- |
| `CACHE_STORE` | Cache backend. | Hosted releases must use `redis`. |
| `QUEUE_CONNECTION` | Laravel queue backend. | Hosted releases must use `redis`. |
| `SESSION_DRIVER` | Session backend. | Hosted releases must use `redis`. |
| `SESSION_LIFETIME` | Session lifetime in minutes. | Default example is 120. |
| `SESSION_ENCRYPT` | Encrypt server-side session payloads. | Must be `true` for hosted releases. |
| `SESSION_SECURE_COOKIE` | Require HTTPS for session cookie. | Must be `true` in production and normal hosted staging. |
| `SESSION_SAME_SITE` | Cookie SameSite mode. | Hosted value must be `lax` or `strict`. |
| `REDIS_CLIENT` | Redis client implementation. | Repository images include `phpredis`. |
| `REDIS_URL` | Optional Redis URL. | Protect if it contains credentials. |
| `REDIS_HOST` / `REDIS_PORT` | Redis endpoint. | Hosted dependency; default port `6379`. |
| `REDIS_USERNAME` / `REDIS_PASSWORD` | Redis authentication. | Secrets when used. |
| `REDIS_DB` | Default Redis database. | Defaults to `0`. |
| `REDIS_CACHE_DB` | Cache Redis database. | Defaults to `1`. |
| `REDIS_PREFIX` | Database key prefix. | Useful when sharing infrastructure; avoid collisions. |
| `REDIS_CLUSTER` | Cluster mode setting. | Defaults to `redis`. |
| `REDIS_PERSISTENT` | Persistent client connections. | Tune only with deployment capacity evidence. |
| `REDIS_MAX_RETRIES` | Client retry count. | Defaults to `3`. |
| `REDIS_BACKOFF_ALGORITHM` | Redis client backoff. | Defaults to `decorrelated_jitter`. |
| `REDIS_BACKOFF_BASE` / `REDIS_BACKOFF_CAP` | Retry backoff milliseconds. | Defaults to 100 / 1000. |

Redis loss affects cache, sessions, queues, Horizon, and scheduler coordination such as `onOneServer()` locks. Treat Redis health as a platform dependency, not only a cache concern.

## Private storage and content media

| Variable | Purpose | Hosted requirement / notes |
| --- | --- | --- |
| `FILESYSTEM_DISK` | Default private filesystem disk. | Hosted values may be `local` or `s3`. |
| `CONTENT_MEDIA_DISK` | Private disk used for content media. | Hosted values may be `local` or `s3`; production must use `s3`. |
| `CONTENT_MEDIA_MAX_KB` | Maximum uploaded media size. | Default is 8192 KiB. |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` | S3-compatible credentials. | Secrets; prefer workload identity/short-lived credentials where the platform supports them. |
| `AWS_DEFAULT_REGION` | S3 region. | Example is `ca-central-1`. |
| `AWS_BUCKET` | S3 bucket. | Required whenever the active filesystem/media disk uses `s3`. |
| `AWS_ENDPOINT` | Optional S3-compatible endpoint. | Use only for an approved backend. |
| `AWS_USE_PATH_STYLE_ENDPOINT` | Path-style S3 access. | Backend-specific. |

Production recovery must treat database, private media, and the application key as one recovery set. See [Backup and restore](runbooks/backup-restore.md).

## Mail

| Variable | Purpose | Notes |
| --- | --- | --- |
| `MAIL_MAILER` | Mail transport. | Examples use `log`; real production mail requires an approved provider/configuration. |
| `MAIL_URL` | Optional SMTP URL. | Secret if credentials are embedded. |
| `MAIL_SCHEME` | SMTP scheme. | Provider-specific. |
| `MAIL_HOST` / `MAIL_PORT` | SMTP endpoint. | Defaults are development-oriented. |
| `MAIL_USERNAME` / `MAIL_PASSWORD` | SMTP credentials. | Secrets. |
| `MAIL_EHLO_DOMAIN` | SMTP EHLO domain. | Defaults from `APP_URL` host. |
| `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | Sender identity. | Production ownership/deliverability remains external launch evidence. |
| `MAIL_LOG_CHANNEL` | Channel for the log mailer. | Development/test use only. |

Do not interpret `MAIL_MAILER=log` as production email delivery readiness.

## Identity and registration

| Variable | Purpose | Notes |
| --- | --- | --- |
| `REGISTRATION_MODE` | Controls application registration mode. | Current application configuration consumes this value. |
| `INVITATION_TTL_HOURS` | Alliance invitation lifetime. | Defaults to 72 hours; minimum behavior is enforced in invitation actions. |
| `SANCTUM_STATEFUL_DOMAINS` | Stateful first-party domains for Sanctum. | Keep limited to intended application origins. |

The active-alliance session key is application-defined and is not an environment variable.

## Security and proxy handling

| Variable | Purpose | Hosted requirement / notes |
| --- | --- | --- |
| `SECURITY_CSP_ENABLED` | Enable application CSP header. | Staging example enables it. Production policy should be explicitly approved. |
| `SECURITY_CONTENT_SECURITY_POLICY` | Override the repository default CSP. | Treat changes as security-sensitive configuration. |
| `TRUSTED_PROXIES` | Comma-separated trusted proxy addresses or `*`. | Configure explicit ingress addresses where possible. |
| `ALLOW_TRUST_ALL_PROXIES` | Explicit approval for wildcard proxy trust. | Required when `TRUSTED_PROXIES=*`; wildcard must be the only proxy entry. |
| `ALLOW_INSECURE_LOOPBACK_STAGING` | Permit HTTP/insecure cookies for loopback staging only. | Must remain `false` for normal hosted environments; intended for ephemeral CI topology only. |

A trust-all proxy configuration is safe only when the application service is unreachable except through controlled internal ingress. Repository validation cannot prove that network architecture.

## Horizon worker capacity

`config/horizon.php` is authoritative for queue partitions and the variable names below.

| Variable | Environment | Purpose | Default |
| --- | --- | --- | ---: |
| `HORIZON_PREFIX` | All | Redis key prefix for Horizon. | Environment example dependent |
| `HORIZON_LOCAL_MAX_PROCESSES` | Local | Single local supervisor capacity. | 3 |
| `HORIZON_STAGING_CORE_MAX_PROCESSES` | Staging | `default` + `notifications` supervisor capacity. | 3 |
| `HORIZON_STAGING_INTEGRATION_MAX_PROCESSES` | Staging | `integrations` supervisor capacity. | 2 |
| `HORIZON_PRODUCTION_CORE_MAX_PROCESSES` | Production | `default` + `notifications` supervisor capacity. | 8 |
| `HORIZON_PRODUCTION_INTEGRATION_MAX_PROCESSES` | Production | `integrations` supervisor capacity. | 4 |

The staging and production `maintenance` supervisors have fixed repository defaults (1 staging, 2 production) rather than environment-variable process counts.

Hosted validation requires at least one Horizon supervisor for the active hosted environment and requires every configured `maxProcesses` value to be between 1 and 64.

## Pulse

| Variable | Purpose | Hosted requirement |
| --- | --- | --- |
| `PULSE_ENABLED` | Enable Laravel Pulse recording. | Must remain `false` for hosted releases until the repository introduces the required schema and access policy. |

Do not enable Pulse solely because the package is installed. Current hosted validation intentionally rejects it.

## Production launch thresholds

These variables tune the repository-controlled `app:launch-check` gate:

| Variable | Default | Meaning |
| --- | ---: | --- |
| `LAUNCH_MINIMUM_PLATFORM_ADMINISTRATORS` | 2 | Minimum active platform administrators. Runtime enforces a floor of 2. |
| `LAUNCH_OUTBOX_GRACE_MINUTES` | 15 | Age after which an unpublished outbox row is considered overdue. |
| `LAUNCH_MAXIMUM_OVERDUE_OUTBOX` | 0 | Maximum overdue unpublished outbox rows allowed. |
| `LAUNCH_MAXIMUM_FAILED_JOBS` | 0 | Maximum rows allowed in `failed_jobs`. |
| `LAUNCH_WEBHOOK_FAILURE_WINDOW_MINUTES` | 60 | Lookback for permanently failed webhook deliveries. |
| `LAUNCH_MAXIMUM_RECENT_WEBHOOK_FAILURES` | 25 | Maximum failed webhook deliveries in that window. |

These are launch/readiness thresholds, not substitutes for production alert policies. Any relaxation should be recorded as an operational decision with rationale.

## Deployment-host controls

The following variables are consumed by `bin/deploy`; they are deployment-script controls, not application runtime settings:

| Variable | Default | Purpose |
| --- | --- | --- |
| `COMPOSE_FILE` | `docker-compose.staging.yml` | Compose topology used by the deployment script. |
| `ENV_FILE` | `deploy/staging.env` | Protected runtime environment file. |
| `STAGING_HTTP_PORT` | `8080` | Host port used by the staging topology. |
| `STAGING_URL` | `http://127.0.0.1:<port>` | URL used for deployment health checks; hosted use should be HTTPS. |
| `DATABASE_READY_ATTEMPTS` | 30 | Number of two-second PostgreSQL readiness attempts. |
| `HEALTHCHECK_ATTEMPTS` | 30 | Number of two-second runtime health/image-verification attempts. |
| `SKIP_BACKUP` | `NO` | Emergency/controlled opt-out; only `YES` skips pre-migration backup. Record every use. |
| `SKIP_MIGRATIONS` | `NO` | Emergency/rollback control; only `YES` skips migrations. Record every use. |

`APP_IMAGE` and `STAGING_ENV_FILE` are exported internally by `bin/deploy` after validating the immutable image and selected environment file. Do not use them to bypass the deploy script's digest and file-permission checks.

## Secret handling

Never commit real values for:

- `APP_KEY`;
- database/Redis/mail passwords or credential-bearing URLs;
- AWS/S3 access keys;
- webhook signing secrets or API credentials;
- private service addresses when repository disclosure is not approved; or
- recovery keys/material.

Environment files used by `bin/deploy` must be owner-readable only (`0400` or `0600`). Keep production secret values in the approved secret-management system and retain only non-sensitive evidence identifiers in repository launch records.

## Change procedure

When adding or renaming an operational setting:

1. update the consuming `config/*.php` or deployment script;
2. update the appropriate example environment file;
3. update `RuntimeConfigurationValidator` if the setting is required for hosted correctness/security;
4. update this reference;
5. update launch/deployment/recovery guidance when the setting changes an operational invariant; and
6. add or update tests so stale or ineffective settings are detected where practical.

A variable listed in an example file but not consumed by runtime code is a configuration defect, not an undocumented feature.