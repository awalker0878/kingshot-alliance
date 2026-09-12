# ADR-0047: Bind endpoint health to the settings generation actually sent

Status: Accepted

## Problem

Current-attempt fencing protects a notification attempt, not the configuration of a reusable endpoint. A request can start with configuration A, the owner can replace it with B (or A then B then A), and the old provider response can incorrectly certify or degrade the current endpoint. Pause/resume and edits also reset verification, so a stable endpoint ID or enabled flag is insufficient.

[ADR-0045](0045-fenced-notification-attempts.md) remains authoritative for attempt claiming and results. [ADR-0046](0046-current-notification-source-authorization.md) remains authoritative for current source access. Neither source authorization nor attempt identity is evidence that replacement credentials were tested.

## Decision

Communications owns a monotonic `verification_generation` on the endpoint. Its canonical fresh-schema table initializes the value to one. Each owner-authorized endpoint update or state change advances it under the existing endpoint lock, atomically with the existing health/verification reset and audit event. This includes label-only saves because the current update contract deliberately resets verification. Pause/resume clears verification and advances the generation; returning to an old credential value cannot restore an old generation.

The identical immediate/digest handoff code is consolidated into `NotificationAttemptTransport`. It reads the enabled, recipient/channel-bound endpoint once and captures both its configuration and generation from that row. Provider IO occurs outside the claim transaction. The immutable `AttemptTransportResult` carries the truthful provider outcome and observed generation; it contains no credential or credential hash. Email uses the Accounts-owned verified email query and has no stored endpoint generation.

Completion preserves existing current-attempt fencing and outcome/receipt semantics. `NotificationEndpointHealth::lockForAttempt` additionally requires the generation observed for provider IO. A mismatched, deleted or paused endpoint is not health-mutated by that result. A missing endpoint or unsupported transport supplies no generation, so an outcome cannot accidentally certify credentials never used. The original delivery/dispatch still records its actual outcome when its own attempt remains current.

Only the configuration actually read for IO matters: do not capture a version at queue time or reload it after a response. Editing can happen between claim and transport selection; the selected current configuration is then the actual attempted generation. Source authorization, destination selection, dispatch membership binding and provider-attempt fencing remain separate requirements.

## Alternatives

Comparing decrypted credentials or hashes at completion would spread secrets through reconciliation and miss A-to-B-to-A changes. Comparing encrypted ciphertext can treat unrelated encryption rewrites as credential changes and is not an explicit lifecycle contract. Holding an endpoint lock over external IO would serialize settings changes on provider latency, violate transaction boundaries and still not create an atomic transaction with the provider. A separate distributed verification service or event stream is unnecessary for this owner-local coordination.

One additional integer plus the existing row lock addresses the actual race. The shared transport removes two implementations of the same handoff rule rather than adding parallel adapters. Existing adapters, outcomes, timeout/retry policies, routing and source owners remain in place.

## Security, operations and limits

Provider success means the handed-off attempt succeeded; it does not prove later replacement settings are healthy. Provider failure likewise does not increment replacement credentials' failure count or replace their diagnostics. The next current-generation test/delivery establishes current health. Generation values are not secrets and no credential material is added to receipts or audit logs.

A settings save or pause/resume during an in-flight send cannot recall the external side effect. The endpoint fence controls local attribution, not exactly-once network delivery. Timestamped prior successful/failed delivery history can remain history; current health/verification is reset by its existing owner action. Both delivery and endpoint locks keep their established order. No network request occurs in a transaction.

The application is not deployed. Correct the canonical table creation directly; no backfill, nullable legacy mode, compatibility query or dual implementation is introduced. New installations and test processes must use the corrected schema.

## Verification

Twenty-eight real PostgreSQL cases cover immediate and digest workers, success and failure outcomes, credential replacement, A-to-B-to-A, pause/resume, label-only verification resets, normal current-generation outcomes, edits before transport selection and endpoint deletion/recreation. Sixteen stale-generation cases reproduce failures before the change; the eight controls pass. Provider callbacks assert that the claim transaction is committed before editing through actual owner Actions. Replacement row attributes remain exactly unchanged by the old response; original delivery/dispatch outcomes remain truthful.

The generation is internal owner-managed state, not a mass-assignable input. The canonical table creation is `2026_08_16_000000_create_notification_delivery_tables.php`; no competing historical migration is introduced.

The randomized Communications/NotificationDelivery/System Architecture scope and full PHPStan results are recorded with HARD-100 in the canonical delivery ledger. The item is not Complete merely because this ADR is accepted; the required containing normal milestone still applies. Digest scope and source authorization retain their own regressions; remaining fan-out and repository audit work remain open.
