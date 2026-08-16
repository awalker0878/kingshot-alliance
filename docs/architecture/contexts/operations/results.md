# Operational results

Status: Current  
Context: Operations  
Implementation: `app/Contexts/Operations/Results`

Results owns operational Event result capture and normalized result metrics.

## Ownership

- Event/result score semantics and metric definitions used by live result capture;
- Player result identity through durable `player_id`;
- Alliance result identity through canonical Alliance identity;
- frozen historical context needed to interpret the Event result.

Score and component metrics remain distinct concepts; the metric catalogue does not create an unexplained universal cross-Event contribution score.

## Boundary

Operations owns captured operational result facts. Intelligence/EventAnalysis may consume those facts for history/trend/reporting without becoming a second canonical result ledger.