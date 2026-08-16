# Product terminology

Status: Current

## User

Authenticated application account. A User may own multiple Players. User is not the game-domain authority holder.

## Player

Durable KingShot persona and game-domain principal. Game authority is evaluated for the active Player.

## Active Player

The Player currently selected for the request/session. Switching active Player changes effective game-domain authority.

## Alliance

Managed Alliance tenant/community. Alliance authority is carried by Player membership, rank and specialist roles.

## Membership

Player-to-Alliance relationship that carries membership lifecycle, R1–R5 rank and role assignments as defined by Alliance.

## Kingdom

Game-world Kingdom identity and governance scope.

## Event

Operationally scheduled game activity owned by the Operations context. An Event may have recurrence, occurrences, phases and enabled operational capabilities.

## Observation

Captured intelligence fact about the game world. It is not the same as the neutral GameWorld identity record.

## ReadModel

Read-only projection that combines data from multiple owners for a screen/report.

## Workflow

Explicit orchestration of one user intent across several context owners; it does not own their aggregates.

## Platform Administrator

User-scoped cross-tenant application administrator. It is not Alliance/Kingdom/Event authority.