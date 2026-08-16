# Architecture V2 compliance

Status: Current

Architecture compliance is continuously evaluated across nine contracts. These contracts describe the system as it exists now and are enforced by `tests/v2` and repository verification.

## 1. Canonical source shape

Business behavior lives in exactly seven bounded contexts: Accounts, GameWorld, Alliance, Operations, Intelligence, Communications and Platform. Cross-context composition uses Workflows and ReadModels; business-neutral technical concerns use Shared. Alternate compatibility surfaces, alias layers and shim packages are not valid architecture.

## 2. Accounts and GameWorld identity

Accounts owns User identity and account assurance. GameWorld owns Player and Kingdom identity. A User may own multiple Players, but the active Player is the game-domain security principal and must belong to the authenticated User.

## 3. Alliance ownership

Alliance membership, R1-R5 rank, specialist roles, invitations, recruitment and Alliance policy are Player/Alliance scoped. Authority is evaluated from the active Player's current Alliance relationship and is never aggregated across a User's Players.

## 4. Authorization ownership

Permission vocabulary and authorization interpretation live with the owner that applies the rule. Alliance, Operations, Intelligence, GameWorld governance and Platform each own their authorization semantics. Platform Administrator is User-scoped Platform authority only and does not grant game-domain access.

## 5. Operations

Operations owns Event execution and scheduling, participation, polls, rosters, battle plans, results, rallies, King Perks, reminder policy and Operations-specific permission interpretation.

## 6. Intelligence

Intelligence owns observations, ingestion/reconciliation, roster intelligence, contributions, Event analysis, diplomacy, sharing and Intelligence-specific permission interpretation. It consumes identifiers and facts without becoming the owner of GameWorld identity or Operations aggregates.

## 7. Communications and Platform

Communications owns delivery state, channels, preferences, retries and idempotency. Platform owns Platform Administrator access, platform lifecycle controls, Event-type administration and external API/webhook administration. Neither changes ownership of game-domain aggregates.

## 8. Composition and shared infrastructure

Workflows coordinate multi-context commands without taking persistence ownership. ReadModels compose cross-context reads and remain read-only. Shared contains business-neutral contracts and infrastructure and has no dependency on business contexts, Workflows or ReadModels.

## 9. Verification and documentation

The executable verification suite lives under `tests/v2`. Architecture tests enforce source shape, dependency direction, architecture contract validation, mutation safety and the contracts above. Living documentation states current ownership, invariants and supported collaboration; implementation maps and operational procedures are updated with the same change when those contracts move.
