# Read-model tests

Use `tests/ReadModels/<Composition>/<Tier>`. A composed read surface owns its Feature behavior, Architecture boundaries, Frontend source contracts and Browser journeys where they exist. Its underlying business rules remain in the corresponding Context tests.

| Composition | Existing coverage to inspect together |
| --- | --- |
| AllianceAssistant | Unit, Feature, Architecture, Browser |
| EventManagement | Feature, Architecture, Browser |
| IntelligenceSignals | Feature, Architecture |
| Progression | Feature, Architecture, Browser |
| RecruitmentManagement | Feature, Browser |
| Roster | Feature, Browser |
| TerritoryPlanning | Feature, Browser |

Source-only boundary classes remain pure PHPUnit. Actual HTTP, authorization, persistence and container checks still use the real application; placing them together does not make their setup interchangeable.

`vendor/bin/phpunit --fail-on-empty-test-suite tests/ReadModels/Progression` selects that composition's PHP tests. `npm run test:visual -- tests/ReadModels/Progression/Browser` selects its browser journeys separately. Neither command proves the whole application passes; include affected Context/Workflow dependencies and required full verification.

See [test navigation](../README.md), [Testing](../../docs/codebase/testing.md) and [Browser testing](../../docs/codebase/browser-testing.md).
