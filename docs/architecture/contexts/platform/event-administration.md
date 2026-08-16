# Platform Event administration

Status: Current  
Context: Platform  
Implementation: `app/Contexts/Platform/EventAdministration`

Platform Event administration provides cross-tenant/catalogue administration over Event configuration where the product needs a platform-level operator surface.

Operations remains owner of Event runtime semantics, scopes, occurrences, participation and `events.*` authorization. Platform administration orchestrates supported Operations configuration contracts rather than reaching into Operations persistence and redefining Event behavior.