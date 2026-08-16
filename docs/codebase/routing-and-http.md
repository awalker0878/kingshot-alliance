# Routing and HTTP

Status: Current

Route registration is split by major entry-point concern rather than being treated as architectural ownership.

Current route files include:

- `routes/account.php` — account-facing routes;
- `routes/api.php` — API entry points;
- `routes/contributions.php` — contribution/reporting routes;
- `routes/event-history.php` — event-history projection routes;
- `routes/integrations.php` — integration routes;
- `routes/king-perks.php` — King Perks routes;
- `routes/kingdoms.php` — Kingdom/game-world and related workflow routes;
- `routes/platform.php` — platform administration;
- `routes/web.php` — main web application composition;
- `routes/console.php` — scheduled/console commands.

A route filename is not a bounded context. Follow the controller/action namespace to determine the implementation owner, then use the architecture context map for business ownership.

## HTTP adapter rule

Controllers/middleware may authenticate, resolve context, validate transport input and format responses. They should not implement multi-step business invariants, reach through another context's persistence or become transaction owners for domain mutations.