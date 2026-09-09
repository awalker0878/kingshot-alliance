# Browser journeys by owner

Keep a journey with the owner of its rendered surface, not every dependency it exercises. This is a navigation structure inside the single recursive Playwright `testDir`, not additional overlapping suites. Cross-application journeys remain explicitly cross-cutting. See [test ownership](../README.md) and [Testing](../../docs/codebase/testing.md).

| Folder | Existing journeys |
| --- | --- |
| `Contexts/Alliance/Access` | Role creation, retained validation and scoped role choices. |
| `Contexts/Alliance/Content` | Rules, notices, reactions and localization. |
| `Contexts/GameWorld/KingdomTransfers` | Transfer planning, evidence, observation history and retry. |
| `ReadModels/AllianceAssistant` | Cited answers, keyboard use and first-use localization. |
| `ReadModels/EventAnalysis` | Bear Hunt debrief and localized readability. |
| `ReadModels/EventManagement` | Event Command readiness and closeout. |
| `ReadModels/GiftCodes` | Catalogue, multi-Governor handoff, session lifecycle and moderation. |
| `ReadModels/Progression` | Factual library, Governor view and Goal Planner. |
| `ReadModels/RecruitmentManagement` | Four complementary candidate, catalogue, input and history specs. |
| `ReadModels/Roster` | Member history navigation and pagination. |
| `ReadModels/ScreenshotIntake` | Evidence intake and review controls. |
| `ReadModels/TerritoryPlanning` | Saved plans and observed-state reconciliation. |
| `Shared` | Application shell, public entry surfaces and Governor activation. |
| `Acceptance` | The cross-capability acceptance matrix. |

From the prepared repository root, use `npm run test:visual` for the full suite. For development only, `npm run test:visual -- tests/Browser/ReadModels/RecruitmentManagement` selects that rendered surface. Include its PHP context/read-model tests when behavior changes; a browser folder alone is not full application verification. Do not add `--pass-with-no-tests` to hide an empty or misspelled selection.

## Snapshot and isolation rules

The existing template remains `{testDir}/__screenshots__/{testFilePath}/{projectName}/{arg}{ext}`. Moving `ApplicationShell.spec.ts` to `Shared/` therefore moves its existing desktop/mobile baseline tree to `__screenshots__/Shared/ApplicationShell.spec.ts/`. The complete 12-PNG tree is reused byte-for-byte. All 17 spec files retain the same Git blobs: test titles, assertions, manual image fingerprints, setup hooks and timeouts are unchanged. No image was regenerated.

Keep screenshot files outside spec directories as configured. Do not change tolerance, retries, projects or worker count to compensate for organization changes. Relative source imports were absent in the moved specs; fixture commands still run from the repository working directory, and diagnostic output paths remain Playwright-managed.

File-based discovery IDs and execution order may change when a spec moves. Reconcile the full desktop/mobile title inventory and repeat isolation-sensitive journeys after the no-test hold is lifted. Source/hash parity is not Playwright discovery, order-independence proof or a passing visual run. Shared database fixtures and ports still require serial execution under the current configuration; these folders do not authorize parallelism.
