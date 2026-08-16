# Frontend

Status: Current

The frontend uses Inertia, Vue 3 and TypeScript with application code under `resources/js`.

```text
resources/js/
├── app.ts
├── components/      # reusable UI pieces
├── layouts/         # page/layout composition
├── localization/    # localization resources/helpers
├── pages/           # Inertia page components
└── types/           # frontend TypeScript contracts
```

## Boundary

Frontend page folders are presentation organization, not bounded contexts. Server-provided page props/read models remain responsible for authorization and data-shaping boundaries.

## Change expectations

User-facing changes should address loading/empty/error states, responsive behavior, keyboard/focus/accessibility needs and localization impact where applicable. Do not duplicate server authorization logic in the browser as a security control.