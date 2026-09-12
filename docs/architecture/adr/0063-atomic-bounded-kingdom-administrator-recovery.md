# ADR-0063: Atomic bounded Kingdom administrator recovery

Status: Accepted

## Context

The recovery Workflow admitted a Platform operator before separately committing the GameWorld repair and Operations permission provisioning. Revocation could overtake admission, and late failure left a partial repair. Recovery choices clipped the first 250 Kingdoms and 1,000 Governors. Replacement hydrated every old administrator assignment and missed scheduled grants; adding a prefix to an already valid 500-character reason exceeded the canonical column.

## Decision

RecoverKingdomAdministrator is a named atomic Workflow exception. Its single transaction composes Platform's AuthorizePlatformOperatorWrite, GameWorld's RepairKingdomAdministratorAssignment and Operations' KingdomOperationsRoleProvisioner. The Workflow owns no models, SQL, row locks or permission interpretation. Platform holds its current unrevoked operator grant through the transaction. GameWorld then locks the Kingdom before the target Player, requires an active Kingdom and a current direct identity in that Kingdom, and owns role provisioning, assignments, audit and outbox. Operations reconciles its own permission meanings through the existing GameWorld owner Action. Any later failure rolls back every owner. Platform grants do not become Kingdom or Player authority.

Replacement reads at most 501 non-revoked, unexpired assignments for other Governors, including scheduled assignments, under the Kingdom scope lock. Up to 500 can be revoked atomically in one bounded update. More than 500 rejects the entire replacement before a target grant is created. The operator can recover without replacement, then the recovered Governor uses ordinary GameWorld role removal by assignment ID (including scheduled grants), or the existing 50-Governor bulk removal for effective grants, before retrying replacement. The normal last-administrator protection remains authoritative. The reason is 10–500 Unicode characters at the owner boundary and is stored without an overflowing prefix; the recovery source remains explicit in audit/outbox metadata. An already effective target with no remaining replacements returns its existing assignment without duplicate recovery audit/outbox.

PlatformAdministration provides read-only Kingdom and Governor choices in 25-row pages. Current Platform authorization is checked on every request. Governor choices require a current active parent Kingdom and exclude reconciled aliases. Cursors bind actor, kind, parent and search to an immutable upper ID frontier; only explicit ID/name projections and SQL counts are hydrated. Selected off-page choices are scoped to the current parent independently of search. Fresh-schema indexes support active Kingdom, direct Governor and replacement traversal. There is no historical backfill or compatibility implementation.

The recovery screen loads choices on demand, searches beyond the former clips and retains selected choices and reason drafts across paging or retry. Actor/Kingdom changes clear dependent intent and cancel stale responses. Failed pages and failed recovery submissions remain visible and retryable. Existing verified-account, Platform MFA and recent-password middleware remains required; selectors confer no write authority.

## Verification

RecoveryAtomicityTest uses two committed PostgreSQL connections for both revocation orders, current HTTP reauthorization after admission, late audit/outbox/Operations failure with whole-composition rollback, and duplicate-free retry. KingdomRecoveryBoundsTest covers the 501-row rejection, additive recovery, ordinary scheduled-role removal, bounded replacement, canonical/Kingdom checks and maximum Unicode reasons. KingdomRecoveryChoiceTest traverses 261 Kingdoms and 1,001 Governors, checks scoped cursor rejection, current eligibility, stable boundaries, bounded SQL, off-page selections and HTTP admission. Desktop/mobile browser cases cover search, paging failure/retry, retained drafts, parent reset and a real recovery after a failed submission. HARD-120 records actual executed evidence separately from authored coverage.
