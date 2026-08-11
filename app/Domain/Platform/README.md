# Platform domain

## Purpose

Owns cross-tenant platform administration, Alliance lifecycle controls, plans/entitlements/settings/feature flags, legal holds, retention/account deletion orchestration, usage/operational snapshots, tenant-complete export evidence, and shared transactional-outbox infrastructure.

## Owned code

Runtime code in this module owns platform-administrator grants, platform lifecycle/configuration persistence, outbox infrastructure, retention/usage/export orchestration, and cross-tenant administrative surfaces.

## Public contracts

- dedicated platform-administrator authorization boundary;
- Alliance lifecycle state consumed by tenant/API access;
- plan/entitlement/settings/feature-flag controls consumed by owning feature domains; and
- shared transactional-outbox recording/publishing infrastructure.

## Dependencies

- `Identity` — verified User/MFA/password assurance and account lifecycle.
- `Memberships` / `Authorization` — same-Alliance target validation for ownership transfer without absorbing their persistence.
- `Audit` — attributable platform-administration evidence.
- feature domains — tenant-owned data/counts used by lifecycle/export/usage orchestration.

Support impersonation and payment processing remain intentionally unimplemented.

## Canonical documentation

- [`docs/domains/platform/`](../../../docs/domains/platform/README.md)
- [Background processing](../../../docs/operations/background-processing.md)
