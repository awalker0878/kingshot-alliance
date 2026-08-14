# KINGDOMS-003 Slice C2 diplomacy contact security review

[← Security documentation](README.md)

**Scope:** `KINGDOMS-003` Slice C2 / `K3-P4`  
**Status:** Candidate — protected validation pending

## Security objective

Slice C2 adds a minimal manager-private diplomacy contact directory without turning contact names, roles, channels, or handles into identity, authentication, membership, authorization, public-directory, or automated diplomacy signals.

## Tenant and object-ID isolation

Every contact mutation:

1. authorizes `kingdoms.manage` against the active platform Alliance;
2. re-loads and row-locks that Alliance;
3. resolves the submitted `TrackedKingdomAlliance` only under that Alliance ID;
4. requires active tracking;
5. verifies captured tracking Kingdom equals the Alliance current Kingdom;
6. locks/revalidates the neutral `KingdomAlliance` reference against captured Kingdom context; and
7. resolves submitted contact IDs only under the same Alliance + tracking row.

A tracking or contact ID from another tenant fails closed. Sharing one neutral `KingdomAlliance` reference never shares contact rows.

## Authorization and password assurance

- the contact workspace requires `kingdoms.manage`;
- create, update, and deactivate require `kingdoms.manage` plus recent password confirmation;
- contact assignment grants no role or permission;
- no contact record creates a platform `User` or `AllianceMembership`; and
- no role-name checks or contact-derived authorization shortcuts are introduced.

## Identity boundary

Contact identity is deliberately weak coordination metadata.

Allowed contact fields are limited to:

- display name;
- game-side role/title;
- approved handle-based channel type;
- handle/identifier;
- active/inactive state;
- last-verified time;
- manager-private notes; and
- actor/lifecycle provenance required for accountability.

There is no `player_id`, membership link, platform-user link, or name/handle uniqueness constraint. Duplicate names and handles are valid and remain separate rows. Name or handle equality never triggers player/reference merge or automatic linkage.

## Private-data boundary

All contact detail is manager-private in Slice C2. Ordinary member payloads do not include:

- contact IDs;
- names/roles;
- channel types;
- handles;
- verification timestamps;
- notes; or
- actor/lifecycle metadata.

The first-party UI explicitly directs managers to store handles only and not phone numbers, home addresses, passwords, recovery material, credentials, or unrelated private secrets.

Audit and outbox metadata contain only bounded contact/tracking/reference identifiers, state, lifecycle timestamps, verification time, and whether a save created a row. Display names, roles, channel labels, handles, and notes are not copied into structured event payloads.

## Lifecycle and history preservation

Normal lifecycle is active → inactive rather than destructive delete.

- active contacts may be edited;
- exact active-contact update retries with identical normalized business fields are no-ops;
- deactivation preserves the row and records actor/time;
- repeated deactivation is idempotent; and
- inactive contacts remain manager-readable history and cannot be silently rewritten.

If coordination resumes, a manager creates a new active contact rather than mutating the preserved inactive record.

## Kingdom drift and archival

If the platform Alliance changes Kingdom or tracking is archived:

- existing contact history remains manager-readable;
- create/update/deactivate fail closed; and
- contact/tracking/reference ownership is never silently retargeted.

## Diplomacy and automation boundary

Contact data has no state-machine authority.

Creating, updating, verifying, or deactivating a contact does not:

- change diplomacy state;
- infer NAP/ally/rival state;
- change review/expiry dates;
- change transfer plans/readiness/completion;
- calculate threat, desirability, combat, or ranking scores;
- recommend diplomacy action; or
- trigger automated messaging/negotiation.

No scheduler, crawler, scraper, OCR process, bot, automated game-data ingestion, or external contact-directory sync is added.

## Integration boundary

Slice C2 emits internal-only durability events:

- `kingdoms.diplomacy_contact_saved`;
- `kingdoms.diplomacy_contact_deactivated`.

Existing Integration rules reject all `kingdoms.*` events from generic external webhook fan-out. No public `/api/v1` Kingdoms/contact route, credential scope, public contact schema, or cross-tenant directory is introduced.

## Persistence boundary

`kingdom_alliance_diplomacy_contacts` is tenant-owned and contains no dormant future-slice fields. Architecture tests explicitly reject player/membership/permission links, phone/address/credential fields, scoring/ranking/recommendation fields, ingestion fields, and webhook contracts.

There is no delete route and no display-name/handle uniqueness constraint.

## Required protected evidence

Before `K3-P4` becomes Validated, the exact runtime SHA must pass:

- PostgreSQL migration and rollback/reapply coverage;
- manager create/update/idempotency/deactivate lifecycle tests;
- cross-tenant tracking/contact-ID tampering tests;
- manager-only visibility and member-payload isolation tests;
- no User/membership/player-identity creation tests;
- no contact-to-diplomacy inference tests;
- recent-password confirmation tests;
- Kingdom drift/archive tests;
- private event-payload safety tests;
- accessibility/source guards;
- Pint and PHPStan/Larastan;
- full ParaTest/PHPUnit suite;
- frontend lint/format/type/build;
- Dependency Review and CodeQL; and
- immutable image/staging/backup-restore/image scan controls.

Whole-increment `KINGDOMS-003` acceptance remains deferred to `K3-P6`.
