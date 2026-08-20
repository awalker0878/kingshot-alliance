# Capability completeness plan

Status: Current

This plan compares the current product with maintained Kingshot community tools. It treats external projects as discovery evidence, not as authoritative game data. Any copied game rule, cost table, or provider behavior must have a verifiable source and an `observed_at`/version boundary before it becomes product logic.

## Discovery sources

- [Gercekefsane/kingshot-bot](https://github.com/Gercekefsane/kingshot-bot) — alliance member monitoring, transfer planning, Crazy Joe guidance, Bear Hunt timers, calculators, recruitment, and multi-channel notifications.
- [adroiteck/discord-kingshot-bot](https://github.com/adroiteck/discord-kingshot-bot) — event guides, player profiles, rally calls, timers, announcements, and moderation workflows.
- [whiteout-project/Whiteout-Survival-Discord-Bot](https://github.com/whiteout-project/Whiteout-Survival-Discord-Bot) — a related multi-game implementation with player management, scheduled notifications, calculators, queues, and backup operations.
- [justncodes/ks-giftcode](https://github.com/justncodes/ks-giftcode) and the [official Century Games Gift Code Center](https://ks-giftcode.centurygame.com/) — gift-code workflow discovery and the safe official redemption boundary.

## Current coverage and remaining gaps

The application already has substantially deeper governed workflows than the bots in Alliance membership/access, recruitment review, content revisions, Events and participation, rosters/battle plans/rallies, King Perks, results, intelligence provenance, Kingdom transfers, platform administration, webhooks, Gift Codes, and retryable notifications.

A second completeness review of the current implementation found that the remaining work is mostly workflow consistency and operational depth rather than missing bounded contexts. The active items are recorded in the [delivery ledger](capability-delivery-ledger.md).

## Prioritized delivery plan

| Priority       | Capability/UX                    | Outcome                                                                                                          | Guardrail                                                                    |
| -------------- | -------------------------------- | ---------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------- |
| Complete       | Pagination and list completeness | Opaque cursor pagination, stable sorting, URL filters and bounded query budgets for every potentially large list | Cursor scope is bound to actor, Alliance, filters and ordering               |
| Complete       | Shared workflow UX               | Common page headers, filters, empty/loading/failure states, result receipts and permission-aware navigation      | Server remains the authorization authority                                   |
| Complete       | Bulk workflows                   | Previewed, bounded bulk triage and correction with per-item outcomes, audit and failed-item retry                | Each owner context keeps its business semantics                              |
| Complete       | Gift Code trust lifecycle        | Explicit review/dispute/expiry states, provenance and selective Governor retry                                   | No undocumented provider automation                                          |
| Complete       | Announcements                    | Recurrence, test delivery, cancellation and truthful queued/sent/failed/read history with selective retry        | Content owns intent; Communications owns delivery                            |
| Complete       | Integration platform             | Typed public events, secret rotation, broader event catalogue and committed OpenAPI/webhook schemas              | Public schemas remain distinct from internal messages                        |
| Complete       | Bot/API write parity             | Revocable external identity pairing and idempotent Event response/registration writes                            | A client never supplies an arbitrary actor identity                          |
| Complete       | Knowledge trust                  | Stale-content review queue, revisioned corrections and contextual Event links                                    | No unreviewed or invented strategy claims                                    |
| Complete       | Operational diagnostics          | Safe queue/outbox/delivery inspection, correlation search and allowlisted replay                                 | Sensitive payloads are fingerprinted and replay remains idempotent           |
| Evidence-gated | Calculators                      | Troop, Governor Gear, Charm and Hero Gear planning with saved scenarios                                          | No implementation until the dataset gate in the delivery ledger is satisfied |

## Calculator gate

Community calculator pages demonstrate demand, but their visible results do not provide an authoritative, reviewable dataset contract. Calculator implementation starts only after the source, version, reconciliation, checksum, tests, and visible-provenance requirements in the [delivery ledger](capability-delivery-ledger.md) are met.

## Engineering standards for every slice

1. Owner context keeps write semantics; cross-context pages live in `app/ReadModels`.
2. Public write actions accept scalar IDs/value objects and never return Eloquent models.
3. Every scheduled/external effect is idempotent, observable, retry-bounded and covered by behavior tests.
4. Sensitive values use encrypted casts and are excluded from serialization/logging.
5. Every page must be responsive, keyboard usable and localized through an existing/new domain; material journeys must be included in visual regression coverage.
6. `/docs` changes in the same pull request whenever ownership, integration flow, provider policy, or a user journey changes.
7. Full PHP, frontend, architecture, security, visual, container, staging, backup/restore and image-scan checks must pass before merge.
