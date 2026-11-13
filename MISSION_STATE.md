# MISSION_STATE — 8h autonomous dev (Nexora Telegram VPN bot)

## Mission
Maximize quality/stability/functionality of the Nexora Telegram VPN sales bot.
Mandatory: Persian (Farsi) is the only user-facing language (RTL preserved).

## Start time
2026-10-29 (session) — Phase 7

## Current phase
Phase 10 — The reseller shop is a manual shop (no panel, no wallet), every answer of the customer
gets a reaction and the bot is faster on a small server.

## Last successful checkpoint
**v1.22.80** — the changes are committed and the release is published:
- The reseller shop (فروشگاه نمایندگی, `agent = 'agentshop'`):
  - the option "پنل" is gone from `panel/agentshop.php` and from the wizard of the bot
    (`shop_admin.php` → `nexora_shop_wizard_stages`), and the products carry no panel any more
    (the migration `024` empties the column `Location` of the products that still had one);
  - no account is created on a panel any more (`index.php`, the branch `agentshop_buy`): the order
    only carries the name of the product and the administrator delivers it by hand;
  - the money of the buyer never touches his wallet: `DirectPayment()` skips the wallet math when
    the note of the order starts with `agentshop:` or `topup:agentshop:` — the difference between
    the paid amount and the price is only reported to the administrator.
- The questions of the customer (`agentshop_admin.php`):
  - `nexora_agent_shop_handle_answer()` is called **before** every other branch of the bot
    (`index.php`, right after the rules and the bot status), so no branch can swallow an answer;
  - every answer gets a reaction (the next question or the confirmation), the text of a question is
    escaped for the HTML mode of Telegram;
  - a button of the menu or a command of the bot is not an answer: the order goes to the
    administrator with the answers that were given, so a paid order is never lost;
  - the report of the administrator carries a link to the customer.
- The agent panel (اجنت پنل):
  - `nexora_agent_panel_deliver()` creates the table `agent_auth` before the access is written, so
    the delivery stays automatic on a database that was never migrated;
  - `nx_agent_buy_product()` refuses a manual panel with a clear message and uses the other panels
    of the bot when the panel of the product can not create the account (the amount is refunded when
    no panel can do it).
- The speed of the bot:
  - `install.sh` writes the settings of the opcode cache (opcache) for every installed PHP version
    (the jit is off, the realpath cache is on);
  - `botapi.php` keeps the connection to Telegram open inside a request;
  - the settings of the shop and of the payments are read from the file cache
    (`nexora_table_file_cache()`), and a change in the web panel drops the cache at once
    (`nexora_clear_table_cache()`).
- Full-repo php -l (PHP 8.2.35 CLI): 238 files clean / 0 errors.
- The flow of the questions is covered by a test (16 checks, all green).
- v1.22.79 (committed): the payment section of the web application and the luxury theme.

## Phase 8 results (2026-11-13)
- **Test quota.** `setting.limit_usertest_all` is the share of every user (every user has a numeric
  telegram id). The bot tab "سرویس تست" shows the share, the number of users and a button to
  change it (with "apply to the existing users"). The customer list shows the remaining share;
  when it is 0 the bot answers with "⛔️ شما حد نصاب سرویس تست را گرفتید." (lang fa/en/ru/zh:
  `users.usertest.limitwarning`, `users.testService.remaining`).
- **Test service = a real account.** `testproduct_get_*` creates the account with
  `ManagePanel::createUser()` exactly like a product; only the volume and the duration of the
  test product are smaller. `is_test` forces the account to be active right away.
- **Agent panel (اجنت پنل).** Ten default packages ("پنل اجنت نمایندگی با ۱..۱۰ میلیون اعتبار",
  price = credit) are seeded by migration 022 and by `nexora_self_heal()`; a marker in PaySetting
  (`nx_seeded_agent_panel_products`) keeps them from coming back after the administrator deleted
  them on purpose. The same marker was added to migration 018 for the top up options.
- **Delivery.** After the payment the customer gets the user name, the password and the link of
  the panel, the report goes to the report channel and to every administrator. If the product was
  deleted after the payment, or the access could not be written, the customer and the admins are
  warned instead of the order falling through to the VPN delivery path.
- **Top up.** `nexora_topup_targets()` returns a list of names of the products the customer really
  bought (per shop). The "no panel yet" message and the buy button are shop specific.
  The agent panel top up adds the credit automatically and reports the new balance.
- **Agent shop (پنل نمایندگی).** The questions of the shop are asked after the payment, the
  answers reach the administrator together with the payment details, and the customer gets the
  24 hour message.
- **Reseller panel.** The prices of the bot are 10% below the bot price (was 15%). The access is
  bound to the `agent_auth` row, the session id is regenerated after the login.
- **The panels that were delivered.** The web panel (مدیریت نمایندگان → پنل‌های تحویل‌شده) lists every
  panel that was delivered: the user name of the panel, the customer, the package the customer bought, the
  credit of the panel, the login link and the date. The credit can be increased and decreased by hand (the
  balance never goes below zero), a new password can be built (it is shown once, only the hash is stored)
  and the panel can be deleted (the access of the customer is taken away, the payments stay untouched).
- Documentation: `AGENT_PANEL.fa.md`.

## Completed changes
- v1.2.0  Unified bot editor (text+color+emoji) for all keys
- v1.3.0  Luxury glass theme + auto-backup cron + payment reminder cron
- v1.3.1  Bug fixes: sendmessage sig, double layout_head
- v1.4.0  Central color manager page
- v1.5.0  Magic auto-repair page
- v1.6.0  Extended color palette (10 colors)
- v1.7.0  Audit Log panel
- v1.8.0  Bug review + stability + migration guide
- v1.9.0  Maintenance mode panel
- v1.9.1  Agent panel login fix
- v1.9.2 (f565262): wired nx_bb_* color+emoji into bot runtime (keyboard.php)
- v1.9.3 (7fb8d4d): agent-login brute-force throttle (5 attempts / 300s, DB counter, reset on success)
- v1.9.4 (bc10789): fixed unclosed braces / BOM / undefined-constant ?? in agent-panel auth.php+login.php
- v1.9.5 (00edfa4): FIXED nx_bb_* color/emoji on emoji-prefixed menu buttons (prefix-match fallback in nexora_apply_bb_style) + refreshed MISSION_STATE
- v1.9.6 (bf39526): prevented double-credit on re-entrant DirectPayment — already-settled order now returns without addBalance (was re-crediting); php -l clean.

## P0/P1 audit findings
- nexora_apply_menu_emoji DOES exist (nexora_style.php:406); wired in keyboard.php
  at both branches (~123 & ~141). Runtime order: applyKeyboardLabels ->
  nexora_apply_menu_emoji -> nexora_apply_bb_style.
- nexora_apply_bb_style exact flip-lookup was the v1.9.2 defect; patched in v1.9.5.
- Agent-panel auth.php (42/42 braces), login.php (32/32 braces), BOM stripped,
  constants guarded with defined() ? : default -> v1.9.4.

## Resolved (P1) — payment integrity
- Payment callback idempotency: VERIFIED. Every gateway callback (nowpayment.php,
  variza_webhook.php, zarinpal.php, iranpay1/2/4, aqayepardakht.php, plisio.php,
  Telegram inline index.php:101) gates DirectPayment() behind claimPaymentPaid()
  (conditional `UPDATE ... WHERE payment_Status <> 'paid'`). DirectPayment itself
  also refuses to re-credit on an already-settled order (v1.9.6).
- Agent invoice -> subscription -> delivery traced via DirectPayment()
  (function.php:1031+): load Payment_report+user -> paymentOrder link -> wallet
  math (addBalance/deductBalance) -> reset Processing_value -> marzban service
  creation -> sendMessageService delivery -> invoice update + discount code
  consumption + channel report.

## Outstanding (P2) — deferred / low-risk
- invoice.Status vs status casing inconsistency (cosmetic on MySQL case-insensitive
  columns; not changed to avoid gratuitous churn).
- RELEASE_NOTES.md missing v1.8.0–v1.9.4 entries (v1.9.5 backfilled).

## Test results
- PHP 8.x CLI at %TEMP%\php_lint\php\php.exe (note: inside a `php` subfolder).
- Lint OK: nexora_style.php, keyboard.php, function.php, agent-panel/inc/auth.php,
  agent-panel/login.php, agent-panel/index.php.
- Regression (runtime color/emoji wiring): 6/6 cases pass (exact, emoji-prefixed,
  unknown-label, inline, plain, callback). Scratch test removed after verification.
- No PHPUnit / test framework in repo.

## Current Git commit
bf39526 (v1.9.6 tip) — origin/main + tag v1.9.6 pushed.

## Phase 7 continued — P1/P2/P4/P5/P6 results (2026-10-29)
**P1 — Agent invoice path traced end-to-end.**
- agent-panel/ (web) = read-only affiliate dashboard (subs where affiliates=agent_id, totalBuy, activeServices, commission). No invoice creation.
- Real agent/customer purchase flow lives in the Telegram bot: typeagent- (admin.php:925) -> locationmessage_ (966) panel select -> product select -> INSERT IGNORE INTO invoice (Status) at admin.php:4961 (manual/admin order, Status='active', immediate Marzban createUser) and index.php:3110/4061/4715 (customer self/affiliate purchase, Status='unpaid' initially). linkPaymentToOrder (function.php:1004) wires invoice.id_payment = order_id when step=get_step_payment_buy. Gateway Payment_report write (payment_Status='Unpaid') -> webhook/Confirm_pay_ (1696) -> claimPaymentPaid -> DirectPayment -> UPDATE invoice SET Status='active' (1117, delivery claim) -> $ManagePanel->createUser + sendMessageService delivers configs to invoice.id_user (customer). Agent commission credited via cashback block.
**P2 — Status casing audit.**
- invoice.Status column (capital S) is canonical per all INSERT sites (admin.php:4961, index.php:3110/4061/4715) and function.php reads ($get_invoice['Status']). MySQL column refs are case-insensitive, so no runtime breakage; PHP keyed reads use uppercase 'Status' consistently. Normalized admin.php:3785 (removebyadmin flow) 'status'->'Status'. cronbot/configtest.php:10/20 still use lowercase 'status' in raw SQL (mixed with 'Status' in the same WHERE at disableconfig.php:15) — cosmetic, functionally safe (Marzban DataUser 'status' read is the API's lowercase key, not the invoice column). Flagged for a follow-up cleanup pass.
- payment_Status canonical values: 'Unpaid' (capital, pending across gateways), 'waiting' (croncard/aban), 'pending' (frenzyex), 'paid'/'reject'/'expire' (final). admin.php:1720 NOT IN ('paid','Unpaid','expire','reject') correctly excludes the pending set. No casing breakage.
- claimPaymentPaid (function.php:1021) guard was `<> 'paid'` (looser than Confirm_pay's `NOT IN ('paid','reject')`). Aligned to `NOT IN ('paid','reject')` so a stale gateway webhook cannot re-claim an admin-rejected payment. Defense-in-depth; downstream guards already prevent double delivery.
**P4 — Full-repo php -l sweep.** Baseline: 105 clean / 0 errors (root + agent-panel + payment + cronbot + panel + api). Fixed the single parse error: panel/emoji_picker.php (unclosed `if (!empty($emoji_map)):` -> missing endif). Also fixed stale include mirza_style.php -> nexora_style.php, <value> -> <option> HTML typos, $pdo->query() misuse -> prepare/execute, and added the missing nexora_emoji_map table to nexora_ensure_emoji_table().
**P5 — Confirm_pay_ flow audit (admin.php:1696-1753).** Role guard admin||Seller present; atomic claim `UPDATE Payment_report SET payment_Status='paid' WHERE id_order=? AND payment_Status NOT IN ('paid','reject')` is the concurrency lock; DirectPayment gated by claim rowCount; cashback/balance credit only on the winning claim. TOCTOU under single admin is low-risk. No code change required beyond P2 alignment.
**P3 — GitHub Release object for v1.9.6.** BLOCKED: `gh` CLI is not installed in this environment; cannot create the release object from here. Compiled changelog is ready in RELEASE_NOTES.md (v1.9.6 + v1.9.7). User can run `gh release create v1.9.6 --notes-file ...` (or use GitHub web UI) once gh is available.
**v1.9.7 plan:** commit the above hardening edits; tag v1.9.7; push. Then continue with cronbot casing normalization + agent-panel nav link for emoji_picker + deeper feature work.

## Update mechanism hardening (2026-10-29)
- **Self-heal on boot** (`nexora_style.php`): added `nexora_self_heal()` that runs once per PHP process and ensures all schema (style/emoji columns on product/category/marzban_panel, bot_custom_emoji + nexora_emoji_map tables, agent_auth + agent_login_attempts, PaySetting defaults) plus a guarded `opcache_reset()`. Runs automatically whenever nexora_style.php is loaded (keyboard.php requires it early in index.php), so even if migrate.php was not run or a table is missing, the bot self-heals on first request. Directly addresses "some features don't get added after update."
- **migrate.php**: now also ensures agent_login_attempts table (was only created lazily by agent-panel login).
- **install.sh**: patch loop now tracks skipped patches (`_skipped_patches`) and prints a clear WARNING listing them; final status is "PARTIALLY" vs "successfully" based on skips. Added post-install verification: checks all required files exist + runs `php -l` on EVERY installed PHP file (not just a subset); aborts with clear error on missing files or syntax errors. OPcache reset already present at end of install.

## Releases
## v1.9.9 — Nexora CLI + progress-bar update (2026-10-29)
- Added `tools/nexora`: a stylish bash CLI menu (Update Bot, Update PHP, Status, Logs, Restart, Version, Magic, Exit) installed to `/usr/local/bin/nexora` by install.sh. Typing `nexora` in SSH now shows a boxed, colored menu.
- `nexora update` runs the update with a single 0-100% progress bar instead of many messages: install.sh now emits `NEXORA_PROGRESS:N` markers at key phases (verify 5, lint 10, backup 20, remove-old 30, install-files 45, patches 60, migration 75, rebrand 85, opcache 92, verify 98, done 100); the CLI parses them and renders one bar.
- install.sh now installs the `nexora` CLI and (already) runs migrate.php + clears OPcache + verifies all files/lint.

## Releases
v0.9.1 … v1.9.9 (all tagged + pushed to origin).
v0.9.1 … v1.9.8 (all tagged + pushed to origin).
