# ADR-0044: One composition of current transfer eligibility

Status: Accepted

## Context

TransferSelfEligibilityQuery, consumed by the Alliance Assistant, independently assembled TransferEligibilityInput and evaluated participant observations, official groups and target conditions. The management query already used the bounded, conflict-preserving evidence boundary established by ADR-0041. The self path instead hydrated complete participant and condition histories and unrelated official groups. Maintaining two compositions could let Assistant answers diverge from management as an eligibility requirement changed.

A regression with 183 observations and 183 condition records demonstrated unbounded self-query hydration. Another case hydrated 80 historical observations for a previous target, even though none could affect the current assessment. The response's observationCount intentionally describes complete history and must not become the length of a clipped witness set.

## Decision

TransferEligibilityQuery is the single composition of **current persisted transfer facts** into an eligibility assessment. Its existing TransferEligibilityEvidenceQuery, selectors, evaluator and capacity projection retain their established ownership and conflict semantics. TransferSelfEligibilityQuery authorizes the current actor and Alliance, selects only that actor's nonwithdrawn participant through TransferParticipantQuery, verifies the active home/target and any explicitly requested target, delegates evaluation to the canonical query, and formats the self response.

The self path no longer depends on the evaluator, observation selector, condition selector or capacity-composition query. It does not construct an alternative input or assessment, including for Staying and incomplete source/target cases. A scoped SQL count reports all of the participant's observations independently of the bounded evidence used in the assessment. No history rows are hydrated merely to count them, and irrelevant former-target observations do not enter current evaluation.

TransferEvidencePreviewQuery remains an explicitly hypothetical, nonpersistent preview of proposed evidence, not another interpretation of current facts: its before assessment uses the canonical query and its proposed after assessment uses the same pure evaluator. This decision does not replace that meaningful preview with a self projection or a new generic evaluation framework.

## Alternatives and consequences

Keeping the duplicate composition was rejected because it required every requirement and evidence fix to be applied twice. Introducing a new interface, shared factory framework or second generic query was rejected because the existing owner query already supplies the typed assessment and provenance. The smaller self adapter preserves its present response contract without a deprecated alias, comparison mode, fallback implementation or compatibility shim.

GameWorld remains the authority; the Assistant remains an authorized read composition. Current authorization is checked before participant evidence is read, and the participant is bound to Alliance, plan and actor. Missing/withdrawn participants or a mismatched requested target remain unavailable. The query is stateless and creates no cache authority or new transaction/network boundary. No schema or data migration is needed for the fresh application.

Application hydration is bounded by the canonical evidence contract, while SQL counting and conflict selection still examine relevant indexed history. Database work is not claimed to be constant irrespective of history. Current outcomes, requirements, source references, dates, capacities and complete history counts must remain equal to the canonical result; neither optimism nor silent history truncation is acceptable.

## Verification

Behavioral regressions compare complete serialized requirements, outcome, primary action, evaluation time, group and condition provenance against the actual canonical query. They cover conflicting/current/untrusted and expired facts, large histories, irrelevant previous targets, Staying, incoming and missing endpoints, revoked membership, archived targets and withdrawal, alongside the existing target and self-isolation cases. A pure architecture contract prevents the self adapter from regaining direct evaluator/selector dependencies or constructing its own assessment/input. Containing Transfer, Assistant and Roster behavior remains required; executed results and the final containing revision belong in HARD-097 in the delivery ledger.
