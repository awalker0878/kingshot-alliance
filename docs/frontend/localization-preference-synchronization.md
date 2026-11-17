# Browser locale preference synchronization

This change builds on the asynchronous coordinator, strict normalizer and shared locale definitions. Laravel catalogues remain in `lang/`; Vue catalogues remain in `resources/js/localization/messages/`. No message value or account preference schema changes.

## Stored contract and precedence

`kingshot_locale_state` is the primary plain, non-authoritative cookie. It contains one atomic record: `1.<epoch>.<revision>.<selectionId>.<canonicalLocale>`. Epoch and selection IDs are 32 lowercase hexadecimal characters; revisions are nonnegative safe integers. PHP and TypeScript reject malformed versions, aliases, trailing whitespace, unsafe integers and unsupported locales against the same fixtures. Both preference cookies bypass cookie encryption because the browser writes them; neither carries authentication or authorization.

Startup chooses the valid versioned cookie, then the strictly normalized legacy `kingshot_locale` cookie, then the explicit bootstrap/server-rendered locale, browser languages and English. PHP uses the versioned cookie, legacy cookie and existing raw Accept-Language policy. Neither legacy nor versioned localStorage may override a valid startup cookie. The runtime rewrites storage mirrors after successful loading.

`kingshot.locale.preference` carries the encoded record in cross-tab storage notifications. `kingshot.locale` and `kingshot_locale` remain compatibility mirrors. Once a valid versioned cookie exists, old tabs writing only the legacy preference cannot override it. Older tabs should reload to adopt this protocol.

## Ordering and failures

- The live cookie anchors the current epoch. A different epoch supersedes all old-epoch revisions and events. Missing or corrupt metadata creates a fresh epoch; stale localStorage cannot resurrect the old epoch.
- Within an epoch, larger revision wins. Equal revisions compare selection IDs lexicographically, then canonical locale as a deterministic final tie-break. IDs come from UUID randomness and remain stable through retries. This provides deterministic convergence for concurrent selections, not an assertion about wall-clock click order.
- A local selection reserves its revision before imports. It publishes only after the required active and pending page catalogues load and the coordinator's generation checks pass. Immediately before UI commit it rechecks the shared record. An intervening Arabic-to-English selection cannot be missed merely because the cookie language again says English.
- Storage events are notifications, not epoch authority. Same-epoch records can repair interleaved older writes. Repeated or reordered events do not allocate revisions or echo unchanged values. Focus, pageshow and visibility recovery also reconcile the live cookie.
- A remotely published preference can be newer than a tab's displayed language. Failed imports preserve that tab's loaded UI, show the existing retry control, and retry the latest desired selection. Startup import failure mounts an English shell while preserving the shared preference for retry.
- Blocked storage writes leave cookie-based synchronization available on focus. Unconfirmed cookie writes do not publish storage notifications; the selection can remain local to the tab until a different shared preference appears. No storage exception turns a successful catalogue load into a failed UI selection.
- An exhausted safe-integer revision reserves a new epoch and publishes it only after a successful load and an unchanged prior anchor.

This protocol is eventual synchronization among active same-origin tabs. Cookies and localStorage do not provide a transaction or global lock. It does not promise cross-device synchronization or instant convergence if a tab closes before receiving or repairing competing writes. Account-owned persistent preferences remain separate work.

## Verification and rollback

The unit tests exercise concurrent equal-revision choices, opposite event order, duplicate delivery, ABA, epoch reset, corrupt metadata, blocked reads/writes, remote failures and revision exhaustion. PHP uses the same wire fixtures and tests actual middleware precedence. Playwright uses two real same-origin pages and built language chunks, including delayed/failed imports, cookie/server validation and blocked storage/cookie setters. Existing navigation and room-artwork tests remain required; artwork now changes language through the actual selector.

Run `npm run check`, the HTTP locale and account request/mail PHP tests, and `LocalizationPreferences.spec.ts` plus `LocalizationNavigation.spec.ts` in hosted browser CI. Record exact-head CI evidence before merging. A local browser run is unavailable in this workspace; authored browser cases are not counted as passed until hosted execution succeeds.

Rollback the preference PR together with its new PHP cookie parser/encryption exception. The legacy cookie and storage mirrors keep the last confirmed language usable by the previous release. If intentionally resetting metadata while this implementation remains deployed, expire `kingshot_locale_state` and retain or clear the legacy cookie according to the desired fallback. Do not alter translation catalogues, immutable fingerprints or native-review records during rollback.
