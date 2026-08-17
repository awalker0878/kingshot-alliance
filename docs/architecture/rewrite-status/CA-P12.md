# CA-P12 — Database and Persistence Cleanup

Status: **PASS**

## Persistence corrections

- Removed legacy migration/import/drop logic for `event_reminder_deliveries` and `king_perk_reminder_deliveries` from the generic Communications delivery migration.
- The clean-room migration now creates only final V3 `notification_deliveries` and `notification_preferences` state; it does not inspect or migrate deleted legacy tables.
- Confirmed Alliance membership is persisted by `player_id`, not `user_id`.
- Confirmed Kingdom role assignments are persisted by `player_id`, not `user_id`.
- Confirmed GameWorld Player ownership intentionally retains scalar `user_id` as the Accounts ownership reference.
- Added a dependency-free V3 persistence verifier so these schema invariants are executable without booting Laravel.

## Verification

- `php -l database/migrations/2026_08_16_000000_create_notification_delivery_tables.php`: PASS
- `php -l tests/v3/Architecture/verify-persistence.php`: PASS
- `php tests/v3/Architecture/verify-persistence.php`: PASS
- `php tests/v3/Architecture/verify.php`: PASS
- Clean-room migration scan for `Schema::hasTable` / `migrateLegacy*`: 0 violations

Known blockers: **NONE**

Safe to proceed: **YES**
