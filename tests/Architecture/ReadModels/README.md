# Read-model architecture contracts

These folders mirror `app/ReadModels/<Owner>`. Each class protects a read composition's dependency, no-write, authorization-order or presentation boundary. The owner is the composition being protected, not every context it happens to read.

| Owner | Contracts in this folder | Complementary behavior |
| --- | --- | --- |
| AllianceAssistant | `AllianceAssistantBoundaryTest` | `tests/Feature/ReadModels/AllianceAssistant` and `tests/Unit/ReadModels/AllianceAssistant` |
| EventManagement | `EventCommandBoundaryTest` | `tests/Feature/ReadModels/EventManagement` |
| IntelligenceSignals | `IntelligenceChangeDetectionArchitectureV3Test` | `tests/Feature/ReadModels/IntelligenceSignals` |
| Progression | `ProgressionPlannerBoundaryTest`, `ProgressionDatasetAbsenceBoundaryV3Test` | `tests/Feature/ReadModels/Progression` |

The complete Architecture suite discovers these folders recursively. Source assertions still read their original application/frontend targets through `Tests\Support\RepositoryPath`; moving the test must never narrow the files checked. HTTP, persistence, application-container and browser verification remain separate, complementary contracts, not interchangeable duplicates.

For a Progression change, an explicit development selection is:

```sh
vendor/bin/phpunit --fail-on-empty-test-suite \
  tests/Architecture/ReadModels/Progression \
  tests/Feature/ReadModels/Progression
```

That selection does not certify the entire application. Broaden for shared dependencies and use the full checks described in [Testing](../../../docs/codebase/testing.md). The ownership moves were source-checked only; runtime discovery and execution remain pending during the no-test hold.
