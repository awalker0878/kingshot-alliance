# ADR-0043: Owner-first tests with disjoint execution suites

Status: Accepted

## Context

Capability-owned behavior, composed reads, workflows and shared infrastructure already have distinct source owners. A tier-first test tree repeats those owners beneath Unit, Feature, Integration, Architecture, Frontend and Browser, scattering an area's evidence. Grouping by owner makes related contracts easier to find, but must not turn their different resource requirements into a common expensive setup or execute them twice.

The owner-first migration was explicitly selected for PR #163. This record captures that structural decision, not approval of the outstanding test-performance or database-reset changes. Source reconciliation alone cannot establish runner discovery, behavioral coverage, order independence or a measured speedup.

## Decision

Use the owner and area before the execution type:

```text
tests/Contexts/<Context>/<Capability>/<Tier>/
tests/ReadModels/<Composition>/<Tier>/
tests/Workflows/<Workflow>/<Tier>/
tests/Shared/<Area>[/<Concern>]/<Tier>/
tests/System/Architecture/
tests/System/Acceptance/{Feature,Browser}/
```

Only create types an area needs. Keep Contexts, ReadModels and Workflows distinct even when their business names are similar. Put genuine cross-application contracts in System rather than assigning them to an arbitrary capability. Product-specific shared behavior, such as application-shell offline privacy, belongs with its Shared owner rather than among repository-wide architecture rules.

Unit, Feature, Integration, Architecture and Frontend remain five disjoint PHP execution views. Each PHP test is assigned exactly once. Browser is a separate runner, not a sixth PHPUnit suite. Real constraints, committed transactions, independent connections, authorization, encryption and container bindings retain their actual resources. Source/reflection tests and independent logic do not bootstrap the application unnecessarily. Concurrency and intentional schema-lifecycle contracts live in their owner's Integration/Concurrency and Integration/Schema folders; folder placement does not authorize parallel execution.

Place owner-specific fixture data in `<Owner>/Fixtures` and helper code in `<Owner>/Support`, outside execution-type folders. A visual fixture belongs to the rendered surface it prepares; a factual evidence corpus belongs to the capability interpreting it. Reuse an owner's helper through an explicit import when another owner's test needs it; do not duplicate data or move it to global support just because it has another consumer. Keep protocol and corpus version names intact.

Root `tests/Support`, `tests/Fixtures` and `tests/TestCase.php` are reserved for genuinely cross-owner test infrastructure or scenarios. For example, the common scenario builder, repository path resolver and shared reset support may remain global. Empty root fixture folders need not be retained. Helpers and fixtures are not suites and must not be named as discoverable test classes. Colocation does not introduce global seeding, fixture reuse between cases or a new shared application lifecycle.

Keep explicit owner/type directory entries in phpunit.xml. Synchronization derives those entries from source paths and changes only the testsuites block. Freshness and layout guards must reject unassigned files, overlap, invalid ownership/tier placement, duplicate declarations and unexpected empty inventory. New files under an existing configured directory remain recursively discoverable. New owner/type directories require synchronized configuration in the same change. Source-path checks do not load test classes or evaluate data providers.

Preserve full regression, browser, acceptance and security execution paths. Owner selections are development accelerators, not dependency-aware certification of the entire application. Uncertain dependencies and changes to shared infrastructure require broader verification. Do not add overlapping owner suites or silently pass an empty selection.

## Alternatives and consequences

Keeping tier-first folders simplifies runner configuration but makes capability work span several distant trees. Mirroring every implementation subfolder adds navigation depth without necessarily clarifying the behavior being protected. One flat owner suite or duplicate owner/tier copies would obscure costs or execute the same scenarios twice. We instead accept explicit directory synchronization in exchange for owner-local navigation and independent execution views.

Moving files changes fully qualified class names, relative paths, direct command selections and possibly execution order. Browser moves can also change file identifiers and snapshot resolution. Each move must reconcile these references, preserve existing fixture data and reviewed images, and keep method/provider scenarios intact. Split mixed-resource classes only with an explicit scenario mapping; do not collapse distinct assertions to reduce counts.

The decision changes test organization, not production architecture, database engines, security settings, coverage thresholds, retries or worker counts. It makes no performance claim. Migration acceptance still requires target-runtime discovery and identity reconciliation, order/isolation checks, browser snapshot verification and the required complete regression when execution is authorized. The explicit no-test hold does not mark those gates as passing.

## Implementation guidance

[Testing](../../codebase/testing.md) owns exact commands, suite synchronization and current verification status. [Test navigation](../../../tests/README.md) owns placement and naming guidance. [Browser testing](../../codebase/browser-testing.md) owns browser paths and reviewed snapshots. Migration receipts are source-only evidence, not permanent registries or substitutes for runtime results.
