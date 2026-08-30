# Route-file reference

Status: Current

| File | Primary entry-point concern |
| --- | --- |
| `routes/account.php` | Account routes. |
| `routes/api.php` | Versioned credential-scoped API. |
| `routes/console.php` | Scheduler/console commands. |
| `routes/contributions.php` | Contribution/reporting routes. |
| `routes/event-history.php` | Event-history/read-model routes. |
| `routes/gift-codes.php` | Gift Code catalogue/detail and Governor redemption routes plus MFA/password-confirmed platform moderation, source-policy and curator operations. |
| `routes/integrations.php` | Integration administration routes. |
| `routes/king-perks.php` | King Perks routes. |
| `routes/kingdoms.php` | Kingdom/GameWorld and related workflow routes. |
| `routes/platform.php` | Platform administration. |
| `routes/web.php` | Main web route composition. |

Route files organize transport registration and are not bounded contexts. For ownership, follow the target controller/action and use the Architecture context map.
