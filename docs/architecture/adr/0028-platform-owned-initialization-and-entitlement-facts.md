# ADR-0028: Platform owns initialization and entitlement facts

Status: Accepted

## Context

Alliance Lifecycle wrote Platform plan and settings tables directly. Its bootstrap upserts could reset an administrator's plan, retention, queue and integration settings. Membership and Content copied Platform's plan-resolution and entitlement logic. PlanEntitlementService also duplicated Alliance capacity decisions and mixed them with Integration usage checks, leaving several authorities for the same policy.

## Decision

Platform AllianceAdministration owns InitializeAlliancePlatform, accepting a scalar Alliance ID. It acquires the Alliance owner lock and creates missing plan/settings records in one transaction. Existing records remain byte-for-byte unchanged, including administrative attribution and timestamps. CreateAlliance composes this owner action within its existing outer transaction so Platform failure rolls back Alliance, membership, roles and durable events.

PlanEntitlementQuery owns plan resolution and numeric limit interpretation. It preserves the current Standard-plan rule for unassigned Alliances and explicit validation for missing entitlements. Individual limit reads and the fixed four-field projection use the same interpretation; the projection reads one plan and one bounded entitlement set.

Membership and Content retain their own usage and capacity decisions while consuming the Platform query. Platform Integrations owns IntegrationCapacityPolicy for current API-credential and webhook usage. Integration management consumes the query's existing scalar limit projection. AllianceBootstrapProvisioner, PlanEntitlementService and duplicate entitlement lookups are removed, with no compatibility wrappers or alternate persistence paths.

## Alternatives

Keeping a forwarding service would retain an obsolete boundary and invite new mixed ownership. Moving all capacity decisions into Platform would make it responsible for Alliance membership and content semantics. Upserting defaults on every initialization would overwrite administration. An asynchronous bootstrap would permit visible Alliances with incomplete mandatory Platform records.

## Consequences and verification

Plan interpretation has one owner; each capability remains responsible for its own usage and authorization. Initialization serializes with Platform administrative updates through the current Alliance lock and cannot reset the winner. Existing capacity writers retain their current Alliance locks and field feedback. ReadModels may compose Platform-owned projections; foreign business contexts cannot access plan/settings tables directly.

Architecture enforcement rejects foreign context references to Platform plan/settings tables. PostgreSQL regressions cover custom and absent limits across all capacity owners, the fixed query budget, unchanged configured records on retry, partial initialization rollback, complete Alliance-creation rollback and both commit orders against administration on separate connections. Runtime results remain in the delivery ledger.
