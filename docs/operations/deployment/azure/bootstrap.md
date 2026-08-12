# Azure bootstrap

[← Azure deployment blueprint](README.md)

**Document type:** Current provider deployment procedure  
**Status:** Current

This procedure establishes the Azure subscription context and shared resources used by the remaining Azure deployment steps. Run the commands from Azure CLI in **PowerShell** syntax. All values in angle brackets are placeholders; never commit the values you substitute.

## 1. Configuration variables

```powershell
# ============================================================
# CONFIG
# ============================================================

$SubscriptionId = "<AZURE-SUBSCRIPTION-ID>"
$Location       = "<AZURE-REGION>"
$Prefix         = "<APP-PREFIX>"
$Stage          = "stg"
$Suffix         = Get-Random -Minimum 1000 -Maximum 9999

$RG       = "$Prefix-$Stage-rg"
$VNet     = "$Prefix-$Stage-vnet"
$AcaEnv   = "$Prefix-$Stage-aca"
$Law      = "$Prefix-$Stage-law"
$AppInsights = "$Prefix-$Stage-ai"

# Globally unique or globally scoped names.
$ACR      = ("$Prefix$Stage$Suffix" -replace '-', '').ToLower()
$KV       = "$Prefix-$Stage-kv-$Suffix"
$Pg       = "$Prefix-$Stage-pg-$Suffix"
$Redis    = "$Prefix-$Stage-redis-$Suffix"

$RuntimeIdentity = "$Prefix-$Stage-runtime"
$GithubIdentity  = "$Prefix-$Stage-github"

$Web       = "$Prefix-$Stage-web"
$Horizon   = "$Prefix-$Stage-horizon"
$Scheduler = "$Prefix-$Stage-scheduler"
$Migrate   = "$Prefix-$Stage-migrate"

# Keep hosted staging defaults aligned with deploy/staging.env.example.
$Database = "kingshot_staging"
$PgAdmin  = "kingshot_staging"

# Keep the hosted image repository aligned with CI's APP_IMAGE convention.
$ImageRepository = "kingshot-alliance"
```

Use an Azure region supported by all selected services. Keep staging and production in separate deployment scopes and do not reuse secret values between environments.

## 2. Login and Azure CLI extensions

```powershell
# ============================================================
# LOGIN / EXTENSIONS
# ============================================================

az login
az account set --subscription $SubscriptionId

az extension add --name containerapp --upgrade
az extension add --name redisenterprise --upgrade
az extension add --name application-insights --upgrade
```

Confirm the selected subscription before provisioning:

```powershell
az account show `
    --query "{name:name,id:id,tenantId:tenantId}" `
    --output table
```

The returned identifiers are operational data. Do not copy them into repository documentation.

## 3. Resource providers

```powershell
# ============================================================
# RESOURCE PROVIDERS
# ============================================================

az provider register --namespace Microsoft.App
az provider register --namespace Microsoft.ContainerRegistry
az provider register --namespace Microsoft.DBforPostgreSQL
az provider register --namespace Microsoft.Cache
az provider register --namespace Microsoft.KeyVault
az provider register --namespace Microsoft.Network
az provider register --namespace Microsoft.OperationalInsights
az provider register --namespace Microsoft.Insights
az provider register --namespace Microsoft.ManagedIdentity
```

Provider registration can be checked with:

```powershell
az provider show `
    --namespace Microsoft.App `
    --query registrationState `
    --output tsv
```

Repeat for any provider whose resource creation reports that registration is incomplete.

## 4. Resource group

```powershell
# ============================================================
# RESOURCE GROUP
# ============================================================

az group create `
    --name $RG `
    --location $Location `
    --tags Environment=$Stage Application=$Prefix
```

## 5. Log Analytics and Application Insights

```powershell
# ============================================================
# LOG ANALYTICS
# ============================================================

az monitor log-analytics workspace create `
    --resource-group $RG `
    --workspace-name $Law `
    --location $Location

$LawId = az monitor log-analytics workspace show `
    --resource-group $RG `
    --workspace-name $Law `
    --query customerId `
    --output tsv

$LawKey = az monitor log-analytics workspace get-shared-keys `
    --resource-group $RG `
    --workspace-name $Law `
    --query primarySharedKey `
    --output tsv
```

`$LawKey` is sensitive operational material. Keep it only in the shell process for the Container Apps environment creation and clear it afterward.

```powershell
# ============================================================
# APPLICATION INSIGHTS
# ============================================================

az monitor app-insights component create `
    --app $AppInsights `
    --resource-group $RG `
    --location $Location `
    --workspace $Law
```

The application already emits JSON logs and request/trace correlation. Application Insights/Log Analytics provide the Azure-side collection surface; enabling additional exporters remains a separate application architecture decision.

## 6. Azure Container Registry

The deployment intentionally keeps ACR in classic registry-wide RBAC mode so the runtime and GitHub identities can use the well-understood `AcrPull` / `AcrPush` roles used by this blueprint.

```powershell
# ============================================================
# AZURE CONTAINER REGISTRY
# ============================================================

az acr create `
    --name $ACR `
    --resource-group $RG `
    --location $Location `
    --sku Standard `
    --role-assignment-mode rbac `
    --admin-enabled false

$AcrId = az acr show `
    --name $ACR `
    --resource-group $RG `
    --query id `
    --output tsv

$AcrServer = az acr show `
    --name $ACR `
    --resource-group $RG `
    --query loginServer `
    --output tsv
```

If the organization intentionally adopts ACR's ABAC repository-permission mode later, replace the legacy `AcrPull` / `AcrPush` assignments with the corresponding current repository roles as a separate reviewed security change.

## 7. Runtime managed identity

Use one user-assigned managed identity for the runtime roles so the web app, Horizon, and Container Apps Jobs can share pre-authorized access to ACR and Key Vault without registry passwords or Key Vault credentials.

```powershell
# ============================================================
# RUNTIME MANAGED IDENTITY
# ============================================================

az identity create `
    --name $RuntimeIdentity `
    --resource-group $RG `
    --location $Location

$RuntimeIdentityId = az identity show `
    --name $RuntimeIdentity `
    --resource-group $RG `
    --query id `
    --output tsv

$RuntimePrincipalId = az identity show `
    --name $RuntimeIdentity `
    --resource-group $RG `
    --query principalId `
    --output tsv
```

Grant image-pull access:

```powershell
az role assignment create `
    --assignee-object-id $RuntimePrincipalId `
    --assignee-principal-type ServicePrincipal `
    --role AcrPull `
    --scope $AcrId `
    --output none
```

## 8. Key Vault

```powershell
# ============================================================
# KEY VAULT
# ============================================================

az keyvault create `
    --name $KV `
    --resource-group $RG `
    --location $Location `
    --enable-rbac-authorization true

$KvId = az keyvault show `
    --name $KV `
    --resource-group $RG `
    --query id `
    --output tsv
```

Allow runtime workloads to read secrets:

```powershell
az role assignment create `
    --assignee-object-id $RuntimePrincipalId `
    --assignee-principal-type ServicePrincipal `
    --role "Key Vault Secrets User" `
    --scope $KvId `
    --output none
```

Allow the currently signed-in deployment operator to create/update staging secrets:

```powershell
$MyObjectId = az ad signed-in-user show `
    --query id `
    --output tsv

az role assignment create `
    --assignee-object-id $MyObjectId `
    --assignee-principal-type User `
    --role "Key Vault Secrets Officer" `
    --scope $KvId `
    --output none
```

Use the least-privilege operator role permitted by the organization's Azure governance. RBAC propagation can take a short period; retry secret operations only after confirming the role assignment exists.

## 9. Laravel APP_KEY without PHP in Cloud Shell

Do not require PHP to be installed in the Azure CLI shell. Generate exactly 32 random bytes with .NET cryptography and encode them in Laravel's `base64:` format:

```powershell
# ============================================================
# LARAVEL APP_KEY
# ============================================================

$Bytes = New-Object byte[] 32
[System.Security.Cryptography.RandomNumberGenerator]::Fill($Bytes)
$AppKey = "base64:$([Convert]::ToBase64String($Bytes))"

az keyvault secret set `
    --vault-name $KV `
    --name app-key `
    --value $AppKey `
    --output none

$AppKey = $null
$Bytes = $null
```

Never print or commit the generated key. Treat it as recovery-critical material: encrypted Laravel data and sessions can depend on it.

Retrieve only the Key Vault secret URI for later Container Apps configuration:

```powershell
$AppKeyUri = az keyvault secret show `
    --vault-name $KV `
    --name app-key `
    --query id `
    --output tsv
```

The URI is a secret reference, not the secret value, but it is still environment-specific operational metadata and should not be copied into repository files.

## 10. GitHub deployment identity shell

Create the user-assigned identity now; federation and role assignments are completed in [GitHub Actions](github-actions.md).

```powershell
# ============================================================
# GITHUB DEPLOYMENT IDENTITY
# ============================================================

az identity create `
    --name $GithubIdentity `
    --resource-group $RG `
    --location $Location

$GithubIdentityId = az identity show `
    --name $GithubIdentity `
    --resource-group $RG `
    --query id `
    --output tsv

$GithubClientId = az identity show `
    --name $GithubIdentity `
    --resource-group $RG `
    --query clientId `
    --output tsv

$GithubPrincipalId = az identity show `
    --name $GithubIdentity `
    --resource-group $RG `
    --query principalId `
    --output tsv

$TenantId = az account show `
    --query tenantId `
    --output tsv
```

Do not store these environment identifiers in tracked files. Use protected GitHub environment variables/secrets as described in the CI/CD procedure.

## Next

Continue with [Networking](networking.md). After the Container Apps subnet exists, the Container Apps environment itself is created in [Container Apps](container-apps.md).