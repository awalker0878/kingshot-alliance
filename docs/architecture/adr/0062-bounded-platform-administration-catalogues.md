# ADR-0062: Bounded Platform administration catalogues

Status: Accepted

## Context

The Platform dashboard truncated Alliances, legal holds and operational failures while loading all administrator history and unrelated Alliance settings/plan assignments. Operators could not reach older recovery records. A selected Alliance disappeared from the management form when absent from the visible list. Credential counts ignored expiry and queued webhook reservations were missing from pending totals. The public ReadModel had no current actor admission.

## Decision

PlatformAdministration remains read-only composition. Its public dashboard, catalogue page and selected-Alliance queries require an explicit account ID and the current Platform Administrator grant through the existing Accounts query and Administration authorization. HTTP retains verified email, MFA and recent authentication admission. Mutation authority stays in the existing Context Actions.

Alliances, administrator history, active legal holds, outbox failures, webhook failures, notification failures, failed jobs, correlated audit and selected Alliance features have independent 25-row pages and complete SQL totals. A 26-row lookahead determines continuation. Encrypted authenticated cursors bind actor, catalogue kind, selected Alliance for features and correlation for audit. They carry immutable ID upper and after frontiers. Numeric failed-job IDs remain internal while the public row retains its UUID. New rows above the upper frontier wait until the first page is refreshed; deleting a boundary record does not invalidate progress. These are live catalogues: revocation, release and current eligibility are re-evaluated on every request, not frozen by the cursor.

Supporting Alliance plans, settings and active integration counts are fetched only for the returned page or explicitly selected Alliance. IntegrationUsageQuery owns active credential expiry and subscription semantics, shared with Connections, capacity and usage capture. Pending webhook totals include pending, queued and delivering states. The fixed deployment-owned plan catalogue is loaded with one batched entitlement query; it is not an operator-created history catalogue.

Diagnostic projections select only public identifiers, timestamps, counters and fingerprints. PostgreSQL hashes the complete error text and returns the existing 16-character SHA-256 fingerprint, including webhook response fallback, without hydrating exceptions, payloads or message bodies. Feature configuration is not displayed and is not fetched. Fresh-schema indexes support catalogue predicates and ID traversal; no historical upgrade/backfill path is introduced.

The UI retains independent URL cursors and unsaved forms during paging, provides visible failure/retry controls, and cancels stale page requests on actor or catalogue scope changes. Selected Alliance facts remain explicit even off the visible Alliance page. Changing the selected Alliance clears its draft controls; changing actors clears all drafts. An exhausted outbox record reached on an older page uses the same audited owner retry Action and retains its idempotency identity.

## Alternatives and verification

Increasing fixed limits still makes older records unreachable. Returning complete arrays or all supporting rows leaves memory use proportional to history. Offset traversal shifts after deletion and does not bind the viewer or selection. Client-side slicing cannot enforce database bounds or current authority.

PlatformCataloguePagesTest traverses every catalogue beyond two pages, checks SQL bounds and supporting filters, diagnostic privacy/fingerprint parity, all cursor scopes and malformed positions, deleted boundaries/new inserts, revoked authority, current integration counts and authenticated off-page selection. Desktop/mobile journeys cover independent pages, retained drafts, a failed continuation with retry and recovery of older exhausted work. Existing owner mutation and complete production gates remain required; HARD-119 records executed evidence.
