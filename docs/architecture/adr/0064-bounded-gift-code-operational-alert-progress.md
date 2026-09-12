# ADR-0064: Bounded Gift Code operational alert progress

Status: Accepted

## Context

The operational-alert scheduler repeatedly visited the first source limit and expanded all alerts across every active Platform administrator. Later sources could never progress. Subscription history and recipient fan-out were unbounded. Subscription alerts with equal timestamps also shared an idempotency meaning despite referring to different subscriptions.

## Decision

GiftCodes owns a durable source sweep and one progress row per source. A transaction acquires the canonical sweep row with FOR UPDATE SKIP LOCKED. Overlapping invocations return zero work. Source selection uses a finite upper ID frontier and visits at most the requested 1–500 sources; it wraps at that frontier so new sources cannot indefinitely postpone existing ones. Each visited source gets one turn before the next sweep, even when its recipient history needs more turns.

A source turn loads at most 25 current pending/active subscriptions and 25 currently active Platform administrator identifiers. Subscription and recipient upper frontiers are durable; recipient continuation completes before the subscription continuation advances. A global budget caps notification-intent attempts, including idempotent replays, at 500 per invocation. The source's five scalar health categories plus at most two alerts per subscription bound alert evaluation to 55 meanings. Sources without alerts or recipients still advance, and deleted boundary records do not invalidate scalar progress. New or reactivated facts behind a frontier are revisited on the next cycle.

Every turn rereads current source eligibility and health, current subscriptions and current Platform recipient facts. Health changes during a recipient sweep apply to the remaining recipients immediately and reach earlier recipients on the next cycle. Subscription status changes are likewise reevaluated, rather than freezing an obsolete message payload. The source row is protected during this bounded transaction; provider calls and foreign grant locks do not occur. Platform owns bounded administrator queries; GiftCodes does not interpret grants or mutate them. Communications remains the notification/route persistence owner. Progress and all notification intents commit together, so late failure leaves retryable prior progress with no partial messages. Stable source/code/meaning/recipient idempotency keys deduplicate retry; subscription meanings now include their subscription ID.

These intents are not delivery authority. CurrentNotificationSourceAuthorization still delegates current Platform grants and GiftCodes source eligibility before delivery or inbox exposure. Operational source eligibility now also requires active, ingestion-enabled and non-revoked source state, suppressing queued work after withdrawal. Existing route/attempt limits, current credentials, preferences, fencing and exhausted-delivery recovery remain unchanged.

Fresh canonical schema creates the sweep singleton, source progress and traversal indexes. Source progress cascades with its source; scalar frontier IDs deliberately survive boundary deletion. No historical backfill, compatibility API or unbounded administrator accessor remains. The CLI retains --limit=100 and its existing scheduler cadence. Its recipients count now reports recipient/source visits in this invocation, alerts counts evaluated meanings, and queued counts newly created logical messages; the 500 budget counts all attempted meanings even when already present.

## Verification

OperationalAlertProgressTest uses committed PostgreSQL state for source and recipient histories beyond the requested limits, 61 subscriptions with equal expiry instants, a 500-intent boundary and continuation, empty populations, current revocation/reactivation, finite frontiers with new/deleted records, health changes, late Communications rollback and competing invocations. NotificationSourceEligibilityTest adds withdrawn-source checks to the existing current-grant checks. HARD-122 records executed hosted results separately from these authored cases.
