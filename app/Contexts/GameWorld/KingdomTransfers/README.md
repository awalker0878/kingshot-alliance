# Kingdom Transfers

GameWorld owns transfer windows, official groups, participant plans, observations, eligibility interpretation, capacity commitments, blockers and completion facts. HTTP adapters and cross-owner ReadModels consume these owners; transfer workflows coordinate their actions without acquiring a second persistence or eligibility authority.

TransferEligibilityEvidenceQuery selects bounded factual witnesses for requested participants and Kingdoms while preserving live conflicts, uncertainty and provenance. TransferCapacityPlanningQuery selects current authoritative observations and aggregates consuming commitments in SQL. Complete observation history is independently authorized and paginated by TransferObservationHistoryQuery. See [ADR-0041](../../../../docs/architecture/adr/0041-transfer-eligibility-evidence-and-history.md).

TransferSelfEligibilityQuery selects the current actor’s participant and delegates current assessment to TransferEligibilityQuery; it does not reconstruct the evaluator input. Its complete observation count is an independent scoped SQL aggregate. See [ADR-0044](../../../../docs/architecture/adr/0044-canonical-self-transfer-eligibility.md).
