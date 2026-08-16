# Experience principles

Status: Current

The application should make actor and scope visible enough that users understand **which Player, Alliance or Kingdom they are acting as/within**.

## Principles

- make active Player identity obvious on game-domain screens;
- do not imply that account identity and Player identity are interchangeable;
- present only actions the current actor/scope can actually perform, while still enforcing authorization on the server;
- keep loading, empty, failure and retry states explicit;
- preserve historical attribution when a Player later changes Alliance/Kingdom;
- keep operational planning screens focused on current execution and analytical screens focused on historical/observed evidence;
- use accessible names, keyboard navigation, visible focus and responsive layouts;
- keep localization-friendly strings and avoid embedding business rules only in frontend copy.

Cross-context dashboards should feel unified to the user even though backend ownership remains separated.