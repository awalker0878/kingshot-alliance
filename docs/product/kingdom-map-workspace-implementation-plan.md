\# Kingshot Kingdom Map — Execute Complete Implementation



You are the principal engineer, product engineer, frontend architect, test owner,

security reviewer, and delivery owner for this repository.



This is an EXECUTION task.



Do not produce another implementation plan and stop.

Do not stop after an audit.

Do not stop after scaffolding.

Do not stop after one work package.

Do not stop after the first successful test.

Do not ask me whether you should continue between phases.



Continue through implementation, integration, testing, visual verification,

cleanup, documentation, and final verification until all work that can be

completed from the repository and available inputs is complete.



If something is genuinely blocked by a missing external input, record that

specific blocker and continue every other independent workstream.



===============================================================================

1\. SOURCE OF TRUTH

===============================================================================



First read this entire file from beginning to end:



docs/product/kingdom-map-workspace-implementation-plan.md



Treat that document as the authoritative PRODUCT SCOPE and DELIVERY PLAN.



However, its Git SHAs, PR states, test results, historical branch states, and

"implemented/missing" classifications are historical observations.



DO NOT blindly trust those historical repository-state claims.



The live repository is authoritative for current implementation state.



Before modifying anything:



1\. Inspect the current working tree.

2\. Determine the current branch.

3\. Determine HEAD.

4\. Fetch current Git information if permitted.

5\. Compare the current branch with main.

6\. Inspect recent relevant commits.

7\. Inspect open/local work that may already implement plan items.

8\. Check whether any files named in the plan moved or changed.

9\. Reconcile the implementation plan against CURRENT CODE.

10\. Never overwrite unrelated user work.



If the working tree contains pre-existing changes:



\- identify them,

\- determine whether they belong to this task,

\- preserve them,

\- do not discard/reset/stash/delete them without explicit necessity,

\- never use destructive Git operations against user work.



Do NOT use old divergent/retired implementations as the new base simply because

the plan references them.



===============================================================================

2\. PRIMARY PRODUCT OUTCOME

===============================================================================



Build the Kingdom Map as one recognizable Kingshot workspace supporting:



1\. Kingdom Explorer

2\. Alliance Hive Builder

3\. Multi-Alliance Kingdom Planning

4\. Plan versus Observed comparison



The finished experience must support exploration, hive design, kingdom

coordination, comparison against observed positions, editing, persistence,

analysis, collaboration, import/export, visual rendition, accessibility,

localization, security, performance, and release verification.



Authentic authorized Kingshot artwork should be used where supplied and approved.



Do not fabricate missing factual game data, geometry, artwork provenance,

mechanics, source evidence, or observed data.



Missing external inputs must remain explicitly unavailable/blocked rather than

being invented to make tests pass.



===============================================================================

3\. PRESERVE THE EXISTING ARCHITECTURE

===============================================================================



Preserve the existing Laravel / Vue / Inertia application.



Do not perform a framework rewrite.



Preserve established ownership boundaries.



The conceptual ownership model is:



GameWorld / KingdomMaps

\- immutable map releases

\- coordinate interpretation

\- factual world geometry

\- sourced restrictions

\- provenance

\- confidence

\- map layer availability



Operations / TerritoryPlanning

\- editable planning intent

\- participants

\- plan objects

\- slots

\- groups

\- annotations

\- templates

\- analysis preferences

\- review

\- publication

\- sharing

\- persistence



Intelligence / Evidence

\- private source artifacts

\- evidence processing

\- evidence review lifecycle



Intelligence / Observations

\- accepted observed positions

\- identity uncertainty

\- capture coverage

\- freshness

\- invalidation



ReadModels / TerritoryPlanning

\- authorized composition of map data, plans, participants, observations,

&#x20; analysis, and display projections



Existing Operations event owners

\- event objectives and behavior

\- immutable references to published territory revisions



Communications / Delivery

\- notification preferences

\- routing

\- attempts

\- retry behavior



Frontend presentation registry

\- artwork mapping

\- loading

\- anchors

\- display representations

\- export imagery

\- NO game placement authority



Presentation must not become the source of factual game rules.



Do not move owner logic into controllers or Vue components simply because it is

convenient.



Preserve current architectural ADR boundaries unless current repository evidence

demonstrates that the architecture has legitimately evolved.



===============================================================================

4\. REQUIRED CONTRACTS

===============================================================================



Establish or complete explicit contracts corresponding to:



C1 — Map Layer Availability



Include:

\- release ID

\- release checksum

\- layer key

\- materialization state

\- available count

\- expected count

\- coverage extent

\- artifact hashes

\- confidence

\- unavailable reason



C2 — Scene Projection



Include:

\- stable entity reference

\- factual/planned/observed/annotation kind

\- geometry reference

\- asset key

\- layer

\- authorized display data

\- selection state

\- validation state



C3 — Layout Command



Include:

\- expected revision

\- typed complete layout

\- validated variant references

\- validated slot references

\- bounded annotations/preferences

\- mutation ID



C4 — Mutation Result



Include:

\- accepted revision

\- normalized layout or checksum

\- validation results

\- mutation receipt

\- structured conflict errors

\- structured authority errors

\- structured retry/temporary errors



C5 — Analysis Result



Include:

\- input checksums

\- algorithm version

\- assumptions

\- component results

\- uncertainty

\- unavailable metrics



C6 — Visual Rendition



Include:

\- plan revision/checksum

\- map pin

\- asset-pack ID/checksum

\- selected layers

\- export specification

\- locale/font references

\- output checksum



Map schema V2 remains the sole runtime map contract.



Do not accidentally reintroduce the retired V1 map schema.



Plan-document schema versions are a separate namespace from map schema versions.



===============================================================================

5\. FIRST FILES TO INSPECT TOGETHER

===============================================================================



Inspect at minimum:



resources/js/pages/Kingdom/Territory/Editor.vue



resources/js/features/territory-planner/components/TerritoryCanvas.vue



resources/js/features/territory-planner/engine/export.ts



resources/js/features/territory-planner/engine/types.ts



app/Contexts/Operations/TerritoryPlanning/Actions/SaveTerritoryPlan.php



app/Contexts/Operations/TerritoryPlanning/Actions/PublishTerritoryPlan.php



app/Contexts/GameWorld/KingdomMaps/Services/KingdomMapArtifactLoader.php



tests/ReadModels/TerritoryPlanning/Browser/TerritoryPlanner.spec.ts



scripts/check-territory-export.mjs



Also inspect all directly related tests, services, read models, migrations,

workflows, ADRs, localization modules, frontend architecture documentation,

performance budgets, and KingdomMaps workflow files before changing public

contracts.



===============================================================================

6\. EXECUTION ORDER

===============================================================================



Execute the implementation-plan work packages in dependency order.



The required package sequence is:



KM-00 — Establish executable baseline



KM-01 — Establish strict workspace contracts



KM-02 — Materialize complete reference layers



KM-03 — Deliver approved artwork and registry



KM-04 — Build shared renderer and coordinate engine



KM-05 — Complete Kingdom Explorer



KM-06 — Complete editing operations



KM-07 — Complete saving, recovery, conflicts, and publication



KM-08 — Complete hive templates and Governor slots



KM-09 — Complete analysis and suggestions



KM-10 — Integrate observed reality into the shared workspace



KM-11 — Add asynchronous collaboration and review



KM-12 — Complete event, notification, audit, and roster integrations



KM-13 — Complete interchange



KM-14 — Complete artwork-bearing visual exports



KM-15 — Complete private sharing



KM-16 — Complete accessible, localized interaction



KM-17 — Prove security and performance budgets



KM-18 — Release verification and documentation closeout



Do not mechanically redo something that current code has already completed.



For every KM package:



1\. Reconcile plan requirement against current code.

2\. Determine implemented / partial / missing / blocked.

3\. Add or repair tests first where a reproducible defect exists.

4\. Implement the smallest coherent architectural change.

5\. Run focused verification.

6\. Review the diff.

7\. Update delivery evidence.

8\. Commit a coherent unit of work when appropriate.

9\. Continue to the next dependency.



===============================================================================

7\. KM-00 BASELINE AND DELIVERY LEDGER

===============================================================================



Create or reconcile these authoritative documents if not already present:



docs/product/kingdom-map-workspace-implementation-plan.md



docs/product/kingdom-map-workspace-asset-catalogue.md



docs/product/kingdom-map-workspace-acceptance.md



docs/product/kingdom-map-workspace-delivery-ledger.md



Do not replace the supplied implementation plan with a shorter generic plan.



The delivery ledger must use stable KM task IDs and meaningful states such as:



planned

in\_progress

blocked\_input

implemented\_unverified

verified



"Verified" means actual executed evidence exists.



A merged file or green narrow test alone is not enough.



For every completed item record:



\- task

\- implementation files

\- relevant commit

\- tests/checks actually run

\- actual result

\- known limitations

\- blockers

\- evidence



Keep historical failures visible until they are actually resolved.



===============================================================================

8\. REGRESSION SLICE FIRST

===============================================================================



Before large feature expansion, reproduce and protect against known high-value

regressions where they still exist.



At minimum investigate:



A. cancelled dragging



pointercancel, Escape, capture loss, interrupted navigation, or entering

multi-pointer navigation must cancel the active object movement rather than

accepting it.



B. nonzero-origin exports



For an exported world rectangle that starts at a nonzero X/Y origin, coordinates

must be projected relative to the exported bounds correctly.



C. publishing with unsaved edits



Publication must never silently publish an older persisted revision while the UI

appears to publish newer unsaved state.



Add meaningful regression tests before or alongside the fixes.



If the current repository already fixed one of these, prove it with an existing

or new targeted regression test instead of reintroducing unnecessary code.



===============================================================================

9\. REFERENCE MAP DATA

===============================================================================



Complete reference-map delivery without fabrication.



Required concepts include:



\- Castle

\- Turrets

\- Fortresses

\- Sanctuaries

\- Outposts

\- regions

\- restrictions

\- resources when materialized

\- terrain when materialized

\- facility metadata

\- factual structure geometry

\- availability state

\- provenance



Join facility metadata to structural identities where they represent the same

world entity.



Do not draw duplicates simply because two source projections exist.



If resource/terrain geometry remains unavailable from authorized inputs:



\- expose layer unavailability,

\- include reason and coverage information,

\- do not fabricate coordinate records,

\- do not mark KM-02 verified.



Unknown Outpost footprints must remain reference markers until sourced geometry

exists.



===============================================================================

10\. ARTWORK PIPELINE

===============================================================================



Build an explicit artwork registry/pipeline.



Support at minimum the families and variants defined by the implementation plan,

including where applicable:



\- Badland HQ

\- Plains HQ

\- Banner

\- Governor city

\- Bear Trap

\- King's Castle

\- Turrets

\- Fortresses

\- Sanctuaries

\- supported Outpost families

\- supported resources

\- supported terrain

\- region/restriction/annotation presentation symbols



Each supported core family should resolve:



\- palette/list icon

\- map sprite

\- inspector/detail image



Maintain provenance.



Do not treat a typed fallback as final artwork acceptance.



Never fabricate authorization.



The user's supplied Kingshot artwork authorization applies only to supplied or

authorized Kingshot artwork and must not be expanded to unrelated assets.



Create or complete:



resources/data/kingdom-map-art/manifest.v1.json



appropriate frontend asset registry



asset loader



art import/check tooling



content-addressed output paths



asset checksums



dimensions



MIME validation



anchor information



review state



Sanitize or rasterize unsafe SVG input.



Reject scripts, event handlers, foreignObject, uncontrolled external references,

oversized dimensions, and unsupported content.



===============================================================================

11\. SHARED SCENE AND RENDERER

===============================================================================



Use a renderer-independent shared scene model.



The same scene semantics must power:



\- editor rendering

\- explorer rendering

\- observed comparison

\- PNG export

\- SVG export

\- thumbnails/briefs where applicable



Start from improved Canvas 2D unless measured evidence proves it cannot meet the

required scale.



Do not introduce PixiJS merely because it sounds faster.



A focused PixiJS adapter is acceptable only if a representative benchmark

demonstrates material need and the integration preserves export/scene parity.



Required concepts include:



\- image primitives

\- rectangle primitives

\- path primitives

\- text primitives

\- interaction bounds

\- spatial indexing

\- viewport culling

\- deterministic z-order

\- label priorities

\- scheduled requestAnimationFrame redraw

\- separate static/dynamic work where valuable

\- bounded image cache

\- proper cleanup

\- far/medium/near zoom representations



Coordinate fidelity is critical.



Preserve:



\- southwest game origin

\- increasing world Y

\- logical footprint separate from artwork

\- coverage separate from footprint

\- visual bounds separate from collision bounds

\- interaction target separate from all of the above

\- correct cropped/exported origin subtraction

\- correct rotation geometry

\- group rotation around declared pivot

\- upright building art unless approved directional art exists

\- deterministic layer/ground-position z ordering



===============================================================================

12\. KINGDOM EXPLORER

===============================================================================



Explorer must work without requiring a plan.



Complete:



\- map opening

\- facility/object search

\- coordinate jump

\- object inspection

\- artwork/detail image

\- provenance

\- layer availability

\- bookmarks

\- saved views

\- minimap

\- fit map

\- fit selection

\- fit Alliance where applicable

\- fullscreen

\- layer/filter controls

\- keyboard access

\- touch access



Unavailable layers must be explicitly represented.



Do not silently hide missing data as though the layer were empty.



===============================================================================

13\. EDITING

===============================================================================



Complete coherent command-based editing.



Support:



\- place

\- move

\- exact coordinate edit

\- duplicate

\- delete

\- multiselect

\- box selection

\- group

\- rotate

\- align

\- distribute

\- bulk coordinates

\- locks

\- undo

\- redo

\- templates

\- hive generation



Each accepted gesture should create one coherent semantic command.



Pointer cancellation must not become an accepted move.



Group rotation must transform member coordinates around a declared pivot rather

than merely updating a rotation field.



Mixed locked/unlocked selections must not silently mutate only part of an

operation unless the user selected an explicit "editable objects only" behavior.



===============================================================================

14\. PERSISTENCE, CONFLICTS, RECOVERY, PUBLICATION

===============================================================================



Preserve current owner Actions as mutation authority.



Complete:



\- revision-aware saving

\- autosave serialization

\- mutation IDs/idempotency

\- structured conflict handling

\- current authorization revalidation

\- actor-scoped recovery

\- recovery retention bounds

\- save retry safety

\- normalized state installation

\- import/restore state reset

\- reviewed checksum invalidation after edits

\- save-before-publish

\- exact publication revision/checksum



Do not store private recovery state in globally unscoped browser storage.



A lost network response must be safely retryable without duplicate mutation.



A stale client must not overwrite newer work.



Publication must publish the exact successfully saved and reviewed revision.



===============================================================================

15\. HIVE BUILDER

===============================================================================



Complete usable map-aware hive generation.



Support:



\- HQ variant

\- Bear position

\- deterministic generation

\- requested Governor slot count

\- spacing

\- obstacles

\- existing map constraints

\- HQ connectivity

\- alternative previews

\- open slots

\- reserved slots

\- assigned slots

\- stable plan-local Governor references

\- templates

\- identity remapping



If a requested layout cannot be generated legally, report precise infeasibility.



Never silently return fewer requested slots and call the generation successful.



Do not couple plan-local identity directly to application Player identity.



===============================================================================

16\. ANALYSIS

===============================================================================



Provide explainable and reproducible analysis.



Include where supported:



\- territory union

\- HQ-anchored components

\- disconnected components

\- Governor coverage

\- uncovered Governor footprints

\- resource access

\- Alliance-resource ownership only where evidence exists

\- Banner count

\- marginal useful Banner coverage

\- redundant coverage

\- hive density

\- distance to selected Bears

\- distance to facilities

\- distance to Castle

\- custom targets

\- side-by-side alternatives



Keep these concepts separate:



1\. straight-line geometric distance

2\. estimated time from explicit calibration

3\. actual pathfinding



Do NOT infer pathfinding barriers from decorative artwork.



Suggestions must:



\- use deterministic inputs

\- identify algorithm version

\- identify seed where applicable

\- use bounded search

\- explain assumptions

\- return candidate layouts

\- require preview/accept

\- never silently mutate the plan

\- never claim global optimality without proof



===============================================================================

17\. PLAN VERSUS OBSERVED

===============================================================================



Integrate observations into the same shared scene.



Do not maintain a disconnected inferior circle-only renderer if the shared scene

can correctly represent the same information.



Support distinctions such as:



\- moved

\- unexpected

\- missing

\- not observed

\- unresolved identity

\- ambiguous match

\- incompatible coordinates



Preserve partial-evidence semantics.



Positive observed coverage can demonstrate presence.



Absence from partial evidence must not automatically imply disappearance.



Expose:



\- freshness

\- capture extent

\- matching tolerance

\- candidate explanations

\- uncertainty



Comparisons must not mutate plans or observations.



===============================================================================

18\. COLLABORATION AND REVIEW

===============================================================================



Complete asynchronous collaboration first.



Support as required:



\- object comments

\- assignments

\- review

\- revision notes

\- exact reviewed checksum

\- delegated layer access

\- bounded discussions

\- stable object references



Edits after approval must invalidate stale review.



Delegated editors must not gain authority over unrelated layers.



Historical comment context must remain understandable even after objects move or

are deleted.



Live co-editing/presence is optional unless already safely implemented.



Do not let optional live collaboration block completion of the essential

asynchronous product.



===============================================================================

19\. INTEGRATIONS

===============================================================================



Integrate through owner contracts rather than bypassing them.



Review and complete:



Governor assignment

\- bounded search

\- selected identity revalidation

\- membership check at save time



Event positioning

\- immutable published-revision references

\- editing current head must not alter attached historical event positioning



Evidence

\- preserve private lifecycle

\- exactly-once/replay-safe handoff



Notifications

\- after-commit intents

\- bounded recipient selection

\- preferences

\- current source authorization

\- idempotency

\- retry safety



Audit

\- same-transaction semantics where required

\- no raw private layouts/evidence dumped into audit logs

\- late audit failure must not leave an inconsistent committed mutation



Do not send notification spam for pointer movement.



===============================================================================

20\. IMPORT / EXPORT / INTERCHANGE

===============================================================================



Complete strict import preview/commit behavior.



Require:



\- exact map ID/checksum

\- supported versions

\- valid object references

\- bounded input bytes

\- bounded depth

\- bounded counts

\- deterministic duplicates

\- preview diagnostics

\- document hash

\- stale-preview protection

\- atomic commit

\- replace mode

\- explicit append mode

\- key remapping

\- identity/group conflict handling



Unknown future versions must fail clearly.



CSV/text exports must neutralize spreadsheet formula injection where applicable.



Named external planner adapters require explicit mapping fixtures.



===============================================================================

21\. VISUAL EXPORTS

===============================================================================



PNG/SVG and related visual renditions must use the shared scene.



Support export scopes:



\- viewport

\- selection

\- Alliance

\- full map



Include as selected:



\- artwork

\- labels

\- map layers

\- grid/coordinates

\- annotations

\- legend

\- title

\- plan revision

\- map release

\- observation freshness

\- asset-pack version

\- locale/font provenance



Fix nonzero-origin projection.



Support:



\- text wrapping

\- deterministic fonts

\- output-size estimation

\- bounded raster allocation

\- cancellation

\- cleanup

\- tiled output where appropriate



Do not leak hidden or unauthorized layers into export.



Private renditions must never be accidentally exposed through publicly cached

asset paths.



===============================================================================

22\. PRIVATE SHARING

===============================================================================



Complete secure private immutable sharing where required.



Support:



\- pinned revision

\- allowed layer scope

\- hashed token

\- authenticated recipient

\- expiration

\- revocation

\- current authority checks

\- export/download authorization



Possession of a link alone must not create unrestricted authority.



Sharing must not create fake Alliance membership.



Platform administration must not become an implicit bypass around normal

Alliance/Kingdom/Intelligence authority.



===============================================================================

23\. ACCESSIBILITY AND LOCALIZATION

===============================================================================



Canvas rendering does not remove accessibility obligations.



Provide a synchronized semantic representation.



Support:



\- searchable semantic object list

\- exact coordinates

\- keyboard actions

\- bounded/virtualized large lists

\- preserved focus

\- off-page selection

\- visible focus

\- reduced motion

\- non-color status communication

\- approximately 44 CSS pixel touch targets

\- live announcements where appropriate

\- shortcut discovery

\- desktop

\- tablet

\- mobile

\- RTL control layout

\- all existing territory locales



World axes must not reverse in RTL.



Long translated text must not destroy usability.



Do not allow virtualization to break focus or object semantics.



===============================================================================

24\. PERFORMANCE

===============================================================================



Preserve existing repository performance budgets.



Also measure representative Kingdom Map workloads.



Use representative fixtures, not merely tiny synthetic examples.



Evaluate:



\- first useful map

\- initial map data/image payload

\- pointer-to-preview latency

\- frame time

\- decoded artwork cache

\- JS heap

\- large editable plans

\- complete reference scene

\- stress scene

\- raster export allocation

\- memory cleanup



The implementation-plan targets are gates to evaluate, not facts to assume.



Do not falsely record an unmeasured target as passing.



Investigate material regressions.



Do not optimize prematurely by introducing architecture complexity without

measurement.



===============================================================================

25\. SECURITY

===============================================================================



Treat all imports and asset inputs as hostile until validated.



Verify:



\- strict owner-boundary validation

\- bounded uploads/imports

\- malformed image/SVG rejection

\- current-scope authorization

\- actor switching

\- stale-tab authorization

\- recovery isolation

\- share revocation

\- cache isolation

\- no private data in public caches

\- no raw evidence leakage

\- no authorization bypass through presentation/read models

\- no dangerous external references in artwork

\- no formula injection in exported tabular data

\- idempotency cannot be abused with changed request bodies



Do not weaken existing security checks merely to make tests pass.



===============================================================================

26\. TEST ORGANIZATION

===============================================================================



Preserve owner-based test organization.



Use existing:



Unit

Feature

Integration

Frontend

Browser



structures.



Do NOT recreate retired tests/v3 or unrelated flat test folders.



Add tests near the owner of the behavior.



The acceptance matrix includes at minimum:



T01 release integrity



T02 geometry



T03 artwork



T04 scene fidelity



T05 editing



T06 persistence



T07 hive / identity



T08 analysis



T09 reconciliation



T10 collaboration / integration



T11 interchange



T12 export / sharing



T13 accessibility / localization



T14 performance / security



T15 complete cross-product journey



The final cross-product journey should prove, where available:



Explore

→ create

→ assign

→ fix

→ compare

→ review

→ publish

→ export

→ reopen

→ revoke



===============================================================================

27\. TEST EXECUTION STRATEGY

===============================================================================



Use focused verification continuously.



Relevant commands may include, subject to current package scripts:



npm run check:territory-geometry



npm run check:territory-export



npm run check:territory-localization



php artisan kingdom-maps:validate



php artisan kingdom-maps:verify-sources



php artisan kingdom-maps:list



php artisan test --fail-on-empty-test-suite \\

tests/Contexts/GameWorld/KingdomMaps



php artisan test --fail-on-empty-test-suite \\

tests/Contexts/Operations/TerritoryPlanning



php artisan test --fail-on-empty-test-suite \\

tests/ReadModels/TerritoryPlanning



npm run test:visual -- \\

tests/ReadModels/TerritoryPlanning/Browser/TerritoryPlanner.spec.ts



composer test:architecture



composer lint:check



composer types:check



npm run check



Do not assume these commands are still exact if package scripts have evolved.

Inspect the repository first.



Run independent Node/PHP/artifact checks in parallel when safe.



Do not repeatedly run the entire slow suite after every tiny edit.



Use:



\- focused tests during implementation

\- broader owner suite after each coherent package

\- full relevant verification on the final candidate



If a long-running test is still executing and independent implementation work is

available, continue productive work rather than idling unnecessarily.



Never claim a test passed unless it actually executed successfully.



===============================================================================

28\. VISUAL VERIFICATION

===============================================================================



Visual verification is part of completion, not optional polish.



Where the environment supports browser execution, verify representative:



\- desktop

\- tablet

\- mobile

\- world zoom

\- regional zoom

\- hive zoom

\- all supplied artwork families

\- dense scenes

\- read-only mode

\- invalid placement

\- partial observation

\- missing artwork fallback

\- long labels

\- RTL

\- artwork-bearing exports



Review visual diffs.



Do not automatically replace snapshots simply because they changed.



Determine whether the new result is correct first.



===============================================================================

29\. GIT DISCIPLINE

===============================================================================



Never commit directly to main.



Never push unless explicitly authorized.



Never merge unless explicitly authorized.



Do not force push.



Do not reset unrelated work.



Do not use destructive cleanup against files you did not create.



Make small coherent commits.



Before every commit:



1\. inspect git status

2\. inspect git diff

3\. ensure unrelated files are not included

4\. run appropriate focused checks

5\. use a meaningful commit message



Examples:



feat(territory): establish workspace scene contracts



fix(territory): cancel interrupted canvas drag



fix(territory): project nonzero export origins correctly



feat(territory): add revision-safe plan persistence



test(territory): cover publication with unsaved edits



docs(territory): update kingdom map delivery ledger



Do not create meaningless "work in progress" commits containing unrelated areas.



===============================================================================

30\. DO NOT LOSE CURRENT WORK

===============================================================================



Before editing a file, inspect its current state.



If current code differs substantially from the historical plan:



\- understand why,

\- inspect recent commits,

\- preserve legitimate newer implementation,

\- adapt the task,

\- update the ledger.



Do not "restore" historical architecture over newer correct work merely because

the old plan describes an older baseline.



===============================================================================

31\. BLOCKERS

===============================================================================



A blocker is valid only when the required input genuinely cannot be derived from

repository code, existing fixtures, authorized supplied assets, or existing

source artifacts.



Examples of legitimate blockers may include:



\- authentic artwork master not supplied

\- missing authoritative resource coordinates

\- missing authoritative terrain geometry

\- unknown Outpost footprint

\- inaccessible external authorized evidence



For a genuine blocker:



1\. record exact missing input

2\. record affected task/test

3\. leave safe explicit unavailable behavior

4\. do not fabricate data

5\. continue independent work

6\. mark blocked\_input rather than verified



A blocker in KM-03 does not automatically prevent all KM-04 renderer work when

fixtures/fallback contracts are sufficient to proceed.



Exploit dependency parallelism.



===============================================================================

32\. RELEASE CLOSEOUT

===============================================================================



Do not declare completion based on a narrow green check.



KM-18 requires a reviewable candidate with traceable evidence.



Before final completion:



1\. Reconcile every KM-00 through KM-18 package.

2\. Reconcile every T01 through T15 acceptance area.

3\. Review all modified files.

4\. Review all migrations.

5\. Review authorization boundaries.

6\. Review asset provenance.

7\. Review map-data provenance.

8\. Review architecture tests.

9\. Review localization.

10\. Review accessibility.

11\. Review security boundaries.

12\. Review performance evidence.

13\. Run appropriate final verification.

14\. Record the candidate Git SHA.

15\. Update the delivery ledger.

16\. Update acceptance evidence.

17\. Record remaining external blockers accurately.

18\. Ensure no placeholder or fallback is incorrectly labelled complete.

19\. Ensure no unexecuted test is labelled passing.

20\. Ensure no unmerged historical branch behavior is counted as present.



===============================================================================

33\. COMPLETION STANDARD

===============================================================================



The implementation is complete only when every in-repository requirement is one

of:



A. VERIFIED

Implemented and backed by executed evidence.



or



B. BLOCKED\_INPUT

Cannot be truthfully completed because a clearly identified external input is

missing, with all independent implementation completed and safe behavior in

place.



"Planned", "partially implemented", "probably working", "tests should pass", and

"another agent can finish this" are not completion states.



===============================================================================

34\. WORKING STYLE

===============================================================================



Be autonomous.



Do not ask me to approve each phase.



Do not repeatedly summarize the plan back to me instead of implementing it.



Do not spend the entire session explaining what you intend to do.



Inspect.

Implement.

Test.

Review.

Commit coherent work.

Update evidence.

Continue.



When a command fails:



\- diagnose the failure,

\- determine whether it is caused by your change or baseline state,

\- repair it when in scope,

\- record unrelated baseline failures,

\- continue other independent work.



When tests are slow:



\- use targeted suites,

\- parallelize independent checks,

\- continue independent implementation,

\- run complete relevant verification at appropriate gates.



Prefer correctness, explicit contracts, traceable evidence, and maintainability

over shortcuts.



===============================================================================

35\. FIRST ACTION

===============================================================================



Begin now.



First:



1\. Read docs/product/kingdom-map-workspace-implementation-plan.md completely.

2\. Run git status.

3\. Record branch and HEAD.

4\. Compare against current main.

5\. Inspect the initial files named above.

6\. Reconcile KM-00 and KM-01 against current implementation.

7\. Identify whether the three initial regressions still reproduce:

&#x20;  - cancelled drag

&#x20;  - nonzero-origin export

&#x20;  - publish with unsaved edits

8\. Update/create the delivery ledger.

9\. Add regression evidence where needed.

10\. Begin implementation.



Then continue through the dependency graph without waiting for another prompt.



DO NOT PUSH.

DO NOT MERGE.

DO NOT MODIFY MAIN DIRECTLY.



Continue until the repository is genuinely implementation-complete to the

standard above or the only remaining items are explicitly documented external

input blockers.

