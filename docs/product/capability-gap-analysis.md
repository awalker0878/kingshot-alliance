# Capability completeness plan

Status: Current

This plan compares the current product with maintained Kingshot community tools. It treats external projects as discovery evidence, not as authoritative game data. Any copied game rule, cost table, or provider behavior must have a verifiable source and an `observed_at`/version boundary before it becomes product logic.

## Discovery sources

- [Gercekefsane/kingshot-bot](https://github.com/Gercekefsane/kingshot-bot) — alliance member monitoring, transfer planning, Crazy Joe guidance, Bear Hunt timers, calculators, recruitment, and multi-channel notifications.
- [adroiteck/discord-kingshot-bot](https://github.com/adroiteck/discord-kingshot-bot) — event guides, player profiles, rally calls, timers, announcements, and moderation workflows.
- [whiteout-project/Whiteout-Survival-Discord-Bot](https://github.com/whiteout-project/Whiteout-Survival-Discord-Bot) — a related multi-game implementation with player management, scheduled notifications, calculators, queues, and backup operations.
- [justncodes/ks-giftcode](https://github.com/justncodes/ks-giftcode) and the [official Century Games Gift Code Center](https://ks-giftcode.centurygame.com/) — gift-code workflow discovery and the safe official redemption boundary.

## Current coverage

The application already has substantially deeper governed workflows than the bots in Alliance membership/access, recruitment review, content revisions, Events and participation, rosters/battle plans/rallies, King Perks, results, intelligence provenance, Kingdom transfers, platform administration, webhooks, Gift Codes, and retryable notifications.

The remaining gap is less about adding disconnected screens and more about making the existing capabilities discoverable, actionable, and complete at the workflow edges.

## Prioritized delivery plan

| Priority | Capability/UX | Outcome | Guardrail |
| --- | --- | --- | --- |
| Complete | Gift Codes | Shared catalogue, official handoff, per-Governor ledger, retries and audit trail | No undocumented provider automation |
| Complete | Notification delivery | In-app inbox plus encrypted Discord/Telegram endpoints, preferences and bounded retries | Provider hosts constrained; credentials never returned |
| Complete | Command overview | One decision surface for unread alerts, Event actions, upcoming Events, Gift Codes and recruitment follow-up | Compose owner read models; do not duplicate business state |
| Complete | Alliance broadcasts | Scheduled announcements delivered once to each active Governor's enabled in-app, Discord and Telegram channels | Active membership, recipient-owned endpoints and idempotent fan-out |
| Complete | Knowledge hub | Searchable, versioned Alliance guides with visible source, game-version and review metadata | Provenance, locale, review date and no invented strategy claims |
| Next | Player progression | Manual/CSV observations for furnace, power and identity changes with history and freshness | Intelligence observations, never authoritative game state |
| Next | Recruitment discovery | Public recruitment board, filters, share links and conversion analytics | Privacy controls and explicit application consent |
| Later | Calculators | Troop, Chief Gear, Charm and Hero Gear planning with saved scenarios | Ship only after cost datasets are verified and versioned; the inspected community calculator contains placeholder sections |
| Later | Bot/API surfaces | Read-only Discord/Telegram commands backed by the same application services | Scoped credentials, rate limits and no parallel business logic |
| Later | Mobile/offline polish | PWA install, cached guides/calendar and push delivery | No offline writes without conflict semantics |

## Engineering standards for every slice

1. Owner context keeps write semantics; cross-context pages live in `app/ReadModels`.
2. Public write actions accept scalar IDs/value objects and never return Eloquent models.
3. Every scheduled/external effect is idempotent, observable, retry-bounded and covered by behavior tests.
4. Sensitive values use encrypted casts and are excluded from serialization/logging.
5. New pages must be responsive, keyboard usable, localized through an existing/new domain, and included in visual regression coverage when layout risk is material.
6. `/docs` changes in the same pull request whenever ownership, integration flow, provider policy, or a user journey changes.
7. Full PHP, frontend, architecture, security, visual, container, staging, backup/restore and image-scan checks must pass before merge.
