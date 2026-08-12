# Azure GitHub Actions deployment

[← Azure deployment blueprint](README.md)

**Document type:** Current provider deployment procedure  
**Status:** Current

This procedure configures GitHub Actions to authenticate to Azure through OpenID Connect (OIDC) using a user-assigned managed identity. It intentionally avoids a long-lived Azure client secret.

## 1. Deployment identity

The user-assigned deployment identity is created in [Bootstrap](bootstrap.md). Rehydrate its identifiers if needed:

```powershell
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

$RgId = az group show `
    --name $RG `
    --query id `
    --output tsv
```

Do not write the returned identifiers into tracked repository files. Configure them as protected GitHub environment variables/secrets according to repository policy.

## 2. Azure RBAC for staging deployment

A simple staging bootstrap can grant the deployment identity `Contributor` on the staging resource group and `AcrPush` on the RBAC-mode registry. This is broader than the long-term least-privilege ideal but does not grant Azure RBAC role-assignment authority.

```powershell
# ============================================================
# GITHUB DEPLOYMENT RBAC
# ============================================================

az role assignment create `
    --assignee-object-id $GithubPrincipalId `
    --assignee-principal-type ServicePrincipal `
    --role Contributor `
    --scope $RgId `
    --output none

az role assignment create `
    --assignee-object-id $GithubPrincipalId `
    --assignee-principal-type ServicePrincipal `
    --role AcrPush `
    --scope $AcrId `
    --output none
```

For a mature production deployment, replace broad `Contributor` access with a custom or narrower role set covering only the Container Apps, jobs, ACR build/deployment, and read operations the workflow actually performs. Pre-create RBAC assignments outside the deployment workflow so the pipeline cannot grant itself new privileges.

## 3. GitHub OIDC federated credential

Use a GitHub **environment** subject so the Azure trust is bound to the protected staging environment rather than every branch in the repository:

```powershell
# ============================================================
# GITHUB OIDC - STAGING
# ============================================================

$GitHubOwner = "<GITHUB-OWNER>"
$GitHubRepo  = "<GITHUB-REPOSITORY>"

az identity federated-credential create `
    --name github-staging `
    --identity-name $GithubIdentity `
    --resource-group $RG `
    --issuer "https://token.actions.githubusercontent.com" `
    --subject "repo:${GitHubOwner}/${GitHubRepo}:environment:staging" `
    --audiences "api://AzureADTokenExchange"
```

For production, use a separate protected GitHub environment and preferably a separate Azure deployment identity with production-scoped permissions:

```text
repo:<GITHUB-OWNER>/<GITHUB-REPOSITORY>:environment:production
```

Do not reuse a staging identity with broad production rights merely for convenience.

## 4. GitHub environment values

Configure the protected `staging` GitHub environment with values equivalent to:

```text
AZURE_CLIENT_ID=<USER-ASSIGNED-IDENTITY-CLIENT-ID>
AZURE_TENANT_ID=<AZURE-TENANT-ID>
AZURE_SUBSCRIPTION_ID=<AZURE-SUBSCRIPTION-ID>
AZURE_RESOURCE_GROUP=<STAGING-RESOURCE-GROUP>
AZURE_ACR_NAME=<ACR-RESOURCE-NAME>
AZURE_ACR_SERVER=<ACR-LOGIN-SERVER>
AZURE_WEB_APP=<WEB-CONTAINER-APP-NAME>
AZURE_HORIZON_APP=<HORIZON-CONTAINER-APP-NAME>
AZURE_SCHEDULER_JOB=<SCHEDULER-JOB-NAME>
AZURE_MIGRATION_JOB=<MIGRATION-JOB-NAME>
```

These values are identifiers, not reusable passwords. Keep them out of tracked workflow literals anyway so environment separation remains explicit. **Do not create `AZURE_CLIENT_SECRET`.**

## 5. Required workflow permission

OIDC login requires the GitHub workflow to request an ID token:

```yaml
permissions:
  contents: read
  id-token: write
```

Use the protected GitHub environment on the deployment job:

```yaml
jobs:
  deploy-staging:
    environment: staging
```

Environment protection rules can require reviewers before Azure credentials become available to the job.

## 6. Azure login

A minimal login step is:

```yaml
- name: Azure login
  uses: azure/login@v2
  with:
    client-id: ${{ secrets.AZURE_CLIENT_ID }}
    tenant-id: ${{ secrets.AZURE_TENANT_ID }}
    subscription-id: ${{ secrets.AZURE_SUBSCRIPTION_ID }}
```

If repository policy stores non-secret identifiers as GitHub environment variables instead, reference `vars.*` consistently. The important property is that authentication uses OIDC federation, not a stored Azure password/client secret.

## 7. Build once with immutable release metadata

The release image must be built from the exact tested Git commit:

```yaml
- name: Build immutable image in ACR
  shell: pwsh
  run: |
    $ReleaseSha = "${{ github.sha }}"
    $AppVersion = "<RELEASE-VERSION-FROM-RELEASE-PROCESS>"

    if ($ReleaseSha -cnotmatch '^[0-9a-f]{40}$') {
      throw "Expected a 40-character lowercase Git SHA."
    }

    az acr build `
      --registry "${{ secrets.AZURE_ACR_NAME }}" `
      --image "app:$ReleaseSha" `
      --build-arg "APP_VERSION=$AppVersion" `
      --build-arg "RELEASE_SHA=$ReleaseSha" `
      .
```

Do not rebuild the source commit independently for production promotion. Record the digest produced by the staging build and promote that exact digest.

## 8. Resolve the digest

```yaml
- name: Resolve immutable image digest
  id: image
  shell: pwsh
  run: |
    $ReleaseSha = "${{ github.sha }}"
    $Digest = az acr repository show `
      --name "${{ secrets.AZURE_ACR_NAME }}" `
      --image "app:$ReleaseSha" `
      --query digest `
      --output tsv

    if ([string]::IsNullOrWhiteSpace($Digest)) {
      throw "Image digest was not resolved."
    }

    "digest=$Digest" >> $env:GITHUB_OUTPUT
```

The deployment image reference is then conceptually:

```text
<ACR-LOGIN-SERVER>/app@sha256:<DIGEST>
```

## 9. Migration gate

Before routing a release as healthy, update the migration job to the same image digest and execute it once. The exact update mechanism may be a checked-in sanitized provider template or Azure CLI job update, but the invariant is fixed:

```text
migration job image == web nginx image == web app image == Horizon image == scheduler image
```

Then:

```powershell
az containerapp job start `
    --name "<MIGRATION-JOB-NAME>" `
    --resource-group "<RESOURCE-GROUP>"
```

Release automation must inspect the execution result and fail closed when migration execution fails.

## 10. Update all runtime roles to the same digest

The multi-container web app must update **both** `nginx` and `app` containers in one revision/template change. Do not issue separate image updates that temporarily create a revision with different Nginx and PHP application releases.

The same digest is then applied to:

```text
web/nginx
web/app
Horizon
scheduler job
migration job
```

For the web app, use an Azure Container Apps YAML/ARM/Bicep template that sets both container image references together. Environment-specific resource IDs and Key Vault URIs must come from protected deployment configuration, not committed live values.

## 11. Health gate

After deployment, require:

```text
GET https://<APP-HOST>/up
GET https://<APP-HOST>/health/ready
```

and verify the active revision is healthy before completing the environment deployment.

Also verify:

- runtime release SHA/version match the reviewed image;
- Horizon uses the same digest;
- the scheduler and migration jobs reference the same digest;
- PostgreSQL and Redis dependencies are reachable privately; and
- public insecure ingress remains disabled.

## 12. Promotion model

Recommended flow:

```text
commit
  -> quality/security/test
  -> build one image
  -> record digest
  -> deploy staging
  -> migrate once
  -> health/recovery validation
  -> approval
  -> promote same digest to production
```

Production changes configuration and infrastructure scope, not application image contents.

## 13. Secret boundary

GitHub Actions must not contain:

- Azure client secrets;
- Laravel `APP_KEY` values;
- database passwords;
- Redis access keys;
- Key Vault secret payloads;
- production certificates/private keys; or
- static registry admin credentials.

Application secrets remain in Key Vault and are resolved by Container Apps through managed identity.

## Related documentation

- [Azure Container Apps](container-apps.md)
- [Validation and recovery](validation-and-recovery.md)
- [Generic deployment runbook](../../runbooks/deployment.md)
- [Branch protection](../../branch-protection.md)
