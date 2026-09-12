# ADR-0053: Webhook egress destination security

Status: Accepted

## Problem

The Integrations owner accepted HTTPS URLs and rejected literal private addresses, but a hostname could resolve to a private or reserved address after configuration. The HTTP client could also follow a redirect to a destination that had never passed the owner policy. Rechecking a hostname without binding the connection to the checked answer leaves a DNS rebinding interval.

## Decision

`WebhookEndpointPolicy` remains the single Integrations-owned destination policy. Configuration validates URL structure and literal addresses. Every transport claim resolves the current hostname again, requires a non-empty answer set containing only public IPv4/IPv6 addresses, and returns a typed resolved endpoint. Delivery pins one vetted address to the original HTTPS hostname for TLS/SNI and certificate validation. Redirects are disabled and curl is restricted to HTTPS.

The policy is applied before an attempt is opened or any provider request occurs. A destination that no longer passes becomes a terminal, privacy-safe failure without exposing its address or response. The signed payload and existing public event catalogue remain unchanged.

## Consequences

DNS changes are observed on each new attempt. Mixed public/private answers fail closed. Pinning removes the resolution-to-connection substitution window while normal TLS hostname verification remains active. Redirects are treated as provider failures and never traversed. Operations must diagnose or replace a rejected subscription rather than bypassing the policy.

## Verification

Owner tests cover empty, private, reserved, mixed and IPv6 answers, pinned address formatting, delivery-time rebinding rejection, redirect non-following and absence of provider IO on policy failure. Final acceptance still requires the normal security, static-analysis, schema and containing gates recorded by HARD-109.
