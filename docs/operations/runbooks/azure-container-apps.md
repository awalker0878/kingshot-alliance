# Azure Container Apps Runbook

[← Operations documentation](../README.md) · [Deployment](deployment.md) · [ADR 0009](../../adr/0009-azure-container-apps-runtime-topology.md)

## Purpose

This runbook defines the supported Azure Container Apps topology for Kingshot Alliance. It supplements the generic deployment runbook; it does not replace immutable-image, migration, backup, health-gate, or release-evidence requirements.

The Azure web runtime is one Container App with two tightly coupled containers per replica:

```text
Internet
   |
   | HTTPS :443
   v
Azure Container Apps ingress
   |
   | HTTP targetPort :8080
   v
+----------------------------------+
| kingshot-<env>-web replica       |
|                                  |
| nginx container                  |
| nginx :8080                      |
|      |                           |
|      | FastCGI                   |
|      v                           |
| 127.0.0.1:9000                  |
|      |                           |
|      v                           |
| app container                    |
| kingshot-entrypoint -> php-fpm   |
|                                  |
+----------------------------------+
```

Nginx and PHP-FPM scale and revise together. Horizon is a separate Container App. Scheduler execution and migrations are Container Apps Jobs.

## 1. Prerequisites and invariants

Before deployment, confirm:

- the application image was built from the reviewed Git commit with non-placeholder `APP_VERSION` and 40-character lowercase `RELEASE_SHA` build arguments;
- the same immutable image digest/tag is used for Nginx, PHP-FPM, Horizon, scheduler, and migration roles;
- Container Apps ingress is external only for the web application and has `allowInsecure=false`;
- ingress `targetPort` is `8080`;
- PHP-FPM port `9000` is not configured as Container Apps ingress;
- PostgreSQL and Redis are reachable only through the approved private network path;
- Azure Managed Redis uses TLS through `REDIS_SCHEME=tls` and the managed Redis service port configured for the environment;
- `APP_URL` is HTTPS and `SESSION_SECURE_COOKIE=true`;
- Laravel Pulse remains disabled while hosted validation requires `PULSE_ENABLED=false`.

Do not add `start-web.sh` or a process supervisor to combine Nginx and PHP-FPM. The accepted runtime boundary is two containers in one web replica.

## 2. Build one immutable image

From the reviewed checkout:

```powershell
$ReleaseSha = (git rev-parse HEAD).Trim()
$AppVersion = "0.1.0-staging"
$ImageTag = $ReleaseSha

az acr build `
    --registry $ACR `
    --image "${ImageRepository}:${ImageTag}" `
    --build-arg "APP_VERSION=$AppVersion" `
    --build-arg "RELEASE_SHA=$ReleaseSha" `
    .

$Image = "$AcrServer/${ImageRepository}:${ImageTag}"
```

The Docker build validates the ACA-specific Nginx configuration with:

```text
nginx -t -c /etc/nginx/azure.conf
```

Do not use `latest` for a hosted release.

## 3. Web Container App definition

Azure Container Apps `command` is an ENTRYPOINT override. Therefore the PHP-FPM container must not define a command override: it needs the image's normal `kingshot-entrypoint` followed by the default `php-fpm` command.

The Nginx container intentionally overrides ENTRYPOINT because it does not need to run Laravel startup validation.

The effective web template is:

```yaml
properties:
  configuration:
    ingress:
      external: true
      allowInsecure: false
      targetPort: 8080
      transport: Auto
  template:
    containers:
      - name: nginx
        image: <same-immutable-image>
        command:
          - nginx
        args:
          - -c
          - /etc/nginx/azure.conf
          - -g
          - daemon off;
        resources:
          cpu: 0.25
          memory: 0.5Gi

      - name: app
        image: <same-immutable-image>
        # No command/args override. Preserve:
        # ENTRYPOINT ["kingshot-entrypoint"]
        # CMD ["php-fpm"]
        env:
          - name: APP_ENV
            value: staging
          - name: APP_URL
            value: https://<container-app-fqdn>
          - name: SESSION_SECURE_COOKIE
            value: "true"
          - name: SESSION_ENCRYPT
            value: "true"
          - name: PULSE_ENABLED
            value: "false"
          - name: DB_CONNECTION
            value: pgsql
          - name: DB_HOST
            value: <private-postgresql-fqdn>
          - name: DB_PORT
            value: "5432"
          - name: DB_SSLMODE
            value: require
          - name: REDIS_CLIENT
            value: phpredis
          - name: REDIS_SCHEME
            value: tls
          - name: REDIS_HOST
            value: <managed-redis-fqdn>
          - name: REDIS_PORT
            value: "10000"
        resources:
          cpu: 0.5
          memory: 1Gi
```

Secrets such as `APP_KEY`, `DB_PASSWORD`, and `REDIS_PASSWORD` must be Container Apps secret references backed by the approved secret-management path; do not place secret values in this manifest or repository.

The ACA Nginx profile is `/etc/nginx/azure.conf` and uses:

```nginx
fastcgi_pass 127.0.0.1:9000;
```

Do not use the Compose profile for ACA. `docker/nginx/default.conf` intentionally uses `app:9000` for Docker Compose service-name networking.

## 4. Verify ingress

```powershell
az containerapp show `
    --name $Web `
    --resource-group $RG `
    --query properties.configuration.ingress `
    --output json
```

Required values:

```text
external      true
allowInsecure false
targetPort    8080
transport     Auto
```

Get the canonical URL:

```powershell
$Fqdn = az containerapp show `
    --name $Web `
    --resource-group $RG `
    --query properties.configuration.ingress.fqdn `
    --output tsv

$AppUrl = "https://$Fqdn"
```

`APP_URL` must match the HTTPS endpoint (or the approved custom HTTPS domain).

## 5. Horizon Container App

Horizon has a different lifecycle and scaling boundary from web traffic. Run it as its own Container App with the same image.

Because ACA `command` overrides image ENTRYPOINT, preserve hosted configuration validation explicitly:

```powershell
az containerapp update `
    --name $Horizon `
    --resource-group $RG `
    --image $Image `
    --command kingshot-entrypoint `
    --args php artisan horizon
```

Use the same Laravel database, Redis, release, logging, and secret configuration as the PHP-FPM app container. Horizon has no external ingress.

Do not create Horizon as a sidecar of every web replica; web scaling must not multiply Horizon masters accidentally.

## 6. Scheduler Container Apps Job

Use Azure scheduling to invoke Laravel's scheduler once per minute rather than running `schedule:work` permanently in Azure:

```powershell
az containerapp job create `
    --name $Scheduler `
    --resource-group $RG `
    --environment $AcaEnv `
    --trigger-type Schedule `
    --cron-expression "*/1 * * * *" `
    --replica-timeout 300 `
    --replica-retry-limit 1 `
    --parallelism 1 `
    --replica-completion-count 1 `
    --image $Image `
    --registry-server $AcrServer `
    --registry-identity $RuntimeIdentityId `
    --mi-user-assigned $RuntimeIdentityId `
    --command kingshot-entrypoint `
    --args php artisan schedule:run `
    --cpu 0.25 `
    --memory 0.5Gi
```

Apply the same runtime environment/secrets required by scheduled commands. Container Apps scheduled-job cron is evaluated in UTC; application/domain time-zone decisions remain explicit in Laravel scheduling code.

## 7. Migration Container Apps Job

Run migrations exactly once per release through a manual job:

```powershell
az containerapp job create `
    --name $Migrate `
    --resource-group $RG `
    --environment $AcaEnv `
    --trigger-type Manual `
    --replica-timeout 900 `
    --replica-retry-limit 0 `
    --parallelism 1 `
    --replica-completion-count 1 `
    --image $Image `
    --registry-server $AcrServer `
    --registry-identity $RuntimeIdentityId `
    --mi-user-assigned $RuntimeIdentityId `
    --command kingshot-entrypoint `
    --args php artisan migrate --force `
    --cpu 0.25 `
    --memory 0.5Gi
```

Start it only after the required pre-migration backup/recovery evidence exists:

```powershell
az containerapp job start `
    --name $Migrate `
    --resource-group $RG
```

Do not set `RUN_MIGRATIONS=true` on web or Horizon Container Apps.

## 8. Azure Managed Redis

The Laravel Redis configuration supports an explicit connection scheme. For Azure Managed Redis over its private endpoint use the environment-specific host and:

```text
REDIS_CLIENT=phpredis
REDIS_SCHEME=tls
REDIS_HOST=<normal-managed-redis-fqdn>
REDIS_PORT=10000
REDIS_PASSWORD=<secretref>
```

Use the normal managed Redis FQDN configured for TLS validation; private DNS must resolve that service path to the private endpoint from the Container Apps VNet.

The local and Compose examples retain:

```text
REDIS_SCHEME=tcp
REDIS_PORT=6379
```

## 9. Validation and stop conditions

After a revision is created:

```powershell
az containerapp revision list `
    --name $Web `
    --resource-group $RG `
    --query "[].{Name:name,Active:properties.active,Health:properties.healthState,Running:properties.runningState}" `
    --output table
```

Require the candidate revision to become `Healthy` / `Running` before promotion.

Check console logs for both named containers. PHP-FPM startup should include the hosted configuration check and a ready FPM process. Nginx should accept requests on port 8080 and produce access logs on stdout.

Validate externally:

```text
GET https://<host>/up
GET https://<host>/health/ready
```

Stop promotion when any of the following occurs:

- `Runtime configuration is valid.` is absent from the PHP-FPM startup path;
- Nginx cannot reach `127.0.0.1:9000`;
- the revision remains `ActivationFailed`, `Unhealthy`, or `Degraded`;
- Redis resolves publicly when private networking is required;
- PostgreSQL or Redis TLS requirements are not satisfied;
- the release job fails or migration state is uncertain;
- the web/Horizon/jobs do not use the intended immutable image release.

## 10. Role summary

| Azure deployment unit | Containers/process | Scaling/lifecycle |
| --- | --- | --- |
| `kingshot-<env>-web` | Nginx + PHP-FPM | Scale/revise together on web demand |
| `kingshot-<env>-horizon` | `kingshot-entrypoint php artisan horizon` | Independent queue-worker capacity |
| scheduler job | `kingshot-entrypoint php artisan schedule:run` | One scheduled execution per minute |
| migration job | `kingshot-entrypoint php artisan migrate --force` | Manual, one release execution |
| Pulse worker | Not deployed while `PULSE_ENABLED=false` | Separate Container App if later approved |

Retain the same immutable release identity across every deployment unit and preserve the rollback digest/evidence required by the generic deployment runbook.
