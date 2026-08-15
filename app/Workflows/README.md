# V2 cross-context workflows

This root owns explicit orchestration that spans multiple bounded contexts, such as Kingdom transfer, Alliance leadership transfer, onboarding and account deletion.

A workflow may coordinate supported context application contracts but never becomes the persistence owner of participating aggregates. Workflows must not import `App\Domain\*` or act as compatibility facades.