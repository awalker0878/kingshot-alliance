# Deployment documentation

[← Documentation home](../README.md)

**Document type:** Current provider deployment index  
**Status:** Current

This section owns provider-specific infrastructure and hosted deployment blueprints for Kingshot Alliance. It describes how to provision the external platform that runs the reviewed application image; it does not replace the application-owned runtime contracts under `docs/domains/`, the generic release/rollback/recovery runbooks under `docs/operations/`, or durable architecture decisions under `docs/adr/`.

## Provider deployments

- [Azure](azure/README.md) — complete Azure Container Apps deployment using PHP 8.5 / Laravel 13, PostgreSQL 18 Flexible Server, private Azure Managed Redis, Azure Container Registry, Key Vault, managed identity, Log Analytics/Application Insights, and GitHub Actions OIDC.

## Ownership boundary

`docs/deployment/` owns:

- cloud/provider resource topology and provisioning;
- provider networking, private endpoints, DNS, ingress, TLS termination, and managed identities;
- provider data-service creation and connection configuration;
- provider-specific container application/job definitions;
- registry, secret-manager, CI/CD federation, and deployment bootstrap commands; and
- provider-specific deployment validation and recovery exercises.

`docs/operations/` continues to own:

- generic immutable-release rules;
- application health/observability/background-processing operations;
- rollback, backup/restore, and incident-response procedures; and
- operator safety/stop conditions that apply regardless of provider.

When a provider deployment changes a durable runtime architecture decision, update the owning ADR and current architecture navigation in the same change.

## Security rules

Deployment documentation may contain resource-name patterns, example private address ranges, public provider documentation links, and placeholder configuration values. Never commit real credentials, Laravel `APP_KEY` values, database or Redis passwords, real private endpoint IP addresses, private certificates, recovery material, or sensitive production identifiers.

## Related documentation

- [Current architecture and ADR index](../adr/README.md)
- [ADR 0009 — Azure Container Apps runtime topology](../adr/0009-azure-container-apps-runtime-topology.md)
- [Shared operations](../operations/README.md)
- [Generic deployment runbook](../operations/runbooks/deployment.md)
- [Runtime configuration reference](../operations/configuration-reference.md)
- [Production launch approval](../product/production-launch-approval.md)
