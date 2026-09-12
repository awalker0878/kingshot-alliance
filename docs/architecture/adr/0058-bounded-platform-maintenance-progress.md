# ADR-0058: Bounded Platform maintenance progress

Status: Accepted

## Context

Platform retention changed every eligible record in four categories per invocation. Scheduled usage capture bounded the first Alliance query but restarted from the first IDs every time, leaving later Alliances uncaptured.

## Decision

DataGovernance retention clamps its owner and command limit to 1–500 records per category. Each category selects oldest eligible IDs in a short transaction with skip-locked row locks and applies the same eligibility predicate to the selected IDs. Payload redaction remains limited to terminal webhook deliveries older than 30 days; revoked credential, usage snapshot and export metadata cutoffs remain 90, 365 and 365 days. A manual webhook retry and retention serialize through the delivery row: a committed retry is no longer eligible, and an already-redacted delivery cannot be retried without a payload. Committed category progress survives a later category failure. Revoked credentials remain as minimal historical anchors while any external actor link or action receipt references them. Both reference predicates are applied before batch selection and again at deletion, with dedicated reference indexes. Foreign-key locks cause an in-flight reference to be skipped; retention does not delete actor history or weaken restrictive foreign keys.

AllianceAdministration owns one durable scheduled usage cursor, separate from retained snapshots. It obtains bounded Alliance references through Lifecycle's ordered scalar projection. Snapshot insertion and the last visited Alliance ID commit atomically. The next invocation resumes after that ID and wraps at the end. Removing old snapshots or a frontier Alliance does not reset traversal. An overlapping worker skips a locked checkpoint. The owner clamps each invocation to 1–500 Alliances; full per-Alliance usage counts retain their existing owners and SQL predicates.

The fresh Platform migration defines the checkpoint and indexes for actual retention predicates and ordering. There is no upgrade/backfill migration or second scheduler registration.

## Verification

Owner regressions traverse seven-record retention backlogs in successive batches, preserve live credentials and delivery payloads, exercise a locked terminal delivery and both retry/redaction outcomes, visit all five Alliances through a two-record budget, wrap after snapshot retention, roll back a partially captured batch, and skip a concurrent checkpoint holder. ReferencedCredentialRetentionTest additionally verifies both retained reference types, progress past older ineligible credentials, another maintenance category, and an uncommitted foreign-key reference on a second connection. Hosted evidence remains tracked under HARD-114, HARD-115 and HARD-117.

## Interactive capture authority

The HTTP capture adapter enters the AllianceAdministration CaptureAllianceUsage Action. Existing PlatformWriteState and PlatformAuthorization protect the current operator grant, followed by the Lifecycle owner Alliance lock, in the same order as other Platform Alliance commands. Snapshot and operator audit commit together. The trusted scheduled capture primitive returns its scalar snapshot identifier for this owner composition; scheduled cursor behavior is unchanged. InteractiveUsageCaptureTest covers both competing revocation orders, late audit rollback/retry and revocation between HTTP admission and owner mutation. HARD-121 records execution.
