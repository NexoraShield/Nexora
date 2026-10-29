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
