# Azure data services

[← Azure deployment blueprint](README.md)

**Document type:** Current provider deployment procedure  
**Status:** Current

This procedure creates PostgreSQL 18 with private VNet integration and Azure Managed Redis with a private endpoint. Secret values are placed in Key Vault and must never be committed or copied into documentation.

## 1. PostgreSQL admin secret

Prompt for the database administrator password in the shell and immediately save it to Key Vault:

```powershell
# ============================================================
# POSTGRESQL ADMIN SECRET
# ============================================================

$PgPassword = Read-Host "PostgreSQL admin password"

az keyvault secret set `
    --vault-name $KV `
    --name db-password `
    --value $PgPassword `
    --output none
```

Use a strong generated value that meets Azure PostgreSQL password requirements. Do not echo it and do not add it to any `.env` or tracked deployment file.

## 2. PostgreSQL 18 Flexible Server

The sizing below is a **staging starting point**, not a production capacity decision.

```powershell
# ============================================================
# POSTGRESQL 18 - PRIVATE VNET INTEGRATION
# ============================================================

az postgres flexible-server create `
    --resource-group $RG `
    --name $Pg `
    --location $Location `
    --admin-user $PgAdmin `
    --admin-password $PgPassword `
    --version 18 `
    --tier Burstable `
    --sku-name Standard_B2s `
    --storage-size 32 `
    --storage-auto-grow Enabled `
    --backup-retention 7 `
    --subnet $PgSubnetId `
    --private-dns-zone $PgDnsId `
    --public-access Disabled

$PgPassword = $null
```

Create the application database:

```powershell
az postgres flexible-server db create `
    --resource-group $RG `
    --server-name $Pg `
    --database-name $Database
```

Capture the server hostname, not an IP address:

```powershell
$PgHost = az postgres flexible-server show `
    --resource-group $RG `
    --name $Pg `
    --query fullyQualifiedDomainName `
    --output tsv
```

Store only the Key Vault secret URI for later Container Apps references:

```powershell
$DbPasswordUri = az keyvault secret show `
    --vault-name $KV `
    --name db-password `
    --query id `
    --output tsv
```

Laravel's hosted configuration uses:

```text
DB_CONNECTION=pgsql
DB_HOST=<AZURE-POSTGRESQL-FQDN>
DB_PORT=5432
DB_DATABASE=<APPLICATION-DATABASE>
DB_USERNAME=<APPLICATION-DATABASE-ADMIN>
DB_PASSWORD=<Key Vault secret reference>
DB_SSLMODE=require
```

Production may choose `verify-ca` or `verify-full` when the complete certificate trust configuration is managed and validated.

## 3. Azure Managed Redis service level and clustering policy

Azure Managed Redis currently provides Redis 7.4.x as the managed service version. The repository can continue using Redis 8 for local/CI, but this blueprint does not claim that Azure Managed Redis is Redis 8.

The staging baseline is `Balanced_B0` with **`NoCluster`**. The Laravel runtime uses a normal PhpRedis connection and does not require Redis Cluster slot routing. Explicitly selecting `NoCluster` prevents `MOVED <slot> <host>:<port>` redirects that a standalone client cannot follow.

`OSSCluster` is the Azure CLI default when `--clustering-policy` is omitted, so the policy must be stated explicitly. Staging disables high availability to reduce cost; production availability and sizing are separate capacity/SLA decisions.

```powershell
# ============================================================
# AZURE MANAGED REDIS - STANDALONE / NOCLUSTER
# ============================================================

az redisenterprise create `
    --name $Redis `
    --resource-group $RG `
    --location $Location `
    --sku Balanced_B0 `
    --minimum-tls-version 1.2 `
    --public-network-access Disabled `
    --clustering-policy NoCluster `
    --client-protocol Encrypted `
    --access-keys-authentication Enabled `
    --port 10000 `
    --high-availability Disabled

$RedisId = az redisenterprise show `
    --name $Redis `
    --resource-group $RG `
    --query id `
    --output tsv
```

Verify the database policy immediately after provisioning:

```powershell
az redisenterprise database show `
    --cluster-name $Redis `
    --resource-group $RG `
    --query "{Policy:clusteringPolicy,Protocol:clientProtocol,Port:port}" `
    --output table
```

The expected staging values are:

```text
Policy       Protocol    Port
-----------  ----------  -----
NoCluster    Encrypted   10000
```

If an existing database was created as `OSSCluster`, do not try to solve `MOVED` responses in Laravel for this topology. Azure does not allow an `OSSCluster` database to be changed to `NoCluster` in place; recreate the Redis database/service with the intended policy after confirming that disposable cache/session/queue state can be lost.

Access-key authentication is used by the current Laravel deployment contract. If the application later adopts Microsoft Entra authentication for Azure Managed Redis, treat that as a reviewed runtime/security change.

## 4. Azure Managed Redis private endpoint

```powershell
# ============================================================
# AZURE MANAGED REDIS - PRIVATE ENDPOINT
# ============================================================

$RedisPrivateEndpoint = "$Prefix-$Stage-redis-pe"

az network private-endpoint create `
    --name $RedisPrivateEndpoint `
    --resource-group $RG `
    --location $Location `
    --vnet-name $VNet `
    --subnet private-endpoints `
    --private-connection-resource-id $RedisId `
    --group-ids redisEnterprise `
    --connection-name "$Prefix-$Stage-redis-connection"
```

Associate the private endpoint with the Azure Managed Redis private DNS zone created in [Networking](networking.md):

```powershell
az network private-endpoint dns-zone-group create `
    --resource-group $RG `
    --endpoint-name $RedisPrivateEndpoint `
    --name redis-private-dns `
    --private-dns-zone $RedisPrivateDnsZone `
    --zone-name redis
```

## 5. Redis host, port, secret, and logical database

Use the normal Azure Managed Redis hostname and TLS port `10000`:

```powershell
# ============================================================
# REDIS CONNECTION SETTINGS
# ============================================================

$RedisHost = az redisenterprise show `
    --name $Redis `
    --resource-group $RG `
    --query hostName `
    --output tsv

$RedisPort = 10000
```

Do **not** replace `$RedisHost` with a `privatelink` hostname. Private DNS should resolve the normal hostname to the private endpoint from the VNet, while the normal hostname remains appropriate for TLS hostname validation.

Retrieve the primary access key without printing it, store it in Key Vault, then clear the shell variable:

```powershell
$RedisPassword = az redisenterprise database list-keys `
    --cluster-name $Redis `
    --resource-group $RG `
    --query primaryKey `
    --output tsv

az keyvault secret set `
    --vault-name $KV `
    --name redis-password `
    --value $RedisPassword `
    --output none

$RedisPassword = $null

$RedisPasswordUri = az keyvault secret show `
    --vault-name $KV `
    --name redis-password `
    --query id `
    --output tsv
```

Azure Managed Redis exposes a single logical Redis database: database `0`. Do not use database `1` to separate Laravel cache data. Keep both Laravel Redis connections on database `0`; repository/application prefixes provide logical key separation.

Laravel's hosted Redis configuration uses:

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
REDIS_CACHE_DB=0
```

## 6. Verify the private endpoint without `customDnsConfigs`

`customDnsConfigs[0].ipAddresses[0]` is not a reliable way to discover the address for every private endpoint state and can return an empty value. Query the network interface attached to the private endpoint instead:

```powershell
# ============================================================
# VERIFY PRIVATE ENDPOINT ADDRESS
# ============================================================

$RedisNicId = az network private-endpoint show `
    --name $RedisPrivateEndpoint `
    --resource-group $RG `
    --query "networkInterfaces[0].id" `
    --output tsv

$RedisPrivateIp = az network nic show `
    --ids $RedisNicId `
    --query "ipConfigurations[0].privateIPAddress" `
    --output tsv

Write-Host "Redis private endpoint address allocated: $([string]::IsNullOrWhiteSpace($RedisPrivateIp) -eq $false)"
```

Do not print the actual private address into persistent logs or repository evidence unless the approved operational procedure requires it.

Verify connection state:

```powershell
az network private-endpoint show `
    --name $RedisPrivateEndpoint `
    --resource-group $RG `
    --query "privateLinkServiceConnections[].privateLinkServiceConnectionState" `
    --output table
```

The connection should be approved before the application is expected to reach Redis.

## 7. Verify PostgreSQL provisioning

```powershell
az postgres flexible-server show `
    --resource-group $RG `
    --name $Pg `
    --query "{State:state,Version:version,FQDN:fullyQualifiedDomainName}" `
    --output table
```

Do not treat Cloud Shell connectivity as proof of private application connectivity: the meaningful verification is from a workload inside the Container Apps VNet, performed after the application is deployed.

## 8. Recovery boundary

PostgreSQL is the primary relational system of record. Redis contains operational cache/session/queue/Horizon state and should be treated as a platform dependency, not as a substitute for PostgreSQL durability. Production backup, point-in-time recovery, Redis persistence/export policy, and application-key recovery evidence must be explicitly approved before real production launch.

## Next

Continue with [Container Apps](container-apps.md).
