# Azure Container Apps

[← Azure deployment blueprint](README.md)

**Document type:** Current provider deployment procedure  
**Status:** Current

This procedure creates the Azure Container Apps environment, builds one immutable release image, deploys the two-container web replica, and creates separate Horizon, scheduler, and migration execution units.

## 1. Create the Container Apps environment

This step requires `$AcaSubnetId`, `$LawId`, and `$LawKey` from the earlier procedures.

```powershell
# ============================================================
# AZURE CONTAINER APPS ENVIRONMENT
# ============================================================

az containerapp env create `
    --name $AcaEnv `
    --resource-group $RG `
    --location $Location `
    --infrastructure-subnet-resource-id $AcaSubnetId `
    --logs-workspace-id $LawId `
    --logs-workspace-key $LawKey

$LawKey = $null

$AcaEnvId = az containerapp env show `
    --name $AcaEnv `
    --resource-group $RG `
    --query id `
    --output tsv

$AcaDefaultDomain = az containerapp env show `
    --name $AcaEnv `
    --resource-group $RG `
    --query properties.defaultDomain `
    --output tsv
```

The workload-profile environment uses the dedicated subnet from [Networking](networking.md), giving Container Apps private reachability to PostgreSQL and the Redis private endpoint.

## 2. Build one immutable image

The Dockerfile requires real release metadata for hosted releases. Build from the reviewed Git commit and use the Git SHA as the unique tag:

```powershell
# ============================================================
# BUILD IMMUTABLE RELEASE IMAGE
# ============================================================

$ReleaseSha = (git rev-parse HEAD).Trim()
$AppVersion = "<RELEASE-VERSION>"
$ImageTag   = $ReleaseSha

if ($ReleaseSha.Length -ne 40 -or $ReleaseSha -cnotmatch '^[0-9a-f]{40}$') {
    throw "RELEASE_SHA must be a 40-character lowercase Git SHA."
}

az acr build `
    --registry $ACR `
    --image "${ImageRepository}:${ImageTag}" `
    --build-arg "APP_VERSION=$AppVersion" `
    --build-arg "RELEASE_SHA=$ReleaseSha" `
    .
```

Resolve the pushed digest and deploy by digest rather than by a mutable tag:

```powershell
$ImageDigest = az acr repository show `
    --name $ACR `
    --image "${ImageRepository}:${ImageTag}" `
    --query digest `
    --output tsv

$Image = "$AcrServer/$ImageRepository@$ImageDigest"

Write-Host "Release SHA: $ReleaseSha"
Write-Host "Image digest resolved: $([string]::IsNullOrWhiteSpace($ImageDigest) -eq $false)"
```

`APP_VERSION` and `RELEASE_SHA` are now embedded in the image/OCI metadata. Do not add different runtime overrides for those values.

## 3. Resolve the initial HTTPS application URL

The default Container Apps FQDN is based on the app name and environment default domain, so it can be constructed before the first web revision starts:

```powershell
# ============================================================
# APPLICATION URL
# ============================================================

$Fqdn   = "$Web.$AcaDefaultDomain"
$AppUrl = "https://$Fqdn"
```

This allows the startup configuration validator to receive an HTTPS `APP_URL` on the first revision.

## 4. Prepare Key Vault secret references

If these variables are not still present from earlier steps, retrieve the secret URIs without retrieving their values:

```powershell
$AppKeyUri = az keyvault secret show `
    --vault-name $KV `
    --name app-key `
    --query id `
    --output tsv

$DbPasswordUri = az keyvault secret show `
    --vault-name $KV `
    --name db-password `
    --query id `
    --output tsv

$RedisPasswordUri = az keyvault secret show `
    --vault-name $KV `
    --name redis-password `
    --query id `
    --output tsv
```

These URIs are environment-specific metadata. Do not copy them into a tracked YAML file.

## 5. Deploy the two-container web replica

Azure CLI's single-container flags are not a good fit for this web topology. Generate a **temporary untracked YAML file** in the shell, deploy it, then delete it. The YAML contains Key Vault references but no secret values.

```powershell
# ============================================================
# WEB APP - TEMPORARY MULTI-CONTAINER YAML
# ============================================================

$WebYaml = Join-Path ([System.IO.Path]::GetTempPath()) "$Web.yaml"

@"
location: $Location
identity:
  type: UserAssigned
  userAssignedIdentities:
    "$RuntimeIdentityId": {}
properties:
  environmentId: $AcaEnvId
  configuration:
    activeRevisionsMode: Single
    ingress:
      external: true
      allowInsecure: false
      targetPort: 8080
      transport: Auto
    registries:
      - server: $AcrServer
        identity: $RuntimeIdentityId
    secrets:
      - name: app-key
        keyVaultUrl: $AppKeyUri
        identity: $RuntimeIdentityId
      - name: db-password
        keyVaultUrl: $DbPasswordUri
        identity: $RuntimeIdentityId
      - name: redis-password
        keyVaultUrl: $RedisPasswordUri
        identity: $RuntimeIdentityId
  template:
    scale:
      minReplicas: 1
      maxReplicas: 3
    containers:
      - name: nginx
        image: $Image
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
        image: $Image
        env:
          - name: APP_ENV
            value: staging
          - name: APP_DEBUG
            value: "false"
          - name: APP_URL
            value: $AppUrl
          - name: APP_KEY
            secretRef: app-key
          - name: LOG_CHANNEL
            value: stack
          - name: LOG_STACK
            value: stderr
          - name: LOG_LEVEL
            value: info

          - name: DB_CONNECTION
            value: pgsql
          - name: DB_HOST
            value: $PgHost
          - name: DB_PORT
            value: "5432"
          - name: DB_DATABASE
            value: $Database
          - name: DB_USERNAME
            value: $PgAdmin
          - name: DB_PASSWORD
            secretRef: db-password
          - name: DB_SSLMODE
            value: require

          - name: CACHE_STORE
            value: redis
          - name: QUEUE_CONNECTION
            value: redis
          - name: SESSION_DRIVER
            value: redis
          - name: SESSION_LIFETIME
            value: "120"
          - name: SESSION_ENCRYPT
            value: "true"
          - name: SESSION_SECURE_COOKIE
            value: "true"
          - name: SESSION_SAME_SITE
            value: lax

          - name: REDIS_CLIENT
            value: phpredis
          - name: REDIS_HOST
            value: $RedisHost
          - name: REDIS_PORT
            value: "10000"
          - name: REDIS_PASSWORD
            secretRef: redis-password
          - name: REDIS_SCHEME
            value: tls
          - name: REDIS_DB
            value: "0"
          - name: REDIS_CACHE_DB
            value: "1"

          - name: FILESYSTEM_DISK
            value: local
          - name: CONTENT_MEDIA_DISK
            value: local

          - name: REGISTRATION_MODE
            value: open
          - name: INVITATION_TTL_HOURS
            value: "72"
          - name: SANCTUM_STATEFUL_DOMAINS
            value: $Fqdn

          - name: PULSE_ENABLED
            value: "false"
          - name: PENNANT_STORE
            value: database

          - name: SECURITY_CSP_ENABLED
            value: "true"
          - name: TRUSTED_PROXIES
            value: "*"
          - name: ALLOW_TRUST_ALL_PROXIES
            value: "true"
          - name: ALLOW_INSECURE_LOOPBACK_STAGING
            value: "false"
        resources:
          cpu: 0.5
          memory: 1.0Gi
"@ | Set-Content -Path $WebYaml -Encoding utf8

az containerapp create `
    --name $Web `
    --resource-group $RG `
    --yaml $WebYaml

Remove-Item $WebYaml -Force
```

### Why the two web containers differ

The `nginx` container intentionally overrides the image `ENTRYPOINT`; it only needs to run Nginx and serve the exact public assets embedded in the same release image.

The `app` container intentionally has **no** `command` or `args` override. It therefore preserves:

```text
ENTRYPOINT ["kingshot-entrypoint"]
CMD ["php-fpm"]
```

which means hosted configuration validation runs before PHP-FPM starts.

The web replica therefore behaves as:

```text
nginx container
PID 1 = nginx
:8080
   |
   | FastCGI
   v
127.0.0.1:9000
   |
app container
PID 1 = php-fpm (after kingshot-entrypoint validation)
```

## 6. Enforce and verify HTTPS ingress

The YAML already sets `allowInsecure: false`; enforce it again explicitly so the intended public posture is obvious and rerunnable:

```powershell
az containerapp ingress update `
    --name $Web `
    --resource-group $RG `
    --target-port 8080 `
    --transport auto `
    --allow-insecure false
```

Verify the actual FQDN:

```powershell
$ActualFqdn = az containerapp show `
    --name $Web `
    --resource-group $RG `
    --query properties.configuration.ingress.fqdn `
    --output tsv

$ActualAppUrl = "https://$ActualFqdn"

Write-Host "HTTPS URL resolved: $([string]::IsNullOrWhiteSpace($ActualFqdn) -eq $false)"
```

If `$ActualFqdn` differs from the predicted `$Fqdn`, update `APP_URL` and `SANCTUM_STATEFUL_DOMAINS` to the actual host through a new revision before functional validation.

## 7. Horizon Container App

Horizon has a different scaling lifecycle from HTTP traffic, so it is a separate Container App. Because specifying `--command` overrides the image entrypoint in Container Apps, explicitly invoke `kingshot-entrypoint` before Artisan:

```powershell
# ============================================================
# HORIZON CONTAINER APP
# ============================================================

az containerapp create `
    --name $Horizon `
    --resource-group $RG `
    --environment $AcaEnv `
    --image $Image `
    --registry-server $AcrServer `
    --registry-identity $RuntimeIdentityId `
    --user-assigned $RuntimeIdentityId `
    --command kingshot-entrypoint `
    --args php artisan horizon `
    --cpu 0.5 `
    --memory 1.0Gi `
    --min-replicas 1 `
    --max-replicas 1 `
    --secrets `
        "app-key=keyvaultref:$AppKeyUri,identityref:$RuntimeIdentityId" `
        "db-password=keyvaultref:$DbPasswordUri,identityref:$RuntimeIdentityId" `
        "redis-password=keyvaultref:$RedisPasswordUri,identityref:$RuntimeIdentityId" `
    --env-vars `
        "APP_ENV=staging" `
        "APP_DEBUG=false" `
        "APP_URL=$AppUrl" `
        "APP_KEY=secretref:app-key" `
        "LOG_CHANNEL=stack" `
        "LOG_STACK=stderr" `
        "LOG_LEVEL=info" `
        "DB_CONNECTION=pgsql" `
        "DB_HOST=$PgHost" `
        "DB_PORT=5432" `
        "DB_DATABASE=$Database" `
        "DB_USERNAME=$PgAdmin" `
        "DB_PASSWORD=secretref:db-password" `
        "DB_SSLMODE=require" `
        "CACHE_STORE=redis" `
        "QUEUE_CONNECTION=redis" `
        "SESSION_DRIVER=redis" `
        "SESSION_ENCRYPT=true" `
        "SESSION_SECURE_COOKIE=true" `
        "SESSION_SAME_SITE=lax" `
        "REDIS_CLIENT=phpredis" `
        "REDIS_HOST=$RedisHost" `
        "REDIS_PORT=10000" `
        "REDIS_PASSWORD=secretref:redis-password" `
        "REDIS_SCHEME=tls" `
        "REDIS_DB=0" `
        "REDIS_CACHE_DB=1" `
        "FILESYSTEM_DISK=local" `
        "CONTENT_MEDIA_DISK=local" `
        "PULSE_ENABLED=false"
```

Do not set web ingress on Horizon.

## 8. Scheduler Container Apps Job

Use a scheduled job that invokes `schedule:run` once per minute. Container Apps scheduled-job cron expressions are evaluated in UTC.

```powershell
# ============================================================
# SCHEDULER JOB
# ============================================================

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
    --memory 0.5Gi `
    --secrets `
        "app-key=keyvaultref:$AppKeyUri,identityref:$RuntimeIdentityId" `
        "db-password=keyvaultref:$DbPasswordUri,identityref:$RuntimeIdentityId" `
        "redis-password=keyvaultref:$RedisPasswordUri,identityref:$RuntimeIdentityId" `
    --env-vars `
        "APP_ENV=staging" `
        "APP_DEBUG=false" `
        "APP_URL=$AppUrl" `
        "APP_KEY=secretref:app-key" `
        "DB_CONNECTION=pgsql" `
        "DB_HOST=$PgHost" `
        "DB_PORT=5432" `
        "DB_DATABASE=$Database" `
        "DB_USERNAME=$PgAdmin" `
        "DB_PASSWORD=secretref:db-password" `
        "DB_SSLMODE=require" `
        "CACHE_STORE=redis" `
        "QUEUE_CONNECTION=redis" `
        "SESSION_DRIVER=redis" `
        "SESSION_ENCRYPT=true" `
        "SESSION_SECURE_COOKIE=true" `
        "SESSION_SAME_SITE=lax" `
        "REDIS_CLIENT=phpredis" `
        "REDIS_HOST=$RedisHost" `
        "REDIS_PORT=10000" `
        "REDIS_PASSWORD=secretref:redis-password" `
        "REDIS_SCHEME=tls" `
        "FILESYSTEM_DISK=local" `
        "CONTENT_MEDIA_DISK=local" `
        "PULSE_ENABLED=false"
```

Use `schedule:run`, not a permanently running `schedule:work`, because Azure already owns the schedule trigger.

## 9. Manual migration Container Apps Job

Migrations are a finite release action and must not run automatically in every web replica startup.

```powershell
# ============================================================
# MIGRATION JOB
# ============================================================

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
    --memory 0.5Gi `
    --secrets `
        "app-key=keyvaultref:$AppKeyUri,identityref:$RuntimeIdentityId" `
        "db-password=keyvaultref:$DbPasswordUri,identityref:$RuntimeIdentityId" `
        "redis-password=keyvaultref:$RedisPasswordUri,identityref:$RuntimeIdentityId" `
    --env-vars `
        "APP_ENV=staging" `
        "APP_DEBUG=false" `
        "APP_URL=$AppUrl" `
        "APP_KEY=secretref:app-key" `
        "DB_CONNECTION=pgsql" `
        "DB_HOST=$PgHost" `
        "DB_PORT=5432" `
        "DB_DATABASE=$Database" `
        "DB_USERNAME=$PgAdmin" `
        "DB_PASSWORD=secretref:db-password" `
        "DB_SSLMODE=require" `
        "CACHE_STORE=redis" `
        "QUEUE_CONNECTION=redis" `
        "SESSION_DRIVER=redis" `
        "SESSION_ENCRYPT=true" `
        "SESSION_SECURE_COOKIE=true" `
        "SESSION_SAME_SITE=lax" `
        "REDIS_CLIENT=phpredis" `
        "REDIS_HOST=$RedisHost" `
        "REDIS_PORT=10000" `
        "REDIS_PASSWORD=secretref:redis-password" `
        "REDIS_SCHEME=tls" `
        "FILESYSTEM_DISK=local" `
        "CONTENT_MEDIA_DISK=local" `
        "PULSE_ENABLED=false"
```

Start it deliberately during the release:

```powershell
az containerapp job start `
    --name $Migrate `
    --resource-group $RG

az containerapp job execution list `
    --name $Migrate `
    --resource-group $RG `
    --output table
```

Release automation must wait for successful migration completion before the release is considered healthy.

## 10. Pulse worker

Do not create a hosted Pulse worker while the repository requires `PULSE_ENABLED=false`. If Pulse recording and a persistent `pulse:work` process are approved later, deploy that process as its own Container App rather than coupling it to web replicas.

## 11. Verify deployment units

```powershell
az containerapp list `
    --resource-group $RG `
    --query "[].{Name:name,Provisioning:properties.provisioningState}" `
    --output table

az containerapp job list `
    --resource-group $RG `
    --query "[].{Name:name,Trigger:properties.configuration.triggerType}" `
    --output table
```

The intended shape is one multi-container web app, one Horizon app, one scheduled job, and one manual migration job, all using the same immutable image digest.

## Next

Continue with [Application configuration](application-configuration.md), then [GitHub Actions](github-actions.md) and [Validation and recovery](validation-and-recovery.md).
