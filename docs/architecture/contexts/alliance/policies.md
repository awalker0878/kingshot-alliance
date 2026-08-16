# Alliance policies

Status: Current  
Context: Alliance  
Implementation: `app/Contexts/Alliance/Policies`

Alliance Policies contains Alliance-owned business rules that apply across Alliance capabilities without becoming a separate bounded context.

## Ownership

Policies may evaluate Alliance state, membership state and Alliance configuration to make Alliance-owned decisions. They do not own persistence that belongs to another context and they do not grant authority independently of the active Player's Alliance relationship.

## Current contract

Policy behavior remains scoped to the concrete Alliance and uses Alliance-owned inputs. Rules that belong to Operations, Intelligence, GameWorld or Platform stay with those owners even when they consume Alliance facts.
