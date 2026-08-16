# Intelligence authorization

Status: Current  
Context: Intelligence  
Implementation: `app/Contexts/Intelligence/Access`

Intelligence owns authorization semantics for observational, analytical and shared-intelligence behavior.

## Authority inputs

The actor is the active Player. Intelligence may consume current Alliance relationship and scope facts as inputs while applying Intelligence-owned permission rules.

## Invariants

- access is evaluated for the active Player and concrete Intelligence scope;
- one Player's authority is not inherited by another Player owned by the same User;
- Platform Administrator does not grant Intelligence access;
- transaction-time authorization is revalidated at the write boundary where mutable access state matters;
- Intelligence does not use Operations or Alliance permission vocabulary as a substitute for its own authorization semantics.
