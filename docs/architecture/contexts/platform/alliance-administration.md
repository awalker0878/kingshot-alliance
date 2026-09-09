# Platform — AllianceAdministration

Status: Current — Architecture V3

Implementation target: `app/Contexts/Platform/AllianceAdministration`

AllianceAdministration owns platform-side administration of Alliance lifecycle status, entitlements, feature controls and usage/accounting concerns.

## Boundary

This capability does not own in-game Alliance membership, R1–R5 leadership, specialist roles or Alliance authorization. Those remain in the Alliance context.

InitializeAlliancePlatform creates missing plan/settings records under the current Alliance owner lock and preserves existing administrative values on retries. Alliance creation composes that scalar-ID action atomically with its own bootstrap. PlanEntitlementQuery is the sole plan-resolution and limit-interpretation contract; Alliance Membership/Content and Platform Integrations retain their own usage/capacity decisions. Its four-limit projection uses two bounded reads. See [ADR-0028](../../adr/0028-platform-owned-initialization-and-entitlement-facts.md).
