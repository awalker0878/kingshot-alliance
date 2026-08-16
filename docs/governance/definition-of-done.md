# Definition of Done

Status: Current — Architecture V3

A change is done only when applicable product, architecture, quality, security and operational obligations are satisfied.

## Architecture

- the owning bounded context and capability are identified;
- new business behavior is placed under `app/Contexts/<Context>/<Capability>`;
- no context-root technical bucket is introduced;
- User/Player/Platform authority semantics remain correct;
- no cross-context Eloquent navigation or unsupported persistence mutation is introduced;
- cross-context calls use explicit owner Actions/Queries, scalar identifiers, events, Workflows or ReadModels as appropriate;
- true multi-owner commands use a Workflow without transferring persistence ownership;
- cross-context read composition uses a ReadModel;
- Shared remains business-neutral;
- removed architecture/module names are removed from live documentation.

## HTTP and writes

- controllers, middleware and routes remain adapters;
- business writes flow through owning capability Actions;
- HTTP adapters contain no business `DB::transaction`, direct domain persistence or business locking;
- mutable authorization is revalidated inside the owner-controlled write transaction where required;
- authorization services do not acquire locks;
- write Actions/services do not interpret foreign permission vocabularies.

## Data and concurrency

- schema ownership is identifiable from the writing capability;
- database constraints protect critical persistence invariants where appropriate;
- lock ordering, idempotency and concurrency risks are addressed;
- historical identity/attribution is not silently rewritten by current membership/placement changes;
- durable asynchronous intent is persisted transactionally where required.

## Composition

- Workflows contain no business Models, migrations, repositories or owner permission enums;
- ReadModels perform no writes;
- Communications remains generic delivery infrastructure at the business level and does not absorb source-domain reminder semantics.

## Quality

- success, failure, authorization, scope, concurrency and retry behavior are tested as applicable;
- architecture structural tests pass;
- formatting, static analysis, type checks and relevant build/test suites pass;
- architecture certification checks directories, namespaces, imports, relationships, controllers, routes, actions, permissions, transactions, events/listeners, tests, documentation and CI as applicable.

## Security and privacy

- sensitive data, secrets/tokens, destructive actions, retention and external trust boundaries are addressed;
- secret/recovery material is not committed to code/docs/evidence;
- platform authority is not used as a game-domain bypass.

## Operations

- configuration, observability, queues, deployment, migration, rollback and recovery impacts are documented where materially affected;
- new dependencies have health/ownership/recovery expectations.

## Documentation

- update the authoritative current document rather than creating parallel architecture descriptions;
- internal links resolve;
- obsolete live documentation is removed;
- capability/module names match Architecture V3.

## Acceptance

A directory test alone is not sufficient for Architecture V3 certification. Structural gates and behavior tests must both pass on the intended final head.