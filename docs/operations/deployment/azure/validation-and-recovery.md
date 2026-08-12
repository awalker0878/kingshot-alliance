# Azure validation and recovery

[← Azure deployment blueprint](README.md)

**Document type:** Current provider deployment procedure  
**Status:** Current

This procedure validates the Azure deployment after provisioning and provides the first-line diagnosis for Container Apps startup/ingress failures, private dependency failures, migrations, rollback, and recovery exercises. It supplements the generic [deployment](../../runbooks/deployment.md), [rollback](../../runbooks/rollback.md), and [backup/restore](../../runbooks/backup-restore.md) runbooks.

## 1. Verify ingress configuration

```powershell
az containerapp show `
    --name $Web `
    --resource-group $RG `
    --query properties.configuration.ingress `
    --output json
```

Expected properties:

```text
external: true
allowInsecure: false
targetPort: 8080
transport: Auto
```

A correct ingress configuration only proves where Azure sends traffic. It does not prove that Nginx is listening or that PHP-FPM/dependencies are healthy.

## 2. Verify revision state

```powershell
az containerapp revision list `
    --name $Web `
    --resource-group $RG `
    --all `
    --query "[].{Name:name,Active:properties.active,Health:properties.healthState,Running:properties.runningState}" `
    --output table
```

The current revision should progress to `Healthy` / `Running`. `ActivationFailed`, `Unhealthy`, or a revision stuck in `Activating` requires log inspection before testing the public URL repeatedly.

## 3. Read system logs first when activation fails

```powershell
az containerapp logs show `
    --name $Web `
    --resource-group $RG `
    --type system `
    --tail 100 `
    --format text
```

System logs are useful when a container cannot start, an image cannot be pulled, a probe fails, or the platform cannot activate a revision.

## 4. Target the exact revision, replica, and container

Get the newest active revision:

```powershell
$Revision = az containerapp revision list `
    --name $Web `
    --resource-group $RG `
    --query "[?properties.active==``true``] | [0].name" `
    --output tsv
```

List replicas:

```powershell
az containerapp replica list `
    --name $Web `
    --resource-group $RG `
    --revision $Revision `
    --query "[].{Replica:name,Containers:properties.containers[].name}" `
    --output table
```

Capture a replica name:

```powershell
$Replica = az containerapp replica list `
    --name $Web `
    --resource-group $RG `
    --revision $Revision `
    --query "[0].name" `
    --output tsv
```

Read PHP-FPM/Laravel startup logs:

```powershell
az containerapp logs show `
    --name $Web `
    --resource-group $RG `
    --revision $Revision `
    --replica $Replica `
    --container app `
    --type console `
    --tail 100 `
    --format text
```

A healthy startup includes the conceptual sequence:

```text
Runtime configuration is valid.
NOTICE: fpm is running
NOTICE: ready to handle connections
```

Read Nginx logs separately:

```powershell
az containerapp logs show `
    --name $Web `
    --resource-group $RG `
    --revision $Revision `
    --replica $Replica `
    --container nginx `
    --type console `
    --tail 100 `
    --format text
```

This separation is one reason for the multi-container design: each process has its own platform lifecycle/log stream.

## 5. Runtime configuration failures

The application intentionally fails closed when hosted configuration violates repository requirements. Examples include:

```text
Hosted releases must declare a non-placeholder application version.
Hosted releases must declare a 40-character lowercase Git release SHA.
Pulse recording must remain disabled until its schema and access policy are introduced.
Staging session cookies must be secure unless insecure loopback staging is explicitly approved.
```

Do not disable `app:config-check` to make the container start. Correct the image metadata or runtime settings.

The normal fixes are:

```text
APP_VERSION=<non-placeholder build argument>
RELEASE_SHA=<40 lowercase hex Git SHA build argument>
PULSE_ENABLED=false
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
APP_URL=https://...
```

`APP_VERSION` and `RELEASE_SHA` belong in the immutable image build, not as unrelated environment-specific replacements.

## 6. Diagnose `stream timeout`

A Container Apps `stream timeout` means the ingress proxy did not receive a timely HTTP response from the configured backend. Check in this order:

1. `targetPort` is `8080`.
2. Nginx container is actually running and listening on `8080`.
3. PHP-FPM container is running and accepting FastCGI on `9000`.
4. Nginx uses `/etc/nginx/azure.conf`, whose upstream is `127.0.0.1:9000`.
5. Laravel startup validation has not exited the app container.
6. PostgreSQL/Redis dependencies are not hanging request processing.

The historical failure mode that motivated ADR 0009 was a single container starting only `php-fpm` while ACA ingress targeted HTTP port 8080. PHP-FPM is FastCGI, not an HTTP server. The accepted two-container web replica removes that mismatch by running Nginx as the HTTP listener.

## 7. Exec into the Nginx container

```powershell
az containerapp exec `
    --name $Web `
    --resource-group $RG `
    --revision $Revision `
    --replica $Replica `
    --container nginx `
    --command "/bin/sh"
```

Inside the container:

```sh
curl -v http://127.0.0.1:8080/up
```

A successful HTTP response proves Nginx is listening and can complete the Laravel FastCGI path.

Test whether the shared replica network can open PHP-FPM's FastCGI port:

```sh
php -r '$s=@fsockopen("127.0.0.1",9000,$errno,$errstr,3); if (!$s) { fwrite(STDERR,"FPM unavailable\n"); exit(1); } fclose($s); echo "FPM reachable\n";'
```

Do not try `curl http://127.0.0.1:9000`; port 9000 speaks FastCGI, not HTTP.

## 8. Exec into the application container

```powershell
az containerapp exec `
    --name $Web `
    --resource-group $RG `
    --revision $Revision `
    --replica $Replica `
    --container app `
    --command "/bin/sh"
```

Inside:

```sh
php artisan app:config-check
php artisan migrate:status
```

`migrate:status` exercises PostgreSQL connectivity and should not be used as a substitute for the one-shot migration release job.

## 9. Verify private DNS from the application container

From the `app` container shell:

```sh
getent hosts "$DB_HOST"
getent hosts "$REDIS_HOST"
```

Expected behavior:

- PostgreSQL resolves through the private PostgreSQL DNS integration.
- The **normal** Azure Managed Redis hostname resolves to a private endpoint address.

Do not paste the resulting private IP addresses into tracked evidence.

If Redis resolution returns a public address or no address, inspect:

```powershell
az network private-dns link vnet show `
    --resource-group $RG `
    --zone-name $RedisPrivateDnsZone `
    --name $RedisDnsLink
```

and the private endpoint DNS zone group:

```powershell
az network private-endpoint dns-zone-group list `
    --resource-group $RG `
    --endpoint-name $RedisPrivateEndpoint `
    --output table
```

## 10. Verify Redis endpoint allocation without exposing it

```powershell
$RedisNicId = az network private-endpoint show `
    --name $RedisPrivateEndpoint `
    --resource-group $RG `
    --query "networkInterfaces[0].id" `
    --output tsv

$RedisPrivateIp = az network nic show `
    --ids $RedisNicId `
    --query "ipConfigurations[0].privateIPAddress" `
    --output tsv

if ([string]::IsNullOrWhiteSpace($RedisPrivateIp)) {
    throw "Redis private endpoint did not expose an allocated private address."
}

Write-Host "Redis private endpoint has an allocated address."
```

This deliberately avoids printing the address.

## 11. Health endpoints

After the revision is healthy:

```powershell
$AppUrl = "https://$(az containerapp show `
    --name $Web `
    --resource-group $RG `
    --query properties.configuration.ingress.fqdn `
    --output tsv)"

Invoke-WebRequest "$AppUrl/up" -UseBasicParsing
Invoke-WebRequest "$AppUrl/health/ready" -UseBasicParsing
```

`/up` proves the HTTP runtime can answer. `/health/ready` is the stronger application readiness gate and should be used before considering a release stable.

## 12. Validate Horizon

```powershell
az containerapp logs show `
    --name $Horizon `
    --resource-group $RG `
    --container $Horizon `
    --tail 100 `
    --format text
```

Container naming can differ depending on the CLI-created template; if the command reports the container name is invalid, inspect the app template first:

```powershell
az containerapp show `
    --name $Horizon `
    --resource-group $RG `
    --query "properties.template.containers[].name" `
    --output tsv
```

The important validation is that Horizon stays running, uses the same immutable image digest as the web revision, and can reach Redis/PostgreSQL as required.

## 13. Validate scheduler and migration jobs

```powershell
az containerapp job execution list `
    --name $Scheduler `
    --resource-group $RG `
    --output table

az containerapp job execution list `
    --name $Migrate `
    --resource-group $RG `
    --output table
```

Scheduled jobs use UTC cron evaluation. Confirm the scheduler is producing successful executions at the intended cadence and that migration execution succeeded exactly once for the release.

## 14. Image identity verification

The web containers must use the same image reference:

```powershell
az containerapp show `
    --name $Web `
    --resource-group $RG `
    --query "properties.template.containers[].{Name:name,Image:image}" `
    --output table
```

Also inspect Horizon and both jobs. A release is invalid if different runtime roles silently run different application digests.

## 15. Rollback

Rollback uses the **previous approved immutable digest**. Do not rebuild an old Git commit and call the rebuilt image equivalent.

Conceptual rollback sequence:

```text
1. identify previous approved digest
2. determine whether database rollback is actually safe/required
3. update web nginx + app together to previous digest
4. update Horizon/scheduler/migration job definitions consistently
5. run the approved rollback or forward-fix database procedure
6. require /up and /health/ready
7. verify queues, logs, release identity, and domain-specific state
```

Follow the generic [rollback runbook](../../runbooks/rollback.md) for stop conditions and data-safety requirements.

## 16. PostgreSQL backup and restore

Azure PostgreSQL backup retention configured during server creation is only one recovery layer. The application's generic release runbook requires explicit backup/restore evidence around migrations and production recovery.

For production approval, record and test:

- PostgreSQL point-in-time recovery expectations;
- pre-migration logical/application backup where required by the release procedure;
- restoration into an isolated target;
- application readiness against restored data;
- private media restoration; and
- recovery of the exact Laravel `APP_KEY` associated with encrypted application data.

Do not treat "Azure says backups are enabled" as proof that the complete application recovery set has been exercised.

## 17. Redis recovery boundary

Redis is critical to sessions, cache, queues, Horizon, and scheduler coordination, but it is not the relational system of record. If production requirements depend on Redis persistence/export, explicitly configure and exercise the selected Azure Managed Redis persistence/export capability.

Application recovery must also define what happens to:

- queued but unprocessed work;
- failed jobs;
- active sessions;
- Horizon metadata; and
- distributed locks/coordinator state.

## 18. Key Vault recovery

The Laravel `APP_KEY` is recovery-critical. Key Vault protection, deletion/recovery policy, operator access, and restoration evidence must be part of the production recovery plan. Never generate a new `APP_KEY` as a routine rollback action for an existing data set.

## 19. Release evidence to retain

Retain non-secret evidence such as:

- reviewed Git commit SHA;
- application release version;
- ACR image digest;
- successful migration job execution ID/status;
- active Container Apps revision names/health state;
- health-check results;
- GitHub Actions run/check identity;
- backup/recovery evidence identifiers; and
- rollback digest.

Do **not** retain secret values, private endpoint addresses, database passwords, Redis keys, Laravel application keys, or access tokens in release evidence.

## 20. Production launch boundary

A successful staging deployment and recovery demonstration proves only repository-controlled/provider-staging behavior. Production still requires the external infrastructure, security, operator, backup, capacity, monitoring, alerting, DNS/certificate, and approval evidence defined by [production launch approval](../../../product/production-launch-approval.md).
