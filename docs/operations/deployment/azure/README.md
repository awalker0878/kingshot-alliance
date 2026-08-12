# Azure deployment blueprint

[← Provider deployments](../README.md) · [Azure Container Apps runbook](../../runbooks/azure-container-apps.md) · [ADR 0009](../../../adr/0009-azure-container-apps-runtime-topology.md)

**Document type:** Current provider deployment blueprint  
**Status:** Current  
**Provider:** Microsoft Azure

This directory captures the complete Azure deployment discussed and validated for Kingshot Alliance. Commands are written for **Azure CLI executed from PowerShell / Azure Cloud Shell PowerShell** and deliberately use placeholders instead of real environment identifiers or secrets.

## Target application stack

The hosted application image contains PHP 8.5, Laravel 13, Inertia 3, Vue 3, TypeScript, Tailwind CSS 4, Vite 8, Nginx, PHP-FPM, Horizon, Sanctum, Pennant, and the repository's release/runtime validation. PostgreSQL 18 is provided by Azure Database for PostgreSQL Flexible Server. Azure Managed Redis currently provides Redis 7.4.x; Redis 8 remains suitable for local/CI but is not claimed as the managed Azure production version.

## Target Azure topology

```text
Internet
   |
   | HTTPS :443
   v
Azure Container Apps ingress
   |
   | HTTP :8080
   v
+----------------------------------------------------+
| web Container App replica                          |
|                                                    |
|  nginx container                                   |
|  same immutable image                              |
|  /etc/nginx/azure.conf                             |
|  :8080                                             |
|       |                                            |
|       | FastCGI 127.0.0.1:9000                    |
|       v                                            |
|  app container                                     |
|  same immutable image                              |
|  kingshot-entrypoint -> php-fpm                    |
|  :9000                                             |
+----------------------------------------------------+

+------------------------+       +-------------------+
| Horizon Container App  |       | Container Apps    |
| same immutable image   |       | Jobs              |
| artisan horizon        |       | schedule:run      |
+------------------------+       | migrate --force   |
                                 +-------------------+

           | private VNet connectivity
           +----------------------+---------------------+
           |                      |                     |
           v                      v                     v
 PostgreSQL 18             Azure Managed Redis      Key Vault
 Flexible Server           Private Endpoint         secret refs
 delegated subnet          TLS / private DNS
```

The Container Apps environment is attached to a dedicated VNet subnet. PostgreSQL uses private VNet integration in its own delegated subnet. Azure Managed Redis uses a private endpoint in a separate private-endpoint subnet with public network access disabled.

## Deployment sequence

Follow these documents in order:

1. [Bootstrap](bootstrap.md) — variables, Azure login/extensions/providers, resource group, Log Analytics/Application Insights, ACR, managed identities, Key Vault, and Laravel `APP_KEY` generation.
2. [Networking](networking.md) — VNet, delegated subnets, private endpoint subnet, DNS zones, and ingress/TLS flow.
3. [Data services](data-services.md) — PostgreSQL 18 private deployment, Azure Managed Redis private endpoint/TLS configuration, secret storage, and connection verification.
4. [Container Apps](container-apps.md) — Container Apps environment creation, immutable image build, multi-container web replica, Horizon, scheduler job, migration job, HTTPS ingress, and revision behavior.
5. [Application configuration](application-configuration.md) — Laravel environment contract, HTTPS/proxy handling, PostgreSQL/Redis TLS, sessions, Sanctum, Horizon, Pennant, Pulse, and storage notes.
6. [GitHub Actions](github-actions.md) — OIDC federation, managed identity, least-privilege roles, build-once/promotion model, and deployment workflow shape.
7. [Validation and recovery](validation-and-recovery.md) — revisions, replicas, logs, stream-timeout diagnosis, private DNS verification, migrations, health gates, rollback, backup/restore, and recovery validation.

## Placeholder convention

Angle-bracket values are intentionally non-real examples and must be replaced at execution time, for example:

```text
<AZURE-SUBSCRIPTION-ID>
<AZURE-REGION>
<APP-PREFIX>
<GITHUB-OWNER>
<GITHUB-REPOSITORY>
<CUSTOM-DOMAIN>
```

Generated resource names use variables and random suffixes rather than hard-coded live names. Secrets are never embedded in this repository.

## Architecture invariants

- Build one immutable image per reviewed Git commit and reuse the exact image across web, Horizon, scheduler, and migration roles.
- Nginx and PHP-FPM are separate containers in the same web Container App replica because they are tightly coupled and scale/revise together.
- Horizon is a separate Container App because queue processing has a different scaling/lifecycle boundary.
- Scheduler and migration work are finite Container Apps Jobs, not web startup side effects.
- Azure ingress terminates public TLS; Nginx listens internally on HTTP port 8080 and PHP-FPM on FastCGI port 9000.
- PHP-FPM port 9000 is never configured as public Container Apps ingress.
- The PHP-FPM web container preserves the image's normal `kingshot-entrypoint` + `php-fpm` startup so hosted configuration validation runs.
- Command-overridden worker/job roles explicitly invoke `kingshot-entrypoint` before the Artisan command.
- PostgreSQL and Redis are private network dependencies.
- `APP_VERSION` and `RELEASE_SHA` are baked into the image at build time; they are not normal runtime overrides.
- Hosted Pulse remains disabled until its repository schema/access policy is approved.

## Source-of-truth boundary

These files are an Azure implementation blueprint. Exact current application configuration remains owned by repository code, tests, [runtime configuration](../../configuration-reference.md), [ADR 0009](../../../adr/0009-azure-container-apps-runtime-topology.md), and the generic deployment/recovery runbooks. If Azure behavior or the application architecture changes, update those owners together rather than allowing this blueprint to drift.
