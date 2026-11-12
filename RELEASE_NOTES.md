# Nexora Bot — Release Notes
## v1.22.70 (2026-11-12) — Fast bot, gateway manager, panel fixes

**Speed**
- The colour and emoji map of the buttons is read once per request instead of once per button (hundreds of queries per message).
- The `PaySetting` table is cached per request; `getPaySettingValue()` no longer bypasses the cache.
- The language file (340 KB) is parsed once per request instead of twice.
- The schema repair (`nexora_self_heal`) no longer runs its DDL on every request: the flag is written before the work, every statement is guarded on its own, a lock prevents parallel runs and the opcode cache is no longer wiped.
- The membership check of the required channels is cached for five minutes and uses a short timeout, so a slow answer of Telegram no longer holds the whole bot back.
- The `user` row is only written when the user is new.

**Payment gateways**
- The web panel has a full gateway manager: every gateway with its switch, its colour and its settings.
- The colour of a gateway is the colour of its payment button in the bot. The payment key of FrenzyEx is green.
- The second Rial gateway stays off: the admin menu no longer switches it on when it finds an unknown value.
- A fresh installation does not point at the gateway of another installation any more (the FrenzyEx address starts empty).
- FrenzyEx is part of the gateway list of the bot, so it can be switched on and off there as well.

**Panel management (bot)**
- A panel whose name carries a premium emoji can be selected again: the reply keyboard shows the plain text, so the panel is looked up without its emoji tag as well. The panel details and the management keyboard are sent again, and «وضعیت قابلیت‌ها» works.
- The product of a panel is stored with the exact name of the panel, so it shows up in the shop again.

**Web panel**
- The premium emoji of a product, a category and a panel is shown as the picture Telegram serves for it instead of the raw `<tg-emoji>` tag. When an emoji cannot be resolved, nothing is shown — the id is never displayed.
- The agent shop has no inbound or protocol setting any more (its products are not a VPN service) and the administrator receives the details of the buyer and of the ordered product after the payment, so it can be delivered by hand.
- The page «پنل‌های VPN» no longer ends in a fatal error (the colour palette was missing).
- The list of the panels is built from the columns the database really has, so a missing column cannot empty the list any more.
- A new tab «ربات» in the settings: the domain of the bot and the administrator that owns it can be changed there.

**Installer**
- The composer binary is no longer deleted before it is used (that was the reason the one-line installer stopped at 75 %).
- The one-line installer prints the reason when it fails instead of ending silently.
- The exclusion list of the installer used the file name, so every `config.php` was skipped — including `panel/inc/config.php`. A change of the panel bootstrap never reached the servers.
- The mojibake of the progress bar is fixed.
- A fresh installation was missing the columns of the newer features (`product.is_test`, `product.btn_style`, `product.btn_emoji_id`, `setting.alert_balance`, `user.low_balance_alert_sent`); the schema now applies them to a fresh table as well.

**Deployed**
- `212.87.198.95` (arvanbot.snnap.site) — v1.22.70
- `206.1.96.78` (mikhvpn.plussheild.site) — v1.22.70
- `153.52.92.153` (arvanpro.projectsdg.site) — fresh installation, v1.22.70

---

## v1.22.48 (2026-11-01) — One-line Installer Bootstrap

**Changed**
- `install.sh`: Now a true one-line curl installer. When run via `bash <(curl -s .../install.sh)` without the full ZIP extracted, it automatically:
  1. Detects missing PHP and installs it (apt-get / yum / dnf)
  2. Installs unzip, curl, ca-certificates
  3. Downloads and runs `nexora-update.sh` (self-extracting, full ZIP embedded)

**Usage**
- Install/update any Nexora bot with one command:
  `bash <(curl -s https://raw.githubusercontent.com/NexoraShield/Nexora/main/install.sh)`
- Force update:
  `bash <(curl -s https://raw.githubusercontent.com/NexoraShield/Nexora/main/install.sh) --force`

**Includes all previous fixes**
- v1.22.42: Agent panel login redesign (reseller panel theme)
- v1.22.43: Backup directory mismatch (`backups` → `backup`)
- v1.22.44: nexora-update.sh repackage
- v1.22.45: Backup send to group (negative chat_id)
- v1.22.46: Backup PHP parse error fix
- v1.22.47: install.sh one-line wrapper (without PHP auto-install)

---


**Fixed (Critical)**
- `cronbot/backup.php`: Duplicate code block (lines 95-116) from a previous edit caused a **PHP parse error** → the entire backup script failed to run → no backup was ever created or sent. Removed the duplicate block.
- This was the **root cause** of "backup gives error" — the script couldn't even parse, let alone execute.

**Previous fixes (v1.22.43, v1.22.45)**
- Directory mismatch (`backups` → `backup`)
- mysqldump detection + PHP fallback
- gzip compression
- Negative group chat_id support (`> 0` → `!== '0'`)
- Error logging for send failures

---


## v1.22.45 (2026-11-01) — Backup send to group fix

**Fixed**
- `cronbot/backup.php`: `$adminChatId > 0` rejected negative supergroup chat ids → backup never sent to groups. Changed to `!== '0'` and kept as string (supports `-100...` group ids).
- Added `setting['backup_chat_id']` and `setting['Channel_Report']` as fallback targets for backup delivery.
- Telegram send response is now logged to `error_log` when it fails (`backup send failed:`), so the error is visible.
- `idadmin` / admin id now cast to string instead of int, preserving negative group ids.

---


## v1.22.43 (2026-11-01) — Backup Fix

**Fixed**
- `panel/backup.php`: backup directory was `backups` (plural) but `cronbot/backup.php` writes to `backup` (singular) → panel never showed backups. Aligned to `backup`.
- `panel/backup.php`: ensure backup directory exists (mkdir).
- `cronbot/backup.php`: detect `mysqldump` binary (PATH + common paths) instead of assuming it is available; fallback to PHP dump if missing.
- `cronbot/backup.php`: gzip compression when `gzip` is available (reduces size, helps Telegram upload limit).
- `cronbot/backup.php`: cleanup glob now includes both `.sql` and `.sql.gz`.
- Added error logging for backup failures.

---


## v1.22.42 (2026-11-01) — Agent Panel Login Redesign

**Changed**
- `agent-panel/login.php` now uses the same background/theme as the manual sales (reseller) panel login: `reseller-panel.css`, `beauty.css`, `mobile.css`, `data-theme="teal"`, `rs-login-wrap`/`rs-login` glass card.
- Agent panel login and inside panel now visually match the Nexora reseller panel (PasarGuard-style) with Nexora glass theme.

**Fixed**
- Login page no longer uses the old dark card style; now consistent with the rest of the panel UI.


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
