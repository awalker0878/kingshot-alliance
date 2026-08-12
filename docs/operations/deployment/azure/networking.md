# Azure networking

[← Azure deployment blueprint](README.md)

**Document type:** Current provider deployment procedure  
**Status:** Current

This procedure creates the VNet and subnet boundaries used by Azure Container Apps, PostgreSQL Flexible Server, and Azure Managed Redis private endpoints. The address space below is an RFC1918 **example**, not a production disclosure. Change it when it conflicts with enterprise address management.

## 1. Network plan

Example layout:

```text
VNet: 10.20.0.0/16

10.20.0.0/24   Container Apps environment subnet
10.20.1.0/24   PostgreSQL Flexible Server delegated subnet
10.20.2.0/24   Private endpoints
```

For workload-profile Container Apps environments, use a dedicated subnet delegated to `Microsoft.App/environments`. `/27` is the platform minimum, but this blueprint intentionally uses `/24` to leave headroom for revisions and future scaling.

PostgreSQL private VNet integration requires its own subnet delegated to `Microsoft.DBforPostgreSQL/flexibleServers`. Do not place unrelated workloads in that subnet.

Private endpoints use a separate non-delegated subnet.

## 2. Create the VNet

```powershell
# ============================================================
# VNET
# ============================================================

az network vnet create `
    --resource-group $RG `
    --name $VNet `
    --location $Location `
    --address-prefixes 10.20.0.0/16

$VNetId = az network vnet show `
    --resource-group $RG `
    --name $VNet `
    --query id `
    --output tsv
```

## 3. Container Apps subnet

```powershell
# ============================================================
# CONTAINER APPS SUBNET
# ============================================================

$AcaSubnetId = az network vnet subnet create `
    --resource-group $RG `
    --vnet-name $VNet `
    --name aca-subnet `
    --address-prefixes 10.20.0.0/24 `
    --delegations Microsoft.App/environments `
    --query id `
    --output tsv
```

The subnet is dedicated to the Container Apps environment. Do not deploy arbitrary private endpoints or other service resources into it.

## 4. PostgreSQL delegated subnet

```powershell
# ============================================================
# POSTGRESQL SUBNET
# ============================================================

$PgSubnetId = az network vnet subnet create `
    --resource-group $RG `
    --vnet-name $VNet `
    --name postgres-subnet `
    --address-prefixes 10.20.1.0/24 `
    --delegations Microsoft.DBforPostgreSQL/flexibleServers `
    --query id `
    --output tsv
```

## 5. Private endpoint subnet

```powershell
# ============================================================
# PRIVATE ENDPOINT SUBNET
# ============================================================

az network vnet subnet create `
    --resource-group $RG `
    --vnet-name $VNet `
    --name private-endpoints `
    --address-prefixes 10.20.2.0/24
```

If enterprise policy requires private-endpoint network policies, configure them deliberately according to the organization's NSG/UDR design rather than assuming that a private endpoint alone implements all traffic policy.

## 6. PostgreSQL private DNS zone

PostgreSQL Flexible Server private VNet integration requires a private DNS zone whose name ends in `postgres.database.azure.com`. Use a deployment-specific zone name that is not identical to the PostgreSQL server name.

```powershell
# ============================================================
# POSTGRESQL PRIVATE DNS
# ============================================================

$PgDnsZone = "$Prefix-$Stage.postgres.database.azure.com"
$PgDnsLink = "$Prefix-$Stage-pg-dns-link"

az network private-dns zone create `
    --resource-group $RG `
    --name $PgDnsZone `
    --output none

az network private-dns link vnet show `
    --resource-group $RG `
    --zone-name $PgDnsZone `
    --name $PgDnsLink `
    --output none 2>$null

if ($LASTEXITCODE -ne 0) {
    az network private-dns link vnet create `
        --resource-group $RG `
        --zone-name $PgDnsZone `
        --name $PgDnsLink `
        --virtual-network $VNetId `
        --registration-enabled false `
        --output none
}

$PgDnsId = az network private-dns zone show `
    --resource-group $RG `
    --name $PgDnsZone `
    --query id `
    --output tsv
```

The explicit existence check avoids failing a rerun merely because the VNet link already exists.

## 7. Azure Managed Redis private DNS zone

Use the Azure Managed Redis private-link DNS zone:

```powershell
# ============================================================
# AZURE MANAGED REDIS PRIVATE DNS
# ============================================================

$RedisPrivateDnsZone = "privatelink.redis.azure.net"
$RedisDnsLink         = "$Prefix-$Stage-redis-dns-link"

az network private-dns zone create `
    --resource-group $RG `
    --name $RedisPrivateDnsZone `
    --output none

az network private-dns link vnet show `
    --resource-group $RG `
    --zone-name $RedisPrivateDnsZone `
    --name $RedisDnsLink `
    --output none 2>$null

if ($LASTEXITCODE -ne 0) {
    az network private-dns link vnet create `
        --resource-group $RG `
        --zone-name $RedisPrivateDnsZone `
        --name $RedisDnsLink `
        --virtual-network $VNetId `
        --registration-enabled false `
        --output none
}
```

The application must still connect using the normal managed Redis hostname returned by Azure, for example the shape `<cache-name>.<region>.redis.azure.net`. Do **not** configure Laravel with a `privatelink` hostname. The normal hostname should resolve to the private endpoint address from the linked VNet while preserving the expected TLS hostname.

## 8. Public HTTPS and internal HTTP/FastCGI

The intended web flow is:

```text
Browser
   |
   | HTTPS :443
   v
Azure Container Apps ingress
   |
   | HTTP :8080 inside the managed environment
   v
nginx container
   |
   | FastCGI 127.0.0.1:9000
   v
PHP-FPM / Laravel container
```

The PHP image does **not** need to terminate TLS. Azure Container Apps ingress terminates external HTTPS. With `allowInsecure=false`, public HTTP requests are redirected to HTTPS by Container Apps before the request is forwarded into the application path.

`docker/nginx/azure.conf` preserves the platform's `X-Forwarded-For`, `X-Forwarded-Host`, `X-Forwarded-Port`, and `X-Forwarded-Proto` values into FastCGI so Laravel can reconstruct the original request after its trusted-proxy middleware accepts the controlled proxy boundary.

## 9. Optional enterprise controls

The minimal staging blueprint does not silently prescribe an organization's full production network-security posture. Production design may additionally require:

- NSGs and UDRs;
- Azure Firewall or another approved egress-control path;
- NAT Gateway for stable outbound IP where required;
- private DNS forwarding/hub-spoke links;
- private ACR endpoints if the registry must not use public endpoints;
- internal-only Container Apps environments plus an approved front door/application gateway; and
- custom domain/certificate governance.

Those controls must be documented when adopted and must not be inferred from this staging topology.

## 10. Verify network objects

```powershell
az network vnet subnet list `
    --resource-group $RG `
    --vnet-name $VNet `
    --query "[].{Name:name,Prefix:addressPrefix,Delegations:delegations[].serviceName}" `
    --output table
```

Expected conceptual result:

```text
aca-subnet          10.20.0.0/24   Microsoft.App/environments
postgres-subnet     10.20.1.0/24   Microsoft.DBforPostgreSQL/flexibleServers
private-endpoints   10.20.2.0/24   <none>
```

Do not paste actual private endpoint IP addresses into repository documentation or tickets unless the approved operational process explicitly requires them.

## Next

Continue with [Data services](data-services.md).
