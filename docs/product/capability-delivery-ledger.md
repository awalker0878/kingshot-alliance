# Capability delivery ledger

Status: Current as of 2026-08-20

This ledger closes the capability-completeness scan and its follow-on product and engineering improvement program. It records shipped outcomes, the remaining evidence gate, and the implementation standard. GitHub remains the source of truth for exact diffs and CI results.

## Merged delivery

| PR | Slice | User outcome |
| --- | --- | --- |
| [#79](https://github.com/awalker0878/kingshot-alliance/pull/79) | Post-Pint stabilization | Restored a green baseline without a PHPStan baseline or compatibility shims. |
| [#80](https://github.com/awalker0878/kingshot-alliance/pull/80) | Gift Codes | Shared, sourced code catalogue with official redemption handoff and per-Governor status. |
| [#81](https://github.com/awalker0878/kingshot-alliance/pull/81) | Notifications | In-app, Discord, and Telegram delivery with encrypted endpoints and bounded retries. |
| [#82](https://github.com/awalker0878/kingshot-alliance/pull/82) | Command overview | One responsive decision surface for alerts, Events, Gift Codes, and recruitment follow-up. |
| [#83](https://github.com/awalker0878/kingshot-alliance/pull/83) | Alliance broadcasts | Scheduled, idempotent announcements to active members' enabled channels. |
| [#84](https://github.com/awalker0878/kingshot-alliance/pull/84) | Knowledge provenance | Searchable versioned guides with source, game-version, locale, and review metadata. |
| [#85](https://github.com/awalker0878/kingshot-alliance/pull/85) | Player progression | Freshness-aware, source-labelled observation history and consecutive change detection. |
| [#86](https://github.com/awalker0878/kingshot-alliance/pull/86) | Recruitment discovery | Opt-in public discovery, shareable filters, visible attribution, and private conversion analytics. |
| [#87](https://github.com/awalker0878/kingshot-alliance/pull/87) | Bot/API reads | Revocable least-privilege command, Gift Code, and knowledge reads with bounded responses. |
| [#88](https://github.com/awalker0878/kingshot-alliance/pull/88) | Mobile/PWA | Install, update, and offline UX while private application responses remain network-only. |

Every merged slice passed PHP tests, Pint, PHPStan, frontend lint/format/type/build, architecture verification, CodeQL, dependency review, intelligence contracts, visual regression, production image build, ephemeral staging, backup/restore, and image scanning.

## Improvement-program delivery

| PR | Slice | Durable outcome |
| --- | --- | --- |
| [#90](https://github.com/awalker0878/kingshot-alliance/pull/90) | Baseline and cleanup | Established the authoritative inventory, documentation-link gate, and cleanup rule. |
| [#91](https://github.com/awalker0878/kingshot-alliance/pull/91) | Architecture enforcement | Removed V2 visual compatibility structure and made the current visual contract enforceable. |
| [#92](https://github.com/awalker0878/kingshot-alliance/pull/92) | UX system | Standardized accessible busy, validation, outcome, and confirmation behavior. |
| [#93](https://github.com/awalker0878/kingshot-alliance/pull/93) | Public webhook contracts | Replaced dead selectors with emitted Alliance-scoped lifecycle contracts. |
| [#94](https://github.com/awalker0878/kingshot-alliance/pull/94) | Webhook delivery recovery | Added signed test delivery, audited replay, bounded retry, and delivery inspection. |
| [#95](https://github.com/awalker0878/kingshot-alliance/pull/95) | Gift Code recovery | Completed official-provider handoff, terminal outcomes, backoff, and safe retry behavior. |
| [#96](https://github.com/awalker0878/kingshot-alliance/pull/96) | Operational budgets | Made reviewed production JavaScript and stylesheet ceilings release gates. |
| [#97](https://github.com/awalker0878/kingshot-alliance/pull/97) | Accessibility and localization | Replaced browser prompts with the shared accessible modal contract and an AST-based enforcement gate. |

The temporary phase report was retired at closeout; Git history remains its archive. Current product outcomes live in the [capability catalogue](capability-catalogue.md), user flow in [primary journeys](experience/user-journeys.md), and operational release evidence in the [release checklist](../operations/release/checklist.md).

Closeout requires a clean PostgreSQL installation through every migration, the complete PHP and frontend checks, architecture verification, dependency review, CodeQL, unchanged or deliberately reviewed visual baselines, production-image construction and scanning, ephemeral staging, and a demonstrated backup/restore cycle. Calculators remain outside the release scope until the evidence gate below opens.

## Remaining evidence gate: calculators

The scan found community troop, Governor Gear, Charm, Hero Gear, shard, pet, research, Event, and formation calculators. These are useful discovery evidence, but no inspected source supplied an official or reproducibly versioned Kingshot dataset suitable for product logic.

Calculator work may start only when all of these are true:

1. Every row has a source URI, source label, `observed_at`, game-version boundary, and unit.
2. Values come from one official inspectable table or are reconciled across two independent inspectable sources plus recorded in-game evidence.
3. Disagreements, regional differences, unlock conditions, and unknown values are explicit; unknown never means zero.
4. Each released dataset is immutable, schema-versioned, checksummed, and retained when superseded.
5. Calculation code consumes the dataset through a typed domain contract; Vue components contain no cost tables or formulas.
6. Golden fixtures cover single-step, range, promotion, bonus, rounding, and unavailable-data boundaries.
7. The UI displays dataset version, source, observation date, assumptions, and a report-correction path.
8. Saved scenarios reference their dataset version so later data corrections cannot silently rewrite historical plans.

## Concise implementation plan after the gate opens

1. Land the dataset schema, validator, provenance record, and one reviewed dataset without a user-facing calculator.
2. Implement pure range aggregation and inventory-gap services with golden fixtures.
3. Ship one calculator end-to-end, beginning with the best-supported dataset rather than the largest feature.
4. Add saved scenarios, compare/export UX, keyboard/mobile verification, and visible source/version metadata.
5. Run the complete repository gate set before merge; expand to another calculator only after the first dataset and correction workflow are operating cleanly.

Until the evidence gate opens, calculator pages, guessed formulas, placeholder values, and copied opaque tables are intentionally out of scope.
