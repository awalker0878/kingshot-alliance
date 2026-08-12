# Provider deployment documentation

[← Shared operations](../README.md)

**Document type:** Current provider deployment index  
**Status:** Current

This section owns provider-specific infrastructure blueprints for hosting Kingshot Alliance. It complements the generic release, rollback, backup/restore, incident-response, configuration, observability, and background-processing guidance already owned by `docs/operations/`.

## Providers

- [Azure](azure/README.md) — complete Azure Container Apps deployment using one immutable application image, multi-container web replicas, private PostgreSQL, private Azure Managed Redis, Key Vault, managed identities, Azure Container Registry, Log Analytics/Application Insights, and GitHub Actions OIDC.

## Ownership boundary

Provider deployment documentation owns:

- provider resource provisioning and naming;
- provider networking, DNS, private endpoints, ingress, TLS termination, and managed identities;
- provider database/cache/registry/secret-manager creation;
- provider-specific application and job deployment definitions;
- CI/CD federation into the provider; and
- provider-specific validation and recovery exercises.

The existing shared operations documents remain authoritative for provider-neutral operational behavior:

- [Runtime configuration](../configuration-reference.md)
- [Background processing](../background-processing.md)
- [Observability](../observability.md)
- [Deployment runbook](../runbooks/deployment.md)
- [Rollback runbook](../runbooks/rollback.md)
- [Backup and restore](../runbooks/backup-restore.md)
- [Incident response](../runbooks/incident-response.md)

## Secret-handling rule

Repository deployment documentation must contain placeholders only. Never commit real subscription/tenant/client IDs, credentials, Laravel `APP_KEY` values, database or Redis passwords, Key Vault secret values, private endpoint addresses, certificates, or sensitive production identifiers. Commands that create or retrieve secrets must keep the value in process memory only long enough to place it in the approved secret manager.

## Related architecture

Provider deployment must remain consistent with accepted architecture decisions. Azure deployments are governed by [ADR 0009 — Azure Container Apps runtime topology](../../adr/0009-azure-container-apps-runtime-topology.md).
