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
