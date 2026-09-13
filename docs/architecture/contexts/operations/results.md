# Operations — Results

Status: Current — Architecture V3

Implementation target: `app/Contexts/Operations/Results`

Results owns authoritative operational Event result capture and normalized result metrics.

## Ownership

- Event/result score semantics and metric definitions used by live result capture;
- Player result identity through durable `player_id`;
- Alliance result identity through canonical Alliance identity;
- frozen historical context needed to interpret the Event result.

Score and component metrics remain distinct concepts; the metric catalogue does not create an unexplained universal cross-Event contribution score.

## Boundary

Operations owns captured operational result facts. `ReadModels/EventAnalysis` may compose those facts for history, trend and reporting without becoming a second canonical result ledger.

## Bear Hunt aggregation and receipts

Reviewed Bear Hunt reports contain 1–100 Governors. Their synchronous occurrence projection admits at most 1,000 distinct reviewed Governors across retained baselines, including removed reports. This application work budget bounds atomic ranking and result writes; exceeding it rejects the entire command without truncating history. Accepted report history is aggregated in PostgreSQL, and overflow of the supported integer score range rejects the command.

The destination receipt captures only that stored report's Governors, with their current score/rank, including on replay. Complete occurrence results stay behind authorized Results queries. Removal restores manual baseline score/rank and preserves result identity, notes, outcome and metrics. See [ADR-0072](../../adr/0072-bounded-bear-hunt-result-recomputation.md).
