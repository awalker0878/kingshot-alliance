# ADR 0081: Atomic territory notification pages

Status: accepted for PR #165, 2026-09-14. Numbers 0071–0080 remain reserved for the concurrent hardening PR #164.

## Context

Territory activity and Communications delivery intents have separate owners. Advancing a recipient cursor before all intents commit loses notifications; committing intents without the cursor causes repeated work. One bounded page must therefore commit or roll back together.

## Decision

Permit `Workflows/NotificationDelivery/Actions/QueueTerritoryNotifications` to compose owner operations in one outer database transaction. The Operations query locks the next activity page with `SKIP LOCKED`; the publisher composes current recipient authorization and Communications' preference-aware, idempotent intent creation; the Operations action advances the activity cursor. The workflow contains no direct persistence or business locks.

Processing is bounded to 20 pages of at most 100 recipients. Failures roll back the entire page, including delivery intents and cursor progress. Communications retains transport, retries and delivery authority. This exception applies only to this named workflow, alongside the previously reviewed owner-composition exceptions.

## Verification

`TerritoryCollaborationConcurrencyTest` exercises competing PostgreSQL connections, skipping a locked activity, idempotent retries, and an injected late cursor failure that rolls back notification intents and permits a clean retry. Architecture checks continue to reject direct workflow writes and every unlisted transaction.

Recovery-draft mutations independently enter through Territory Planning owner actions. Expiry reads are non-mutating; a scheduled owner command removes a bounded locked page of expired drafts.
