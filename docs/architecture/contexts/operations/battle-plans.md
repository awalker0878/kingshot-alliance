# Operations — BattlePlans

Status: Current — Architecture V3

Implementation target: `app/Contexts/Operations/BattlePlans`

BattlePlans owns Event objectives, objective assignments and operational battle-plan state.

Assignments use explicit targets and Player identity where a Player is the target. BattlePlans remains inside the Operations consistency boundary and does not create a separate context.