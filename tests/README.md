# Test navigation and ownership

Execution commands, database reset/isolation requirements and verification status live in [Testing](../docs/codebase/testing.md). This page answers where a test belongs and how to name it.

## One execution tier, one owner

Use `tests/<Tier>/Contexts/<Context>/<Capability>` for a capability-owned contract and `tests/<Tier>/ReadModels/<Composition>` for a composed read surface. Use `Workflows/<Workflow>` for cross-owner command orchestration and `Shared/Infrastructure/<Concern>` for business-neutral infrastructure. Tests of the test harness itself belong under `Integration/Shared/Testing`, alongside cache namespace and migration-reference isolation contracts.

Keep Unit, Feature, Integration, Architecture and Frontend as disjoint execution roots. A domain can legitimately have complementary tests in several roots. Do not collapse them into one application-booting base class or create an additional overlapping domain suite just to present one folder. Browser journeys use the same ownership vocabulary within the separate `tests/Browser` root.

The existing `Integration/Concurrency/Contexts` and `Integration/Concurrency/Workflows` trees remain explicit committed-state/independent-connection contracts. Include them when selecting an affected owner's tests. Their resource strategy and scheduling have not been changed by the ownership cleanup.

## Current ownership navigation

| Test concern | Location |
| --- | --- |
| Read-model source/dependency/no-write boundaries | [Architecture/ReadModels](Architecture/ReadModels/README.md) |
| Capability-owned architecture and container contracts | [Architecture/Contexts](Architecture/Contexts/README.md) |
| Browser journeys, rendered surfaces and snapshot ownership | [Browser](../docs/codebase/browser-testing.md) |
| Active Player shell, switching and stale-context UX | `Frontend/Contexts/GameWorld/Players` |
| Alliance Content localization and reaction UX | `Frontend/Contexts/Alliance/Content` |
| Transfer manual/evidence UX | `Frontend/Contexts/GameWorld/KingdomTransfers` |
| Real cross-route throttle-budget isolation | `Feature/Shared/Infrastructure/Security` |
| Opaque scoped cursor encryption and rejection | `Feature/Shared/Infrastructure/Pagination` |
| Plain pagination response shape | `Unit/Shared/Infrastructure/Pagination` |
| Test cache namespace and migration-reference recovery | `Integration/Shared/Testing` |
| Repository-wide architecture, scheduler, namespace and persistence rules | Architecture root |
| Cross-application acceptance matrices | `Feature/Acceptance` and `Browser/Acceptance` |

The frontend PHP contracts inspect source only. They complement rather than replace actual browser, HTTP, authorization or persistence verification. File paths use `Tests\Support\RepositoryPath` instead of depending on how deeply a test is nested.

## Names that explain the contract

Use a class/file name ending in `Test.php`, with a matching namespace. Prefer the subject and observable responsibility: `EventCommandBoundaryTest`, `EvidenceReferenceContractTest`, `EvidenceReferenceBindingTest`, `TransferEvidenceReferenceGuardTest`, `ScopedCursorCodecTest`, `PageSliceTest`, or `CacheNamespaceIsolationTest`.

Use `Boundary` for ownership/security/dependency constraints, `Contract` for an interface or source/API shape, `Binding` for real application registration, and `Http` or `Concurrency` when those execution semantics are the point of the test. Test methods should describe the condition and expected outcome. Keep data-provider labels meaningful and stable.

Do not add an architectural revision suffix to a new test merely because the repository is called Architecture V3. Existing versioned names can be changed in focused owner cleanup, with references reconciled; a version that actually identifies the dataset/protocol under test is meaningful and must not be casually removed. Never rename methods/providers simply to make the count or failure history look different.

## Consolidate folders, not distinct evidence

Group tests by the owner of the behavior, not by the latest development task, transport label or every dependency they read. Do not copy a test into each referenced owner's folder. Keep pure logic, application wiring, HTTP behavior, committed transactions, concurrency and browser journeys separate when their fixtures or resources differ.

Split a mixed class when one concern unnecessarily inherits another's setup. Evidence interface reflection and transfer source assertions use pure PHPUnit; the consolidated general/progression binding class still boots the real application. Transfer guard behavior retains Laravel validation and its original lookup fixture. Pagination likewise separates the plain response object from real encrypted/scoped cursor behavior. Every extracted method retains its assertions; grouping bindings does not combine or delete their distinct scenarios.

For renames or splits, review namespace/autoload alignment, method/provider identities, relative paths, direct CLI/CI selections, scripts and documentation. Browser moves must also relocate existing snapshots according to the configured path template and account for changed file-based IDs/order. Source equivalence is useful review evidence but is not runtime discovery, order-isolation proof or a passing regression run.
