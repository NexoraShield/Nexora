# Nexora Bot — Release Notes

## Migration Guide

### From Mirza to Nexora
1. Back up the database: `mysqldump -u DB_USER -p DB_NAME > backup.sql`
2. Keep a backup of `config.php`.
3. Run `migrate-mirza.sh` on the server (or copy files manually).
4. Run `install.sh` to install new files.
5. Open the panel and verify panels/products/categories.
6. Check `lang/fa.php` (must not be empty).

### From ViraNaut to Nexora
1. Back up database and `config.php`.
2. Run `migrate-viranaut.sh`.
3. Run `install.sh`.
4. Verify the panel.

### Important Migration Notes
- Always back up before updating.
- Transfer `config.php` manually (installer won't overwrite it).
- After updating, check `lang/override/`.
- For future updates use `nexora update`.

---


## v1.9.8 (2026-10-29) — Update Mechanism Hardening
**New**
- `nexora_self_heal()` runs automatically on boot (via `nexora_style.php`, loaded early by `keyboard.php`): ensures style/emoji columns, `bot_custom_emoji` + `nexora_emoji_map` tables, `agent_auth` + `agent_login_attempts` tables, PaySetting defaults, and a guarded `opcache_reset()` — all idempotent, once per PHP process. Even if `migrate.php` was not run or a table is missing, the bot self-heals on first request.
**Fixed**
- `migrate.php` now also ensures `agent_login_attempts` table.
- `install.sh`: patch loop now tracks skipped patches (`_skipped_patches`) and prints a clear WARNING listing them; final status is "PARTIALLY" vs "successfully". Added post-install verification: checks all required files exist + runs `php -l` on EVERY installed PHP file (not just a subset); aborts with a clear error on missing files or syntax errors. OPcache reset already present at end of install.


## v1.9.7 (2026-10-29) — Payment Idempotency + Lint Baseline + Emoji Picker Completion
**Fixed**
- `claimPaymentPaid()` guard aligned from `<> 'paid'` to `NOT IN ('paid', 'reject')`, matching the admin `Confirm_pay_` claim so a stale/replayed gateway webhook cannot re-claim an admin-rejected payment and re-trigger `DirectPayment` (defense-in-depth; downstream guards already prevent double delivery).
- Normalized `invoice.Status` casing in the `removeservice-` admin flow (`status` -> `Status`, the canonical column name from all INSERT sites). Cosmetic on MySQL (case-insensitive columns) but keeps PHP keyed reads consistent.
- Completed the orphaned `panel/emoji_picker.php`: fixed the parse error (unclosed `if (!empty($emoji_map)):`), stale `mirza_style.php` include -> `nexora_style.php`, `<value>` -> `<option>` HTML typos, and `$pdo->query($sql, $params)` misuse -> `prepare()/execute()`.
- Added the missing `nexora_emoji_map` table to `nexora_ensure_emoji_table()` (idempotent `CREATE TABLE IF NOT EXISTS`) and auto-ensure it on the picker page, so the premium emoji picker is functional.
**Audit**
- P1: traced agent invoice path end-to-end (agent web panel is read-only affiliate dashboard; real purchase flow is the Telegram bot: panel select -> product -> INSERT IGNORE INTO invoice -> linkPaymentToOrder -> gateway Payment_report -> claimPaymentPaid -> DirectPayment -> Marzban createUser + sendMessageService to the customer).
- P4: full-repo `php -l` sweep -> 105 clean / 0 errors (root + agent-panel + payment + cronbot + panel + api).
- P5: audited `Confirm_pay_` flow (1696-1753): role guard + atomic claim lock verified; TOCTOU under single admin = low.
- P2: casing audit confirmed no PHP keyed-read breakage (invoice reads use `Status`; service_other uses `status` internally consistent); cosmetic lowercase writers in cronbot/configtest.php flagged for follow-up.
**Docs**
- Backfilled v1.9.6 entry (was missing from release notes).

## v1.9.6 (2026-10-29) — DirectPayment Re-entrancy Fix
**Fixed**
- `DirectPayment()` no longer double-credits on a re-entrant call to an already-settled order: when `invoice.Status == 'active'` or the service is already `paid`, the function now returns before the balance credit (v1.9.6). Upstream `claimPaymentPaid()` makes this path rare; the guard keeps it safe.
- Payment-callback idempotency verified (not a bug): every gateway callback gates `DirectPayment()` behind `claimPaymentPaid()` (conditional `UPDATE ... WHERE payment_Status <> 'paid'`).


## v1.9.5 (2026-10-29) — Color/Emoji Runtime Fix
**Fixed**
- `nexora_apply_bb_style` exact flip-lookup failed for menu buttons whose label was prefixed with an emoji glyph by `nexora_apply_menu_emoji` (e.g. 🎯 خانه), silently dropping panel-configured colors and custom emoji for keyboard-group buttons. Added a tolerant prefix-match fallback (strip a leading symbol/mark/whitespace run, then retry the exact lookup). Inline buttons (callback_data/url set) keep their plain text and the original exact path. Regression test: 6/6 cases pass.

## v1.9.4 (2026-10-29) — Agent Panel Auth Regression
**Fixed**
- Removed stray `}` in `agent-panel/inc/auth.php`; closed unclosed `if` block in `agent-panel/login.php`; stripped BOM.
- Replaced `??` on undefined constants (`NEXORA_AGENT_LOGIN_MAX`, etc.) with `defined() ?: default`.
- Verified brace balance: auth.php 42/42, login.php 32/32.

## v1.9.3 (2026-10-29) — Agent Login Brute-Force Throttle
**Security**
- Agent login: 5 attempts / 300s lockout with DB counter; counter resets on success.

## v1.9.2 (2026-10-29) — Runtime Color/Emoji Wiring
**Fixed**
- Wired `nx_bb_*` color+emoji overrides from the admin panel into the bot runtime (`keyboard.php`) so panel color choices apply to keyboard-group buttons. Partially regressed (emoji-prefixed labels); fully fixed in v1.9.5.

## v1.9.1 (2026-10-29) — Agent Panel Login Fix
**Fixed**
- Agent panel login function name + invoice status casing.

## v1.9.0 (2026-10-29) — Maintenance Mode
**New**
- Maintenance mode panel (`panel/maintenance.php`).

## v1.8.0 (2026-10-29) — Stability + Migration Guide
**Fixed**
- Bug review & stability improvements.
**Docs**
- Migration guide added to release notes (EN + FA).

## v1.7.0 (2026-10-29) — Audit Log
**New**
- `panel/audit.php` — full audit log of admin actions (admin, action, detail, IP, timestamp).
- Auto-creates `AuditLog` table on first run.
- Nav entry: 📜 Audit Log.

## v1.6.0 — Extended Color Palette
**New**
- `nexora_color_palette()` with 10 colors: default, blue, green, red, orange, light-blue, purple, pink, teal, gray.
- `nexora_color_slugs()` for validation.
- `nexora_btn_style()` maps extended slugs to Telegram native styles.
- All panels (botbuttons, btnstyle, panels) now show all 10 colors in dropdowns.
- `STYLE_COLORS` built dynamically from palette.

## v1.5.0 — Magic (Auto-Repair)
**New**
- `panel/magic.php` — one-click system health check & auto-repair.
- Ensures style/emoji columns exist, checks PaySetting, clears OPcache, counts records, removes empty overrides.
- Color-coded report (OK / Fixed / Error).

## v1.4.0 — Central Color Manager
**New**
- `panel/botcolors.php` — central hub for all button colors.
- Nav entry: "رنگ تمام کلیدها".

## v1.3.1 — Bug Fixes
**Fixed**
- `sendmessage()` signature in backup cron.
- Double `layout_head` include in `panels.php`.

## v1.3.0 — Luxury Theme + New Features
**New**
- Luxury glass theme (gold/violet) in `panel/css/nexora-glass.css`.
- `cronbot/backup.php` — automatic DB backup + Telegram delivery.
- `cronbot/payment_reminder.php` — unpaid invoice reminders.

## v1.2.0 — Unified Bot Editor
**New**
- `panel/botbuttons.php` now edits **text + color + premium emoji** for all keys (keyboard, back, menu).
- Covers `users.backbtn`, `Admin.*` back buttons, main-menu labels.
- Text saved to `lang/override/fa.php`; color/emoji to `PaySetting`.

## v1.1.0 — Migration Scripts
**New**
- `migrate-mirza.sh`, `migrate-viranaut.sh` + README migration docs.

## v1.0.1 — Rebrand & Core Fixes
**Fixed**
- Empty `lang/fa.php` (0 bytes) root cause of wrong bot messages.
- HTTPS vhost repointed to Nexora.
- `panel/inc/bot_emojis.php` missing require removed.
- Admin aboutBot conditional + channel link fix.

## v1.0.0 — Initial Nexora Release
- Full Nexora install, rebrand from Mirza, premium emoji support, glass panel.
