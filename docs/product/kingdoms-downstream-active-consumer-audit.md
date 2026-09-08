# Kingdoms downstream active-consumer audit

## Objective

Close the downstream enforcement gap left after the Kingdom identity lifecycle and reconciliation delivery: archived Kingdom identities remain available to explicit historical readers, while current operational work must fail closed at the authoritative transaction boundary.

This is a fresh-deployment hardening slice. It does not add compatibility shims, transitional behavior, or a second Kingdom lifecycle model.

## Classification rule

Kingdom consumers are classified by intent rather than by framework layer:

- **Historical readers** use `KingdomReferenceQuery::find()` / `require()` and may render archived identities.
- **Current readers** use `findActive()` / `requireActive()` and do not present an archived Kingdom as an active workspace.
- **Operational writes** acquire an active Kingdom under a database lock so the operation cannot race Kingdom archival.
- **Historical Event and Governance history** deliberately keep historical resolution.

## Transaction-time enforcement

The shared active lock contracts are:

- `KingdomReferenceQuery::lockActive()` for an exclusive active Kingdom lock.
- `KingdomReferenceQuery::lockActiveShared()` for a shared active Kingdom lock that prevents concurrent archival while a dependent write is validated.

The downstream write boundaries enforce the active parent Kingdom for:

- Player identity creation and movement;
- Kingdom Governance mutations and administrator bootstrap;
- Alliance mutations;
- Event creation and mutation for Kingdom-, Alliance-, and Player-scoped targets;
- Transfer mutations and mutable Transfer Evidence targets.

This means a caller cannot bypass the lifecycle rule by invoking an action directly instead of entering through a current UI.

## Current-facing surfaces

Current settings, Governance authority/health, roster/import/intelligence, Kingdom intelligence, Alliance public/recruitment, integration API, bot feeds, and announcement/broadcast management resolve an active Kingdom rather than a historical Kingdom reference.

Explicit historical surfaces remain historical. In particular, Event History and Kingdom Governance History must continue to render records after the Kingdom has been archived.

## Acceptance criteria

- Historical `find()` / `require()` behavior is unchanged.
- Active lock methods reject archived Kingdoms.
- Player identity cannot be created or moved into an archived Kingdom.
- Kingdom Governance and bootstrap cannot mutate an archived Kingdom.
- Alliance writes fail closed when their parent Kingdom is archived.
- Event writes fail closed when the target Kingdom, or the parent/current Kingdom of an Alliance or Player target, is archived.
- Existing Event history can still resolve its historical target after archive.
- Transfer writes fail closed when the operating Kingdom is archived, and mutable Transfer Evidence rejects an archived target Kingdom.
- Current-facing read/API/public surfaces do not expose an archived Kingdom as an active workspace.
- Dedicated V3 regression coverage protects active-vs-historical behavior.
- Repository CI, Architecture V3, Intelligence Verification, Visual Regression, CodeQL, and Dependency Review pass before merge.
