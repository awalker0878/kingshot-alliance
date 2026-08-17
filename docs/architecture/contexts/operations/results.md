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