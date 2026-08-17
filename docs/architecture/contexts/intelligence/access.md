# Intelligence — Access

Status: Current — Architecture V3

Implementation target: `app/Contexts/Intelligence/Access`

Access owns Intelligence permission vocabulary and authorization interpretation.

The actor is the active Player. Intelligence may consume current Alliance/GameWorld facts as inputs, but it interprets those facts using Intelligence-owned permission semantics. Authorization services do not acquire database locks; write Actions revalidate mutable authority inside owner-controlled transactions.