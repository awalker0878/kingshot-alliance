# Kingdoms

[← Domain documentation](README.md)

**Validated implementation:** `KINGDOMS-001` Slice A / `K1-P1` — first-class Kingdom foundation.  
**Current candidate:** Slice B / `K1-P2` — [Kingdoms roster](kingdoms-roster.md)  
**Approved increment:** [Kingdoms roster intelligence](../product/kingdoms-roster-intelligence-increment.md)  
**Implementation sequence:** [KINGDOMS-001 implementation plan](../product/kingdoms-roster-intelligence-implementation-plan.md)

This guide documents the validated Kingdom reference and alliance-association contract and points to the separate Slice B roster candidate. Later snapshots, intelligence and CSV capabilities remain unimplemented.

## Ownership

`Kingdoms` owns Kingshot game-world reference concepts. The validated Slice A runtime owns:

- the global `Kingdom` entity;
- canonical kingdom-number normalization/resolution for new runtime input;
- Kingdom lifecycle state; and
- the business action that changes an alliance's Kingdom association.

Slice B adds a candidate roster boundary under the same domain: neutral `KingdomPlayer` identity plus alliance-owned roster observations. Its detailed contract is maintained in [Kingdoms roster](kingdoms-roster.md) so the global reference foundation is not conflated with tenant-owned roster behavior.

`Alliances` continues to own the alliance aggregate. An Alliance stores the foreign-key relationship to a Kingdom, but Kingdom identity and Kingdom-specific business rules belong to `Kingdoms`.

`Content` does not own Kingdom mutation. The public-profile workflow can change presentation/profile fields, but Kingdom association is changed through the dedicated Kingdom settings workflow.

## Kingdom identity

A Kingdom is global reference data, not alliance-owned data.

Current fields are:

- ULID primary key;
- canonical positive integer kingdom number;
- lifecycle status (`active` or `archived`); and
- timestamps.

The canonical kingdom number is unique. Multiple alliances may reference the same Kingdom record.

Sharing a Kingdom record does **not** create any cross-alliance authorization or data-sharing relationship. The active-alliance tenant boundary remains authoritative for alliance-owned behavior.

## Alliance relationship

The alliance schema stores nullable `kingdom_id` referencing `kingdoms.id`. The legacy free-form `alliances.kingdom` string is removed by the Slice A migration after backfill.

There is intentionally no runtime compatibility accessor that pretends the old string column still exists. Code that needs the Kingdom uses the explicit relationship.

User-facing/API representations may still expose a field named `kingdom` when that is part of an existing presentation contract. Such values are derived from `Alliance::kingdom()->number`; they are not a second persistence model.

## Alliance creation

Alliance creation accepts an optional canonical numeric kingdom number.

When supplied:

1. the Kingdoms resolver validates the number;
2. an existing active Kingdom with that number is reused, or a new active Kingdom is created;
3. the alliance is created with `kingdom_id`; and
4. the normal alliance-creation membership, roles, platform defaults, audit and outbox behavior remains transactional.

Archived Kingdoms cannot be newly selected.

## Changing an alliance Kingdom

Authorized alliance administrators use **Alliance → Kingdom settings**.

The read surface requires:

- authenticated session;
- verified email;
- active alliance context; and
- `alliance.manage` for the active alliance.

The mutation additionally requires recent password confirmation.

The mutation:

1. locks the active Alliance row in a transaction;
2. resolves the requested active Kingdom or nullable association;
3. does nothing when the relationship is unchanged;
4. updates `kingdom_id` when it changed;
5. records `alliance.kingdom_updated` in the audit log with previous/new Kingdom identifiers and numbers; and
6. writes a matching alliance-scoped transactional-outbox event.

Kingdom association remains an alliance-setting mutation protected by `alliance.manage`. Slice B's `kingdoms.manage` permission protects roster management; it does not replace `alliance.manage` for changing the Alliance→Kingdom association.

## Legacy migration/backfill

The migration creates first-class Kingdom records, backfills Alliance references, and then removes the old string column.

Legacy values are normalizable when they represent a positive numeric Kingdom using accepted forms such as:

- `1234`;
- `K1234`;
- `K #1234`;
- `Kingdom 1234`; or
- `Kingdom #1234`.

Whitespace and leading zeroes are normalized. Blank/null values remain unassociated.

The migration is fail-closed for malformed or unsupported non-empty values: it raises an error rather than silently discarding the value or preserving an indefinite compatibility column. Operators must correct such source data and rerun the migration.

The rollback path recreates the legacy string representation from current Kingdom numbers for development/test rollback support.

## Presentation and API compatibility

Current surfaces that historically showed `kingdom` continue to show the canonical number derived from the relation, including:

- alliance member overview;
- public alliance page;
- public recruitment page; and
- `GET /api/v1/alliance`.

The `/api/v1/alliance` response retains the existing `kingdom` field so Slice A does not introduce an unnecessary API breaking change. This is representation compatibility only; persistence uses the first-class relationship.

Slice B does not add a public roster API or webhook contract.

## Tenant and security boundary

A Kingdom record and a `KingdomPlayer` reference are global neutral data. Neither may be used as a replacement tenant key.

Alliance-scoped authorization continues to use the active Alliance context. Knowing or sharing a Kingdom ID, Kingdom number, or neutral player ID does not authorize access to another alliance.

Slice B keeps roster notes, application-membership links, role/state and manual observations on alliance-owned roster entries. See [Kingdoms roster](kingdoms-roster.md) and the [Slice B security review](../security/kingdoms-roster-security-review.md).

## Current deferrals

Slice B still does **not** implement:

- player power/level snapshots or historical observation records;
- roster intelligence/trends;
- CSV roster import/export;
- transfer planning;
- diplomacy/NAP intelligence; or
- automated game-data ingestion.

Follow the implementation plan before adding any of these capabilities.

## Troubleshooting

### A migration rejects a legacy Kingdom value

Inspect the affected Alliance's old Kingdom value. Correct it to a supported numeric Kingdom representation or explicitly clear it when the association is genuinely unknown, then rerun the migration. Do not weaken the migration to silently discard malformed values.

### A Kingdom cannot be selected

Confirm the submitted value is a positive numeric Kingdom number and the corresponding Kingdom is not archived.

### A user cannot open Kingdom settings

Confirm they have an active membership in the active Alliance and the effective permission union includes `alliance.manage`. Mutation also requires recent password confirmation.

### A user cannot manage the roster

Confirm the active membership grants `kingdoms.manage`. The built-in Owner, Leader and Officer roles receive it; other built-in roles do not. Mutations also require recent password confirmation.

### Two alliances share a Kingdom or neutral player

That is expected. They share only neutral reference identity; alliance-owned roster state, notes, links and observations remain isolated by Alliance.
