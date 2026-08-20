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

User-facing changes must address loading/empty/error states, responsive behavior, keyboard/focus/accessibility needs and localization. Every Inertia page uses the locale service for interface text, dates and numbers; non-English catalogues may fall back to the complete English domain catalogue when a reviewed translation is unavailable. Do not duplicate server authorization logic in the browser as a security control.

`npm run check:page-localization-coverage` prevents a page from bypassing the localization service. `npm run check:event-localization-coverage` separately protects the reviewed minimum translated Event catalogue while retaining English fallback for keys not yet translated.

Successful domain mutations use the shared typed action-receipt contract. Controllers flash `actionReceipt` with a stable code, interpolation parameters and a supported tone; `AppLayout` localizes and announces it through `ActionNotice`. Pages must not add a second success-status prop or decode receipt codes locally. `npm run check:action-receipt-coverage` keeps controller codes and the English receipt catalogue synchronized. Framework authentication responses rendered outside `AppLayout` retain their framework-defined status contract.

The production performance gate measures the complete initial JavaScript graph, the authored `resources/js/app.ts` bootstrap source, the largest lazy page chunk and the largest stylesheet. The entry-source budget prevents bootstrap responsibilities from growing while the initial-JavaScript budget accounts for compiled framework and shared dependencies exactly once.
