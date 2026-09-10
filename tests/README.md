# Tests by owner and area

Start with **what is being tested**, then choose its execution type. Commands and resource rules live in [Testing](../docs/codebase/testing.md); browser conventions live in [Browser testing](../docs/codebase/browser-testing.md).

The structural rationale and shared-support exceptions are recorded in [ADR-0043](../docs/architecture/adr/0043-owner-first-tests-and-disjoint-execution-suites.md).

## Folder convention

```text
tests/
  Contexts/<Context>/<Capability>/<Tier>/
  ReadModels/<Composition>/<Tier>/
  Workflows/<Workflow>/<Tier>/
  Shared/<Area>[/<Concern>]/<Tier>/
  System/Architecture/
  System/Acceptance/Feature/
  System/Acceptance/Browser/
  Support/
  Fixtures/
  TestCase.php
```

`Tier` is Unit, Feature, Integration, Architecture, Frontend or Browser. Create only the types an area needs. For example, `Contexts/Alliance/Recruitment/Feature` and `Contexts/Alliance/Recruitment/Integration/Concurrency` stay together, while the composed management page has its own `ReadModels/RecruitmentManagement/Feature` and `Browser` directories.

Contexts and ReadModels remain different owners. Do not put a composed read surface inside the context merely because their business names are similar. Use Workflows for cross-owner commands, Shared for business-neutral concerns, and System for genuinely repository-wide contracts. [Contexts](Contexts/README.md) and [ReadModels](ReadModels/README.md) provide navigation.

## Separate resource requirements, not ownership

Unit uses pure PHPUnit without Laravel bootstrapping. Feature retains actual HTTP, validation, encryption, authorization and application interactions. Integration retains committed-state, independent-connection and after-commit semantics; concurrency cases belong in the owner's `Integration/Concurrency`. Genuine migration-lifecycle checks belong in owner-local `Integration/Schema`.

Architecture uses pure PHPUnit for source/reflection but the real application for container, route or scheduler registration. Frontend contains PHP source contracts, not browser journeys. Browser specifications live inside each rendered surface's Browser folder; reviewed PNGs live beside that specification under `Browser/__screenshots__/<SpecName>/<Project>/`.

Shared test-harness checks belong in `Shared/Testing/Integration`. Owner-specific helpers and data live in that owner's `Support` and `Fixtures` folders, outside execution-type directories. Visual seeders belong with their rendered surface. Root support is reserved for genuinely cross-owner infrastructure; see [shared support](Support/README.md). A folder move must not make an isolated test inherit an expensive application base, or replace a meaningful integration boundary with a mock.

## Naming and maintenance

PHP class and file names match and end in `Test`; namespaces mirror the complete path below tests. Prefer subject plus observable responsibility, such as `PageSliceTest`, `ScopedCursorCodecTest`, `EvidenceReferenceBindingTest` and `TransferEvidenceWriteBoundaryTest`. Keep useful provider labels and method names stable. Architectural revision suffixes are not required for new tests; retain meaningful protocol/dataset versions.

Group related scenarios, but split mixed classes when their setup or resources differ. Do not combine assertions merely to reduce counts, copy tests between owners, or create overlapping owner suites. The five PHPUnit suites are execution views of these folders, not additional copies of the files.

After adding a new PHP owner/type directory, run `php scripts/sync-test-suites.php` and commit the updated `phpunit.xml`. `php scripts/verify-test-layout.php` rejects unassigned, overlapping or stale suite paths and namespace/file mismatches without loading tests. Existing directories discover new `*Test.php` files recursively.

For moves, reconcile namespaces, relative paths, imports, CLI/CI selections, documentation and browser snapshot paths. Source/hash preservation does not establish runtime discovery, order independence or passing regression results.
