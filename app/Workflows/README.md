# Cross-context workflows

This root owns explicit command orchestration that spans multiple bounded contexts, such as Kingdom transfer, governance coordination, registration and Player-context activation.

A workflow coordinates supported context application contracts but does not become persistence owner of participating aggregates. Business policy remains with the context that owns the rule.
