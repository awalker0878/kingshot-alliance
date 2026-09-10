# KingdomMaps tests

Follow the [owner-first convention](../../../README.md) and [ADR-0043](../../../../docs/architecture/adr/0043-owner-first-tests-and-disjoint-execution-suites.md). [Testing](../../../../docs/codebase/testing.md) owns the execution commands and full-verification requirements.

## Geometry and application boundaries

| Contract | Resource requirements and preserved scenarios |
| --- | --- |
| [KingdomMapPlacementParityTest](Unit/KingdomMapPlacementParityTest.php) | Pure PHPUnit, actual PlacementValidator and TerritoryCoverageGeometry objects, and all eleven golden validation rows. Each row retains fresh validator construction and assertions on violations, warnings and suggestions. No Laravel application or database setup. |
| [KingdomMapGeometryParityV3Test](Feature/KingdomMapGeometryParityV3Test.php) | The unchanged golden analysis method uses Laravel to resolve the actual TerritoryLayoutAnalyzer and its telemetry dependencies. It retains the complete expected analysis result, not a mock or a source-string substitute. |
| [KingdomMapDatasetV2Test](Feature/KingdomMapDatasetV2Test.php) | Existing application-backed release, validation, source-rights and lineage contracts remain unchanged. Their loader/integrity checks are not replaced by the golden placement fixture. |

Both parity classes use [ReadsTerritoryGeometryFixture](Support/ReadsTerritoryGeometryFixture.php). This owner-local trait contains only the extracted fixture decoding and typed dataset construction. Each method reads the same [JSON fixture](Fixtures/territory-geometry.json) afresh; no static cache, lifecycle hook, shared mutable dataset or duplicate fixture copy is introduced. Malformed data still fails. The [JavaScript parity checker](../../../../scripts/check-territory-geometry-parity.mjs) reads that same unchanged corpus.

Commits `fc225841` and `85086b17` extract the shared reader and separate the two original methods. The analysis method and sorting helpers are byte-identical to their originals; the placement method differs only in direct construction of its real dependencies. The fixture-reader body replaces the application path helper with the existing repository-relative helper. Reversing those substitutions and reassembling the extracted methods reproduces the original class. Every original assertion, fixture row, shape check and failure message remains present.

This removes one application bootstrap and eleven container resolutions from the placement method's source path. Those are static operation counts, not measured elapsed savings. Actual registration and application integration still matter: the Feature analyzer retains real dependency resolution. A passing Unit selection alone would not certify that wiring, the release loader, persistence, browser behavior or the whole application.

## Execution and CI selections

The new owner-local Unit directory is explicitly included once in the Unit suite. KingdomMaps Assurance selects `tests/Contexts/GameWorld/KingdomMaps`, covering its PHP tests across Unit and Feature without separately selecting them again. The workflow path filter covers the entire owner, including Support and Fixtures. Its existing release checks, JavaScript parity command, runner settings, action pins and database environment are otherwise unchanged. Placement isolation does not authorize a different database engine for persistence contracts elsewhere.

From a prepared repository root, once test execution is authorized:

```sh
# Pure PHP placement checks using the shared golden corpus.
vendor/bin/phpunit --fail-on-empty-test-suite tests/Contexts/GameWorld/KingdomMaps/Unit

# The owner's PHP placement, real analysis and release contracts.
vendor/bin/phpunit --fail-on-empty-test-suite tests/Contexts/GameWorld/KingdomMaps

# The separate JavaScript side of the shared geometry contract.
npm run check:territory-geometry
```

For changes affecting territory analysis, include the Operations/TerritoryPlanning and ReadModels/TerritoryPlanning tests and their telemetry/dependency boundaries. Full regression remains a separate requirement. No runner, geometry checker, application bootstrap or benchmark was executed during this source-only continuation. Runtime discovery, trait loading, order/isolation and parity verification remain pending; syntax, formatting and source preservation are not passing regression evidence.
