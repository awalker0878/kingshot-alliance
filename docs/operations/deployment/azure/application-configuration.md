# Azure application configuration

[← Azure deployment blueprint](README.md)

**Document type:** Current provider deployment procedure  
**Status:** Current

This document maps the repository's Laravel runtime contract onto Azure Container Apps. It supplements the canonical [runtime configuration reference](../../configuration-reference.md); when values differ, repository code and the canonical reference remain authoritative.

## 1. Release identity

Hosted releases require a non-placeholder application version and a 40-character lowercase Git SHA. These values are supplied as Docker build arguments and embedded in the immutable image:

```text
APP_VERSION=<release version baked into image>
RELEASE_SHA=<40-character lowercase Git SHA baked into image>
```

Do not normally set different `APP_VERSION` or `RELEASE_SHA` values in Container Apps environment variables. A runtime override that disagrees with the reviewed image defeats release identity and can be rejected by deployment controls.

## 2. Application and HTTPS

```text
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://<CONTAINER-APP-FQDN>
APP_KEY=<Key Vault secret reference>
```

Azure Container Apps terminates external TLS. The application image does not need a certificate or HTTPS listener inside the replica:

```text
HTTPS :443
    -> Azure Container Apps ingress
    -> HTTP :8080 nginx
    -> FastCGI :9000 PHP-FPM
```

`APP_URL=https://...` is the canonical URL; it does not mean PHP-FPM itself must terminate TLS.

## 3. Trusted proxies and forwarded HTTPS metadata

`bootstrap/app.php` reads `TRUSTED_PROXIES` and configures Laravel's trusted-proxy middleware. The ACA Nginx profile preserves the platform-provided forwarded request metadata into FastCGI:

```text
X-Forwarded-For
X-Forwarded-Host
X-Forwarded-Port
X-Forwarded-Proto
```

For the accepted ACA topology, the PHP-FPM container is reachable only through the tightly coupled Nginx container inside the replica and is not exposed as Container Apps ingress. The deployment therefore uses the repository's explicit trust-all approval pair:

```text
TRUSTED_PROXIES=*
ALLOW_TRUST_ALL_PROXIES=true
ALLOW_INSECURE_LOOPBACK_STAGING=false
```

Do not copy this wildcard configuration to a topology where the application/FastCGI service can be reached directly by untrusted networks. If stable explicit proxy addresses are available and operationally supported, prefer them.

## 4. Secure sessions

Normal hosted staging requires encrypted Redis-backed sessions and secure cookies:

```text
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
```

Do not set insecure cookies to work around an HTTPS/proxy misconfiguration. Fix the ingress or trusted-forwarded-header path instead.

## 5. PostgreSQL 18

```text
DB_CONNECTION=pgsql
DB_HOST=<AZURE-POSTGRESQL-FQDN>
DB_PORT=5432
DB_DATABASE=app
DB_USERNAME=appadmin
DB_PASSWORD=<Key Vault secret reference>
DB_SSLMODE=require
```

The server hostname is resolved through PostgreSQL private DNS from the Container Apps VNet. Do not substitute a private IP for the managed PostgreSQL FQDN.

Production may adopt stronger certificate verification (`verify-ca` / `verify-full`) when the corresponding trust material is managed and tested.

## 6. Azure Managed Redis

```text
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_CLIENT=phpredis
REDIS_HOST=<NORMAL-AZURE-MANAGED-REDIS-HOSTNAME>
REDIS_PORT=10000
REDIS_PASSWORD=<Key Vault secret reference>
REDIS_SCHEME=tls
REDIS_DB=0
REDIS_CACHE_DB=1
```

Use the normal Azure Managed Redis hostname returned by the service, not a `privatelink` hostname. Private DNS resolves that normal name to the private endpoint for VNet clients.

The managed Azure service currently runs Redis 7.4.x even though local/CI can use Redis 8. This difference must remain visible rather than being hidden by documentation.

## 7. Horizon

Horizon uses the same PostgreSQL/Redis configuration as the web runtime and runs as its own Container App:

```text
HORIZON_PREFIX=<ENVIRONMENT-SPECIFIC-PREFIX>
HORIZON_STAGING_CORE_MAX_PROCESSES=3
HORIZON_STAGING_INTEGRATION_MAX_PROCESSES=2
```

The exact process limits remain governed by `config/horizon.php` and capacity testing. Do not scale Horizon merely by increasing web replica count.

## 8. Sanctum

For the default Container Apps hostname:

```text
SANCTUM_STATEFUL_DOMAINS=<CONTAINER-APP-FQDN>
```

For a custom domain:

```text
APP_URL=https://<CUSTOM-DOMAIN>
SANCTUM_STATEFUL_DOMAINS=<CUSTOM-DOMAIN>
```

Set `SESSION_DOMAIN` only when the intended cookie-sharing domain design requires it. Do not use a broad parent-domain cookie scope by default.

## 9. Pennant

The staging blueprint uses the database-backed feature flag store:

```text
PENNANT_STORE=database
```

Any change to the feature-flag persistence model must be reflected in application configuration and migration/recovery procedures.

## 10. Pulse

Hosted Pulse recording remains deliberately disabled:

```text
PULSE_ENABLED=false
```

Do not set `PULSE_ENABLED=true` only because the package is installed. The repository's hosted validator intentionally blocks Pulse until the required schema and access policy are introduced. If a future approved design requires `pulse:work`, deploy it as a separate Container App.

## 11. Logging

```text
LOG_CHANNEL=stack
LOG_STACK=stderr
LOG_LEVEL=info
```

The repository emits JSON logs to stderr. Azure Container Apps sends console/system logs into the environment logging configuration, which is connected to Log Analytics in this blueprint.

Do not log secret values, Key Vault payloads, session contents, access tokens, or private data solely for troubleshooting.

## 12. Files and content media

For staging, the repository validator permits local storage:

```text
FILESYSTEM_DISK=local
CONTENT_MEDIA_DISK=local
```

Container writable filesystems are ephemeral and are not a durable production media store. Do not rely on `local` for persistent user uploads across replica replacement or scale-out.

Production Content media requires the repository's durable S3-backed contract. A production deployment must therefore provide the approved S3-compatible storage configuration, for example:

```text
FILESYSTEM_DISK=s3
CONTENT_MEDIA_DISK=s3
AWS_ACCESS_KEY_ID=<SECRET-OR-WORKLOAD-IDENTITY-MECHANISM>
AWS_SECRET_ACCESS_KEY=<SECRET-OR-WORKLOAD-IDENTITY-MECHANISM>
AWS_DEFAULT_REGION=<STORAGE-REGION>
AWS_BUCKET=<PRIVATE-BUCKET>
AWS_ENDPOINT=<APPROVED-S3-COMPATIBLE-ENDPOINT-IF-REQUIRED>
AWS_USE_PATH_STYLE_ENDPOINT=false
```

Do not put real storage credentials or bucket identifiers into tracked documentation. Provider-specific production object storage is a separate deployment decision from the staging bootstrap documented here.

## 13. Security controls

The current staging profile also includes:

```text
SECURITY_CSP_ENABLED=true
REGISTRATION_MODE=open
INVITATION_TTL_HOURS=72
```

`REGISTRATION_MODE` is product configuration, not an Azure requirement. Choose the production registration policy through product/security approval rather than carrying the staging value forward automatically.

## 14. Canonical hosted environment shape

The effective non-secret staging values are conceptually:

```dotenv
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://<CONTAINER-APP-FQDN>

LOG_CHANNEL=stack
LOG_STACK=stderr
LOG_LEVEL=info

DB_CONNECTION=pgsql
DB_HOST=<AZURE-POSTGRESQL-FQDN>
DB_PORT=5432
DB_DATABASE=app
DB_USERNAME=appadmin
DB_SSLMODE=require

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

REDIS_CLIENT=phpredis
REDIS_HOST=<NORMAL-AZURE-MANAGED-REDIS-HOSTNAME>
REDIS_PORT=10000
REDIS_SCHEME=tls
REDIS_DB=0
REDIS_CACHE_DB=1

FILESYSTEM_DISK=local
CONTENT_MEDIA_DISK=local

PULSE_ENABLED=false
PENNANT_STORE=database
SANCTUM_STATEFUL_DOMAINS=<CONTAINER-APP-FQDN>
SECURITY_CSP_ENABLED=true
TRUSTED_PROXIES=*
ALLOW_TRUST_ALL_PROXIES=true
ALLOW_INSECURE_LOOPBACK_STAGING=false
```

The following values are **not** represented literally above because they must come from Key Vault references:

```text
APP_KEY
DB_PASSWORD
REDIS_PASSWORD
```

## 15. Configuration validation

Every role that passes through `kingshot-entrypoint` runs the repository's hosted configuration validation before starting the requested process. A valid staging startup should emit:

```text
Runtime configuration is valid.
```

If validation fails, treat each reported message as a deployment blocker instead of disabling the validator.

## Related documentation

- [Runtime configuration reference](../../configuration-reference.md)
- [Azure Container Apps](container-apps.md)
- [Azure data services](data-services.md)
- [ADR 0009](../../../adr/0009-azure-container-apps-runtime-topology.md)
