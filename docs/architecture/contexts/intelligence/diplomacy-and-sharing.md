# Diplomacy and intelligence sharing

Status: Current  
Context: Intelligence  
Implementation: `app/Contexts/Intelligence/Diplomacy` and `Sharing`

Diplomacy owns analytical relationship/diplomacy state. Sharing owns the grants/history required to expose Intelligence across allowed scopes.

## Invariants

Sharing a projection does not transfer ownership of the underlying observation. Revoking current access does not erase historical ownership/provenance unless a separate retention/privacy requirement requires deletion or anonymization.

Authorization remains active-Player/scope based and is interpreted by Intelligence. Platform Administrator does not silently bypass game-intelligence access.