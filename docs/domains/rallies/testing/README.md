# Rallies testing and evidence

[← Rallies domain](../README.md)

**Document type:** Living domain testing and evidence profile
**Status:** Current
**Owning domain:** Rallies
**Code owner:** `app/Domain/Rallies`
**Primary validation boundary:** Player formation ownership, Rally Alliance eligibility, group/assignment concurrency and multi-Alliance Event planning
**P5 evidence decision:** Current Rally architecture is protected by Feature and Architecture suites plus repository-wide quality gates

## 1. Critical claims and validation ownership

Tests must prove Player-context isolation, durable Player identity, 100% formations, exact Rally Alliance eligibility, joiner/lead/slot integrity, multi-Alliance Kingdom planning and participation evidence.

## 2. Executable suite mapping

Feature tests exercise Rally actions and HTTP self-response boundaries. Architecture tests enforce schema identity, route shape, catalogue capabilities and Event UI integration. Repository suites cover authorization and tenancy dependencies.

## 3. Architecture and domain-boundary validation

Rallies owns formation/guidance/group/assignment/participation state; Events owns occurrence and Event authorization. Tests prevent participant identity from moving away from `player_id`.

## 4. Authorization, tenancy, security and privacy validation

Tests cover active Player ownership, cross-Player self-response denial, exact operating Alliance eligibility, cross-Alliance assignment rejection and exact Kingdom Event manager authority.

## 5. Feature, interface and integration validation

Feature coverage includes multi-Player saved formations, group capacity, lead/slot rules, assignment moves, confirmation/decline, participation, and multiple Alliance Rally groups within one Kingdom occurrence.

## 6. Idempotency, concurrency and asynchronous validation

Action tests protect serialized occurrence/group mutations and conflict rechecks. No Rally-specific asynchronous executor exists.

## 7. Persistence, migration, rollback and recovery evidence

Migration/static tests protect Player/Alliance foreign keys, active lead/slot indexes. Recovery follows [operations](../operations/README.md).

## 8. Performance, query and capacity evidence

No standalone numeric Rally SLO is accepted. Candidate and group queries must remain occurrence/Alliance bounded and become performance-tested if production scale requires thresholds.

## 9. Accessibility and frontend evidence

Show/Manage controls are keyboard-native form/button elements, use localization keys and retain the shared accessible Event shell.

## 10. Historical accepted evidence

Current acceptance is tied to the current local branch revision and its Rally Feature/Architecture tests; no historical implementation is normative for this domain contract.

## 11. Evidence identity, retention and supersession

Future acceptance records exact commit/workflow identity under the [testing evidence standard](../../../product/testing-evidence-standard.md) and [coverage matrix](../../../product/testing-evidence-coverage-matrix.md).

## 12. Gaps, non-capabilities and related documentation

No automated game execution or public Rally API is claimed. See [security](../security/README.md), [operations](../operations/README.md), [interfaces](../interfaces/README.md), and [Events testing](../../events/testing/README.md).
