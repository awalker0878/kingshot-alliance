# Azure Communication Services Email and SMTP

[← Azure deployment blueprint](README.md)

**Document type:** Current provider deployment procedure  
**Status:** Current

This procedure configures Azure Communication Services (ACS) Email as the SMTP transport for Laravel transactional mail. It uses an Azure Communication Services resource, an Email Communication Services resource with a linked domain, a Microsoft Entra application, an ACS SMTP Username resource, and a client secret stored in Key Vault.

Do not commit the generated sender address, Entra identifiers, SMTP username, client secret, Key Vault secret URI, or other environment-specific identifiers into repository documentation.

## 1. Resource variables

Choose resource names and the ACS data location at deployment time. The Communication Services and Email Communication Services resources must use compatible data locations for domain linking.

```powershell
# ============================================================
# ACS EMAIL VARIABLES
# ============================================================

$EmailDataLocation = "<ACS-DATA-LOCATION>"
$EmailSuffix       = Get-Random -Minimum 1000 -Maximum 9999

$Acs          = "$Prefix-$Stage-comms-$EmailSuffix"
$EmailService = "$Prefix-$Stage-email-$EmailSuffix"
```

The Azure resource location is `Global`; `$EmailDataLocation` controls where ACS stores service data at rest.

## 2. Create Communication Services and Email Communication Services

```powershell
# ============================================================
# COMMUNICATION SERVICES
# ============================================================

az communication create `
    --name $Acs `
    --resource-group $RG `
    --location Global `
    --data-location $EmailDataLocation `
    --output none

az communication email create `
    --name $EmailService `
    --resource-group $RG `
    --location Global `
    --data-location $EmailDataLocation `
    --output none
```

The `az communication email` command group is supplied by the Azure CLI communication extension and may still be marked preview even though the underlying ACS Email service is supported.

## 3. Provision an email domain

For staging, an Azure-managed domain is the simplest baseline because Azure manages its sender-authentication DNS configuration.

```powershell
# ============================================================
# AZURE-MANAGED EMAIL DOMAIN
# ============================================================

az communication email domain create `
    --domain-name AzureManagedDomain `
    --email-service-name $EmailService `
    --resource-group $RG `
    --location Global `
    --domain-management AzureManaged `
    --output none
```

For production, a customer-managed domain may be preferable. A customer-managed domain must complete ownership verification and sender authentication, including the required SPF and DKIM records, before it is used for application mail.

Capture the domain resource ID:

```powershell
$EmailDomainId = az communication email domain show `
    --domain-name AzureManagedDomain `
    --email-service-name $EmailService `
    --resource-group $RG `
    --query id `
    --output tsv
```

Link the email domain to the Communication Services resource:

```powershell
az communication update `
    --name $Acs `
    --resource-group $RG `
    --linked-domains $EmailDomainId `
    --output none
```

Verify the link without copying the resource ID into tracked evidence:

```powershell
az communication show `
    --name $Acs `
    --resource-group $RG `
    --query "{Name:name,DataLocation:dataLocation,LinkedDomainCount:length(linkedDomains)}" `
    --output table
```

## 4. Record the verified sender address

Azure-managed domains provide a generated sender address. Customer-managed domains use an approved sender address from the verified domain.

Set the address only in the deployment shell or deployment secret/configuration system:

```powershell
$MailFromAddress = "<VERIFIED-SENDER-ADDRESS>"
$MailFromName    = "Kingshot Alliance"
```

Do not put the environment's generated Azure-managed sender address into this repository.

## 5. Create the Microsoft Entra application for SMTP

ACS SMTP authentication uses an SMTP username linked to a Microsoft Entra application. The SMTP password is one of that application's client secrets.

```powershell
# ============================================================
# SMTP ENTRA APPLICATION
# ============================================================

$SmtpAppName = "$Prefix-$Stage-smtp"

$SmtpAppId = az ad app create `
    --display-name $SmtpAppName `
    --query appId `
    --output tsv

az ad sp create `
    --id $SmtpAppId `
    --output none

$SmtpSpObjectId = az ad sp show `
    --id $SmtpAppId `
    --query id `
    --output tsv

$TenantId = az account show `
    --query tenantId `
    --output tsv
```

Do not persist `$SmtpAppId`, `$SmtpSpObjectId`, or `$TenantId` in tracked deployment examples.

## 6. Grant the SMTP application access to ACS

Microsoft documents the built-in `Communication and Email Service Owner` role as a supported SMTP bootstrap role. It is broad: it grants access to Communication and Email service operations.

```powershell
$AcsId = az communication show `
    --name $Acs `
    --resource-group $RG `
    --query id `
    --output tsv

az role assignment create `
    --assignee-object-id $SmtpSpObjectId `
    --assignee-principal-type ServicePrincipal `
    --role "Communication and Email Service Owner" `
    --scope $AcsId `
    --output none
```

For production least privilege, prefer a reviewed custom role limited to the Microsoft-documented SMTP permissions rather than retaining the broad built-in owner role indefinitely.

## 7. Create the ACS SMTP username

The Azure resource name and the actual SMTP login username are separate properties and **must not be the same value**.

```powershell
# ============================================================
# ACS SMTP USERNAME
# ============================================================

$SmtpUsernameResource = "$Prefix-$Stage-smtp-user"
$SmtpUsername         = "$Prefix-$Stage-mailer"

az communication smtp-username create `
    --resource-group $RG `
    --comm-service-name $Acs `
    --smtp-username $SmtpUsernameResource `
    --username $SmtpUsername `
    --entra-application-id $SmtpAppId `
    --tenant-id $TenantId `
    --output none
```

Verify the SMTP Username resource:

```powershell
az communication smtp-username show `
    --resource-group $RG `
    --comm-service-name $Acs `
    --smtp-username $SmtpUsernameResource `
    --output table
```

Do not configure Laravel until the SMTP Username resource reports that it is ready to use.

## 8. Create the SMTP password and store it in Key Vault

The ACS SMTP password is an Entra application client secret. Generate the secret, write it directly to Key Vault, then clear the shell variable.

```powershell
# ============================================================
# SMTP CLIENT SECRET -> KEY VAULT
# ============================================================

$SmtpPassword = az ad app credential reset `
    --id $SmtpAppId `
    --append `
    --display-name "$Prefix-$Stage-smtp" `
    --years 2 `
    --query password `
    --output tsv

if ([string]::IsNullOrWhiteSpace($SmtpPassword)) {
    throw "SMTP client secret generation failed."
}

az keyvault secret set `
    --vault-name $KV `
    --name smtp-password `
    --value $SmtpPassword `
    --output none

$SmtpPassword = $null

$SmtpPasswordUri = az keyvault secret show `
    --vault-name $KV `
    --name smtp-password `
    --query id `
    --output tsv
```

Do not print the secret value. Treat client-secret rotation as an application credential rotation: create a new Entra client secret, update the Key Vault secret, roll the dependent Container Apps revisions, verify mail delivery, and then retire the old credential.

## 9. Register the Key Vault secret with the web Container App

```powershell
$RuntimeIdentityId = az identity show `
    --name $RuntimeIdentity `
    --resource-group $RG `
    --query id `
    --output tsv

az containerapp secret set `
    --name $Web `
    --resource-group $RG `
    --secrets "smtp-password=keyvaultref:$SmtpPasswordUri,identityref:$RuntimeIdentityId" `
    --output none
```

Configure the Laravel web `app` container:

```powershell
az containerapp update `
    --name $Web `
    --resource-group $RG `
    --container-name app `
    --set-env-vars `
        "MAIL_MAILER=smtp" `
        "MAIL_SCHEME=smtp" `
        "MAIL_HOST=smtp.azurecomm.net" `
        "MAIL_PORT=587" `
        "MAIL_USERNAME=$SmtpUsername" `
        "MAIL_PASSWORD=secretref:smtp-password" `
        "MAIL_FROM_ADDRESS=$MailFromAddress" `
        "MAIL_FROM_NAME=$MailFromName" `
    --output none
```

ACS requires TLS 1.2 or later. Port `587` is the preferred SMTP submission port and the client must use TLS/STARTTLS. Keep `MAIL_SCHEME=smtp`; Laravel/Symfony Mailer negotiates the secure SMTP transport rather than using an HTTP scheme.

## 10. Configure Horizon for queued mail

Horizon must receive the same SMTP secret and mail configuration because queued Laravel notifications/mail are processed by the worker rather than the web request.

```powershell
az containerapp secret set `
    --name $Horizon `
    --resource-group $RG `
    --secrets "smtp-password=keyvaultref:$SmtpPasswordUri,identityref:$RuntimeIdentityId" `
    --output none

$HorizonContainer = az containerapp show `
    --name $Horizon `
    --resource-group $RG `
    --query "properties.template.containers[0].name" `
    --output tsv

az containerapp update `
    --name $Horizon `
    --resource-group $RG `
    --container-name $HorizonContainer `
    --set-env-vars `
        "MAIL_MAILER=smtp" `
        "MAIL_SCHEME=smtp" `
        "MAIL_HOST=smtp.azurecomm.net" `
        "MAIL_PORT=587" `
        "MAIL_USERNAME=$SmtpUsername" `
        "MAIL_PASSWORD=secretref:smtp-password" `
        "MAIL_FROM_ADDRESS=$MailFromAddress" `
        "MAIL_FROM_NAME=$MailFromName" `
    --output none
```

If a scheduled command sends mail directly, configure the scheduler job with the same Key Vault-backed secret and `MAIL_*` variables. Migration jobs do not need SMTP unless a migration command intentionally sends mail, which is discouraged.

## 11. Verify the effective non-secret configuration

Do not retrieve or print the SMTP secret. Verify only the environment-variable wiring:

```powershell
az containerapp show `
    --name $Web `
    --resource-group $RG `
    --query "properties.template.containers[?name=='app'] | [0].env[?starts_with(name, 'MAIL_')].{Name:name,Value:value,SecretRef:secretRef}" `
    --output table
```

Expected shape:

```text
MAIL_MAILER         smtp
MAIL_SCHEME         smtp
MAIL_HOST           smtp.azurecomm.net
MAIL_PORT           587
MAIL_USERNAME       <environment SMTP username>
MAIL_PASSWORD                              smtp-password
MAIL_FROM_ADDRESS   <verified sender address>
MAIL_FROM_NAME      Kingshot Alliance
```

## 12. Functional verification

A successful forgot-password flow provides an end-to-end application test:

1. submit the forgot-password form for a known test account;
2. verify the HTTP request redirects normally rather than returning `500`;
3. verify the message is delivered from the configured sender;
4. open the reset link and verify the reset page loads;
5. complete a password reset and confirm authentication/session behavior independently of mail delivery.

If the email is delivered but the password update or login returns `500`, inspect application logs before changing SMTP. Redis/session failures are a separate dependency and can occur after successful mail delivery.

For the web application:

```powershell
$Revision = az containerapp show `
    --name $Web `
    --resource-group $RG `
    --query properties.latestRevisionName `
    --output tsv

az containerapp logs show `
    --name $Web `
    --resource-group $RG `
    --revision $Revision `
    --container app `
    --type console `
    --tail 300 `
    --format text
```

## 13. Production notes

- Use a customer-managed verified domain when a branded sender identity is required.
- Complete domain verification, SPF, and DKIM before production cutover.
- Prefer a least-privilege custom Entra role for the SMTP service principal.
- Rotate the Entra client secret before expiration and keep the value only in Key Vault.
- Configure mail settings consistently on every role that can send mail.
- Monitor ACS Email delivery outcomes and application mail failures without logging message bodies or credentials.

## Related documentation

- [Azure application configuration](application-configuration.md)
- [Azure Container Apps](container-apps.md)
- [Azure data services](data-services.md)
- Microsoft Learn: Azure Communication Services SMTP authentication
