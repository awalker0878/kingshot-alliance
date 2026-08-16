# Roster intelligence and contributions

Status: Current  
Context: Intelligence  
Implementation: `app/Contexts/Intelligence/Roster` and `Contributions`

Roster Intelligence owns observed roster/snapshot state used for analysis. Contributions owns the contribution ledger/reporting facts produced by the Intelligence model.

## Historical identity

Contribution and roster history is keyed to durable Player/Alliance/Kingdom identities appropriate to the fact. Current membership is an authority/eligibility fact; it must not rewrite who historically produced an observation/contribution.

## Boundary with Operations

Event result/execution facts remain Operations-owned. Intelligence may analyze or report them but does not create a second writable Operations Event ledger.

## Authority

Current Intelligence permission vocabulary includes `intelligence.view`, `contributions.manage` and `kingdoms.manage`, interpreted against the active Player and relevant scope.