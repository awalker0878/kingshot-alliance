# ADR-0053: Webhook egress destination security

Status: Accepted

## Problem and alternatives

Literal-address validation alone allowed hostname rebinding; following redirects or an environment proxy could bypass the destination check. PHP's generic private/reserved filters also accepted mapped IPv6 destinations. Merely resolving before a normal HTTP request leaves the client free to resolve again. An external proxy allowlist would introduce another operational authority without a current requirement.

## Decision

Integrations owns `WebhookEndpointPolicy` and `WebhookTransport`. Configuration validates canonical HTTPS URL syntax. A delivery first claims a bounded attempt, commits, resolves all current A/AAAA answers, and requires a non-empty bounded set of ordinary public unicast addresses. Explicit IPv4 special-use exclusions and a conservative IPv6 global-unicast allow policy reject mapped, translated, tunnel, documentation and private addresses. Canonical request and pin use the same hostname and port.

After DNS, the owner rechecks the exact attempt and current subscription scope, activation, revocation, URL and signing secret in a short transaction. Network IO holds no database transaction. The CurlHandler connects freshly to one vetted address, preserves hostname TLS/SNI verification, disables proxies and redirects, and restricts transport to HTTPS. Missing curl fails closed. The exact JSON bytes signed are the bytes sent.

Connection and total transfer limits are three and ten seconds. Provider response headers/progress abort responses exceeding 64 KiB; decompression is disabled. No response body or raw exception is persisted. Destination rejection terminalizes the attempt with a safe diagnostic.

## Consequences and verification

Every attempt observes current DNS. Mixed answers fail closed. A revocation committed before the final handoff check prevents transport; a request already handed to a remote provider cannot be recalled. Consumers must deduplicate the stable delivery identity. Operations must correct rejected subscriptions through the authorized owner.

Owner tests cover URL ambiguity, special-use IPv4/IPv6, mixed answers, canonical pins, exact signed bytes, proxy/redirect/TLS options, response limits, revocation during DNS and no transaction during network work. Local policy/options tests pass. The real loopback TLS fixture verifies the production CurlHandler with a test CA: pinned hostname/port, exact body/signature bytes, environment-proxy exclusion, redirect non-following, declared and streamed response limits, and hostname mismatch rejection all pass (12 assertions). Production URL policy is tested separately and never accepts loopback. HARD-109 retains hosted containing verification requirements. This replaces the earlier resolve-inside-claim and generic client behavior.
