# MISSION_STATE — 8h autonomous dev (Nexora Telegram VPN bot)

## Mission
Maximize quality/stability/functionality of the Nexora Telegram VPN sales bot.
Mandatory: Persian (Farsi) is the only user-facing language (RTL preserved).

## Start time
2026-10-29 (session) — Phase 7

## Current phase
Phase 7 — Security hardening + regression fixes + runtime color/emoji wiring audit + payment integrity (P0–P2).

## Last successful checkpoint
**v1.9.6 (bf39526)** — committed + pushed to origin/main + tag v1.9.6 pushed. (v1.9.5 color/emoji prefix-match fallback also committed/tagged/pushed.)
Fix: nx_bb_* color/emoji now resolve for menu buttons whose label was prefixed
with an emoji glyph by nexora_apply_menu_emoji (tolerant prefix-match fallback
in nexora_apply_bb_style). php -l clean; regression test: 6/6 cases pass.

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

## Releases
v0.9.1 … v1.9.6 (all tagged + pushed to origin).
