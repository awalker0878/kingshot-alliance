# Platform domain

The Platform domain owns cross-tenant operations and runtime composition. Phase 6 expands that ownership to platform-administrator grants, alliance lifecycle controls, plan entitlements, tenant configuration and feature flags, usage snapshots, legal holds, alliance export metadata, account-deletion processing, retention enforcement, queue visibility, and platform operations UI.

Platform administrators are deliberately separate from alliance roles and `PermissionKey`. Web access requires a verified email address, MFA, an active `platform_administrators` grant, and recent password confirmation. A bootstrap administrator can be granted from the console with `platform:admin:grant`; that grant does not bypass MFA.

Alliance deletion is logical and reversible until the retention deadline. Legal holds prevent deletion. Account deletion anonymizes identity after a cooling-off period and preserves pseudonymized records needed for audit/history. Plan entitlements are modeled without coupling Phase 6 to payment processing.

Support impersonation is not implemented in Phase 6 because the implementation plan makes it conditional on explicit approval and no approval is recorded.
