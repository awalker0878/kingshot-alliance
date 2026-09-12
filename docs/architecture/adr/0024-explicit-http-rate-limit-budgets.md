# ADR-0024: Give HTTP rate limits explicit workload budgets

Status: Accepted

## Context

HARD-049 found numeric throttle declarations without a prefix throughout the route adapters. The locked Laravel middleware keys these limits by authenticated account, or by route domain and client IP for guests. It does not include the route name or configured limit. Unrelated Gift Code, credential, export and webhook requests therefore consumed the same counter, sometimes with different ceilings.

HARD-050 also found two unbounded authenticated password-verification entry points: confirmation and password change. Both validate the current credential under the account lock, but neither had a request limit.

## Decision

Keep Laravel's maintained throttling middleware and configured cache store. Give each numeric declaration a nonempty third-argument prefix identifying an explicit workload. Every route sharing a prefix must have the same limit and window. Account-scoped budgets span browser sessions, IP changes, Alliance selections and resource IDs; guest budgets span resource IDs for the same client IP and route domain.

All limits below are requests per minute. Existing endpoint ceilings remain unchanged. Password proof gains a shared six-attempt limit; internal observations additionally participate in the existing 120-request provider ingress ceiling. Requests reaching middleware consume an attempt even when later validation or authorization fails. The maintained response supplies 429 and Retry-After; expiry allows retries.

| Budget prefix | Scope | Limit | Shared operations |
| --- | --- | ---: | --- |
| account-reset-request | Guest client | 6 | Request a password-reset message |
| account-reset-complete | Guest client | 6 | Submit a reset token and replacement password |
| account-email-verify | Account | 6 | Complete initial or pending-email signed verification |
| account-email-resend | Account | 6 | Resend initial verification |
| account-password-method | Account | 6 | Add or remove a local password sign-in method |
| account-password-proof | Account | 6 | Confirm current password or change it using current-password proof |
| actor-pairing-code | Account | 5 | Issue external actor pairing codes |
| integration-management | Account | 10 | Create API credentials/webhooks, test webhooks and retry deliveries |
| integration-secret-rotation | Account | 5 | Rotate webhook signing secrets |
| contribution-self-report | Account | 20 | Submit contribution self-reports |
| contribution-export | Account | 10 | CSV and spreadsheet contribution exports |
| platform-alliance-export | Account | 5 | Alliance JSON exports across all selected Alliances |
| platform-outbox-retry | Account | 10 | Outbox recovery across message IDs |
| gift-session-create | Account | 20 | Start redemption workspace sessions |
| gift-session-abandon | Account | 10 | Abandon redemption workspace sessions |
| gift-code-submit | Account | 20 | Submit candidate Gift Codes |
| gift-redemption | Account | 30 | Direct redeem/result and workspace state/prepare/result/skip across all codes and items |
| gift-curation | Account | 10 | Bulk moderation, source registration/policy/control/acquisition/push/revocation, curator grants/revocation |
| gift-curation-content | Account | 20 | Source evidence entry and single-code moderation |
| gift-intelligence-rebuild | Account | 5 | Rebuild Gift Code source intelligence |
| gift-source-ingress | Guest client | 120 | YouTube/Facebook/X verification and deliveries, plus internal observations |
| gift-source-internal | Guest client | 60 | Internal observations within the shared ingress ceiling |
| api-actor-claim | Guest client | 10 | Actor-link claims within the existing named v1 API ceiling |

The existing named api limiter remains an aggregate 120 requests per minute per client IP across every v1 endpoint. Existing named login, registration, Google, passkey, MFA, email-change and recruitment budgets retain their owner definitions.

## Alternatives and consequences

Implicit global sharing was rejected because it has no coherent ceiling and can deny unrelated account recovery after ordinary game activity. A separate bucket per route or resource ID was rejected because switching workflow endpoints, formats or IDs would multiply deliberate workload budgets. A new custom limiter store or middleware is unnecessary; this change makes the maintained mechanism's grouping explicit.

The fresh undeployed application needs no compatibility treatment for old ephemeral counter keys. This is request admission protection, not a substitute for current transactional authority, provider signatures, bounded payloads or infrastructure ingress controls.

## Verification

RouteThrottleBoundaryV3Test boots the real routes and checks explicit, consistent numeric budgets. HTTP cases exercise guest reset isolation, provider/source aggregation and its internal sublimit, the v1 aggregate, Gift Code workflow aggregation, and valid signed verification after unrelated workloads. PasswordProofThrottleV3Test exercises mixed credential attempts across IPs/sessions, rejection without password/proof/audit mutation, separate accounts and deterministic expiry. Configured-store cases retain Redis-backed CI; only the simulated-clock expiry case uses a clock-aware array store.
