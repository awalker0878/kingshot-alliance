# ADR-0046: Reauthorize notification sources independently of destination routing

Status: Accepted

## Problem and alternatives

Queue-time publishers authorize their recipients, but a queued body is not a permanent permission to disclose source information. Immediate and digest workers previously rechecked recipient routing and the destination's optional Governor. An account-scoped destination could therefore bypass ownership of the message's original Governor. Membership suspension, officer demotion or source revocation could occur before delivery or a retry.

Putting Alliance, Event, Gift Code and Kingdom-role models into Communications would make generic transport a competing source authority. A blanket Alliance-membership check would incorrectly suppress account-security notices and public Gift Code information. Persisting permission flags in notification metadata would merely cache the stale grant. A generic extensible policy framework or new bounded context is unnecessary for the finite current producers.

## Decision and ownership

Communications owns `NotificationSource`, an immutable descriptor of the original notification type, recipient account, optional original Governor, source subject and existing bounded metadata. `NotificationMessage::source()` constructs it; it contains no endpoint, provider credentials, inferred role grant or mutable permission cache. `NotificationSourceAuthorization` is the explicit delivery-side port.

`Workflows/NotificationDelivery/CurrentNotificationSourceAuthorization` implements that port and is bound by the existing Workflow provider. This is coordination within a publication command, not a new public ReadModel or a source of domain facts. It first verifies the active account and original claimed, non-merged Governor ownership through existing owner queries. It then delegates source access to the authoritative owner. It never imports foreign Models, writes source data, opens a transaction or interprets foreign permission enums. The single-implementation interface has a concrete dependency-inversion purpose: source owners already depend on the Communications intent contract, so Communications must not depend back on their implementation or on the Workflow.

| Notification type | Current source eligibility |
| --- | --- |
| `account.security` | Active original account and Accounts' account-security subject/event contract. No Governor or Alliance is required. |
| `communications.endpoint_test` | Original Governor ownership and the same enabled, recipient/Governor-owned endpoint subject. |
| `alliance.announcement` | Current membership in the original Alliance and the original published member-visible announcement, using the existing publication query. An explicit test delivery instead requires current Content management permission and an unarchived announcement, including a draft preview. |
| `event.reminder` | Original scheduled occurrence in a published Event, matching enabled rule and the existing per-recipient audience/authorization resolver. A before-poll-close source must still reference an open, unexpired poll on that occurrence. |
| `king_perks.reminder` | Original Kingdom and non-closed plan; the source must still be an eligible appointment or skill. Personal appointment reminders require the current assignee. Manager reminders require the existing Kingdom Operations permission; unconfirmed reminders still require an unconfirmed appointment. |
| `officer.brief` | Current original-Alliance membership-management authority, through the same semantic owner method used by the publisher. |
| `intelligence.change` | Current original-Alliance Intelligence view authority, through the same owner method used by the publisher. |
| `gift_code.expiring`, `gift_code.available`, `gift_code.trust_changed`, `gift_code.reminder` | Existing catalogue source plus the original Governor/account scope. Available/trust summaries must retain ownership of every named Governor in their bounded metadata, rather than borrowing permission from one destination. Ordinary reminders require a current owned Governor. No Alliance membership is imposed on these catalogue notifications. |
| `gift_code.redemption_ready` | Original account workspace subject and a current owned Governor. |
| `gift_code.source_alert` | Existing operational source and current Platform administration grant; this is not a public catalogue notice. |

Source subject identifiers must match their owning aggregate/scope. Unknown notification types, missing required identity metadata, deleted sources and revoked authority deny publication. Adding a production type requires its owner rule and regression coverage; there is no permissive fallback, alias, rollout flag or compatibility path. Source owner queries use indexed identities and bounded lookups; named-Governor metadata is capped at fifty. No unbounded recipient enumeration is added to dispatch.

## Claim, retry and failure behavior

Both immediate and digest claim paths consume the original message descriptor, before route reconstruction, attempt increment and provider work. Source eligibility and destination routing are separate requirements. An account destination cannot replace the original Governor identity. Every retry repeats current source checks. Each digest member is evaluated independently: denied members are cancelled and detached without contaminating the remaining provider payload; an empty digest is cancelled without a send.

A denial is a cancellation with safe source-authorization diagnostics, not a provider failure or a consumed provider attempt. Unexpected infrastructure errors propagate and roll back the claim instead of being swallowed as success or converted into a business denial. Existing due/budget/attempt fencing remains authoritative. Provider IO still occurs after the claim transaction commits; no source locks or transaction are kept open over network latency.

These checks establish dispatch-time eligibility from current owner reads, not a distributed transaction with the external provider. Revocation after a claim/provider handoff cannot recall an in-flight external disclosure. The rule does not retroactively delete already-delivered recipient-owned inbox snapshots or recompute their factual content. Source pages continue enforcing their current read boundaries. Exactly-once external delivery is not claimed; [ADR-0045](0045-fenced-notification-attempts.md) continues to govern attempt fencing and uncertain acknowledgements.

## Verification and consequences

The regression first reproduced nine unauthorized sends across thirteen publisher/worker cases. Real publishers, persisted current membership/Governor facts, immediate and digest workers, and transport fakes verify revocation, reassignment, mixed digests, valid publication and retry reauthorization. Source-specific tests cover every current production type, account security without a Governor, public catalogue scope, operational grants, draft previews, source cancellation and poll state. An infrastructure-failure case verifies rollback and propagation. Existing transport/race tests now use owner-created Event and King Perk sources rather than source-less synthetic notification fixtures; their original transport and fencing assertions remain.

The provider-binding architecture test verifies the actual production port registration. Existing architecture gates still reject cross-context persistence and Workflow permission/transaction authority. No schema, endpoint selection, retry budget, timeout, security setting or database engine changes are required. Containing full verification and the exact code checkpoint are recorded under HARD-099, not inferred from this decision. Credential-generation health attribution remains HARD-100; digest membership/recipient binding and remaining source fan-out budgets are separately tracked findings. The overall program remains open.
