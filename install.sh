#!/usr/bin/env bash
set -Eeuo pipefail

UPDATE_NAME="Nexora Full Update 1.0.0 (all features + Nexora rebrand)"
PACKAGE_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
MANIFEST="$PACKAGE_DIR/manifest.sha256"

TARGET=""
FORCE=0

usage() {
  cat <<'USAGE'
Usage:
  sudo bash install.sh [nexoraBOT_DIRECTORY] [--force]

Examples:
  sudo bash install.sh
  sudo bash install.sh /var/www/html/nexorabot

Only ADDS new files/columns. Never deletes or rewrites your data.
USAGE
}

for arg in "$@"; do
  case "$arg" in
    --force) FORCE=1 ;;
    -h|--help) usage; exit 0 ;;
    -*)
      echo "Unknown option: $arg" >&2
      usage
      exit 2
      ;;
    *)
      if [[ -n "$TARGET" ]]; then
        echo "Only one nexorabot directory may be supplied." >&2
        exit 2
      fi
      TARGET="$arg"
      ;;
  esac
done

echo "==> $UPDATE_NAME"

if [[ ! -f "$MANIFEST" ]]; then
  echo "Update payload is incomplete. Extract the entire ZIP before running install.sh." >&2
  exit 1
fi

if ! command -v sha256sum >/dev/null 2>&1; then
  echo "sha256sum is required." >&2
  exit 1
fi

echo "==> Verifying update files"
(cd "$PACKAGE_DIR" && sha256sum -c manifest.sha256)

if [[ -z "$TARGET" ]]; then
  for candidate in \
    /var/www/html/nexorabot \
    /var/www/html/nexorabotconfig \
    /var/www/nexorabot \
    /var/www/html/bot \
    /var/www/html/nexorabotconfig
  do
    if [[ -f "$candidate/function.php" && -f "$candidate/index.php" && -f "$candidate/config.php" && -f "$candidate/keyboard.php" && -f "$candidate/botapi.php" ]]; then
      TARGET="$candidate"
      break
    fi
  done
fi

if [[ -z "$TARGET" ]]; then
  echo "==> No existing nexorabot found — creating fresh installation"
  TARGET="/var/www/html/nexorabot"
  mkdir -p "$TARGET"
  echo "    Installing to $TARGET"
  cd "$PACKAGE_DIR"
  for f in $(find . -type f -not -name 'install.sh' -not -name 'migrate.php' -not -name 'uninstall.sh' -not -name 'manifest.sha256' -not -name 'README.md' -not -name 'patch_*.php'); do
    rel="${f#./}"
    mkdir -p "$TARGET/$(dirname "$rel")"
    cp -- "$PACKAGE_DIR/$rel" "$TARGET/$rel"
  done
  cd "$TARGET"
  echo "    Fresh installation created at $TARGET"
fi

TARGET="$(cd -- "$TARGET" 2>/dev/null && pwd)" || {
  echo "Target directory does not exist: $TARGET" >&2
  exit 1
}

for required in function.php index.php keyboard.php table.php config.php botapi.php; do
  if [[ ! -f "$TARGET/$required" ]]; then
    echo "This does not look like a nexorabot installation: missing $required" >&2
    exit 1
  fi
done

if [[ ! -w "$TARGET/keyboard.php" ]]; then
  echo "The installation is not writable. Run this installer with sudo." >&2
  exit 1
fi

if ! command -v php >/dev/null 2>&1; then
  echo "PHP CLI is required to install the update." >&2
  exit 1
fi
PHP_BIN="$(command -v php)"

echo "==> Checking PHP syntax"
"$PHP_BIN" -l "$PACKAGE_DIR/nexora_style.php" >/dev/null
"$PHP_BIN" -l "$PACKAGE_DIR/migrate.php" >/dev/null
"$PHP_BIN" -l "$PACKAGE_DIR/patch_keyboard.php" >/dev/null
"$PHP_BIN" -l "$PACKAGE_DIR/patch_panel.php" >/dev/null
"$PHP_BIN" -l "$PACKAGE_DIR/patch_botapi.php" >/dev/null
"$PHP_BIN" -l "$PACKAGE_DIR/patch_bottext.php" >/dev/null
"$PHP_BIN" -l "$PACKAGE_DIR/patch_admin.php" >/dev/null
"$PHP_BIN" -l "$PACKAGE_DIR/patch_miniapp.php" >/dev/null
"$PHP_BIN" -l "$PACKAGE_DIR/panel/btnstyle.php" >/dev/null
"$PHP_BIN" -l "$PACKAGE_DIR/panel/textemojis.php" >/dev/null
"$PHP_BIN" -l "$PACKAGE_DIR/panel/emojis.php" >/dev/null
"$PHP_BIN" -l "$PACKAGE_DIR/panel/inc/emoji_picker_bar.php" >/dev/null
"$PHP_BIN" -l "$PACKAGE_DIR/panel/panels.php" >/dev/null
"$PHP_BIN" -l "$PACKAGE_DIR/panel/health.php" >/dev/null
"$PHP_BIN" -l "$PACKAGE_DIR/panel/export.php" >/dev/null
"$PHP_BIN" -l "$PACKAGE_DIR/agent-panel/login.php" >/dev/null
"$PHP_BIN" -l "$PACKAGE_DIR/agent-panel/index.php" >/dev/null
"$PHP_BIN" -l "$PACKAGE_DIR/agent-panel/inc/auth.php" >/dev/null
"$PHP_BIN" -l "$PACKAGE_DIR/cronbot/nudge_unpaid.php" >/dev/null
"$PHP_BIN" -l "$PACKAGE_DIR/cronbot/frenzyex.php" >/dev/null
"$PHP_BIN" -l "$PACKAGE_DIR/tools/nexora-indexes.php" >/dev/null
"$PHP_BIN" -l "$PACKAGE_DIR/payment/frenzyex.php" >/dev/null

timestamp="$(date +%Y%m%d-%H%M%S)"
if [[ "${EUID:-$(id -u)}" -eq 0 ]]; then
  backup_root="${nexoraBOT_BACKUP_DIR:-/var/backups/nexorabot}"
else
  backup_root="${nexoraBOT_BACKUP_DIR:-$HOME/nexorabot_backups}"
fi
BACKUP_DIR="$backup_root/nexora-$timestamp"
mkdir -p "$BACKUP_DIR"
chmod 700 "$backup_root" "$BACKUP_DIR" 2>/dev/null || true

# ---- upgrade: if a previous version exists, remove it first ----
if [[ -f "$TARGET/nexora_style.php" ]]; then
  echo "==> Previous version detected — removing it first"
  prev_backup=""
  if [[ -d "$backup_root" ]]; then
    prev_backup="$(ls -td "$backup_root"/nexora-* 2>/dev/null | head -1 || true)"
  fi
  if [[ -n "$prev_backup" && -d "$prev_backup" ]]; then
    [[ -f "$prev_backup/keyboard.php" ]] && cp -a -- "$prev_backup/keyboard.php" "$TARGET/keyboard.php"
    [[ -f "$prev_backup/layout_head.php" ]] && cp -a -- "$prev_backup/layout_head.php" "$TARGET/panel/inc/layout_head.php"
    [[ -f "$prev_backup/botapi.php" ]] && cp -a -- "$prev_backup/botapi.php" "$TARGET/botapi.php"
    [[ -f "$prev_backup/bottext.php" ]] && cp -a -- "$prev_backup/bottext.php" "$TARGET/panel/bottext.php"
    [[ -f "$prev_backup/admin.php" ]] && cp -a -- "$prev_backup/admin.php" "$TARGET/admin.php"
    [[ -f "$prev_backup/miniapp-index.php" ]] && cp -a -- "$prev_backup/miniapp-index.php" "$TARGET/app/index.php"
    for _rb in lang/fa.php lang/en.php lang/ru.php lang/zh.php install/checks.php install/index.php api/index.html composer.json; do
      if [[ -f "$prev_backup/$_rb" ]]; then
        cp -a -- "$prev_backup/$_rb" "$TARGET/$_rb"
      fi
    done
    echo "    stock files restored from $prev_backup"
  fi
  for _f in nexora_style.php panel/btnstyle.php panel/textemojis.php panel/emojis.php panel/panels.php panel/health.php panel/export.php panel/inc/emoji_picker_bar.php panel/js/emoji-insert.js panel/css/nexora-glass.css agent-panel/login.php agent-panel/index.php agent-panel/logout.php agent-panel/inc/auth.php cronbot/nudge_unpaid.php cronbot/frenzyex.php tools/nexora-indexes.php app/assets/nexora-theme.css payment/frenzyex.php; do
    rm -f -- "$TARGET/$_f"
  done
  echo "    old files removed — fresh install follows"
fi

echo "==> Backing up patched files to $BACKUP_DIR"
for _f in keyboard.php panel/inc/layout_head.php botapi.php panel/bottext.php admin.php app/index.php; do
  if [[ -f "$TARGET/$_f" ]]; then
    mkdir -p "$BACKUP_DIR/$(dirname "$_f")"
    cp -a -- "$TARGET/$_f" "$BACKUP_DIR/$_f"
  fi
done
for _rb in lang/fa.php lang/en.php lang/ru.php lang/zh.php install/checks.php install/index.php api/index.html composer.json; do
  if [[ -f "$TARGET/$_rb" ]]; then
    mkdir -p "$BACKUP_DIR/$(dirname "$_rb")"
    cp -a -- "$TARGET/$_rb" "$BACKUP_DIR/$_rb"
  fi
done

echo "==> Installing new files"
mkdir -p "$TARGET/panel" "$TARGET/panel/inc" "$TARGET/panel/js" "$TARGET/panel/css" "$TARGET/agent-panel/inc" "$TARGET/cronbot" "$TARGET/tools" "$TARGET/app/assets" "$TARGET/payment"
cp -- "$PACKAGE_DIR/nexora_style.php" "$TARGET/nexora_style.php"
cp -- "$PACKAGE_DIR/panel/btnstyle.php" "$TARGET/panel/btnstyle.php"
cp -- "$PACKAGE_DIR/panel/textemojis.php" "$TARGET/panel/textemojis.php"
cp -- "$PACKAGE_DIR/panel/emojis.php" "$TARGET/panel/emojis.php"
cp -- "$PACKAGE_DIR/panel/panels.php" "$TARGET/panel/panels.php"
cp -- "$PACKAGE_DIR/panel/health.php" "$TARGET/panel/health.php"
cp -- "$PACKAGE_DIR/panel/export.php" "$TARGET/panel/export.php"
cp -- "$PACKAGE_DIR/panel/inc/emoji_picker_bar.php" "$TARGET/panel/inc/emoji_picker_bar.php"
cp -- "$PACKAGE_DIR/panel/js/emoji-insert.js" "$TARGET/panel/js/emoji-insert.js"
cp -- "$PACKAGE_DIR/panel/css/nexora-glass.css" "$TARGET/panel/css/nexora-glass.css"
cp -- "$PACKAGE_DIR/agent-panel/login.php" "$TARGET/agent-panel/login.php"
cp -- "$PACKAGE_DIR/agent-panel/index.php" "$TARGET/agent-panel/index.php"
cp -- "$PACKAGE_DIR/agent-panel/logout.php" "$TARGET/agent-panel/logout.php"
cp -- "$PACKAGE_DIR/agent-panel/inc/auth.php" "$TARGET/agent-panel/inc/auth.php"
cp -- "$PACKAGE_DIR/cronbot/nudge_unpaid.php" "$TARGET/cronbot/nudge_unpaid.php"
cp -- "$PACKAGE_DIR/cronbot/frenzyex.php" "$TARGET/cronbot/frenzyex.php"
cp -- "$PACKAGE_DIR/tools/nexora-indexes.php" "$TARGET/tools/nexora-indexes.php"
cp -- "$PACKAGE_DIR/app/assets/nexora-theme.css" "$TARGET/app/assets/nexora-theme.css"
cp -- "$PACKAGE_DIR/payment/frenzyex.php" "$TARGET/payment/frenzyex.php"

if [[ -f "$TARGET/panel/product.php" ]]; then
  for _f in btnstyle.php textemojis.php emojis.php panels.php health.php export.php; do
    chmod --reference="$TARGET/panel/product.php" "$TARGET/panel/$_f" 2>/dev/null || chmod 0644 "$TARGET/panel/$_f"
    chown --reference="$TARGET/panel/product.php" "$TARGET/panel/$_f" 2>/dev/null || true
  done
fi

_skipped_patches=""
for _patch in patch_keyboard patch_panel patch_botapi patch_bottext patch_admin patch_miniapp; do
  echo "==> Patching with ${_patch} (dry run)"
  if "$PHP_BIN" "$PACKAGE_DIR/${_patch}.php" "$TARGET"; then
    echo "==> Patching with ${_patch} (apply)"
    "$PHP_BIN" "$PACKAGE_DIR/${_patch}.php" "$TARGET" --apply
  else
    echo "WARNING: ${_patch} skipped for this nexorabot version."
    _skipped_patches="$_skipped_patches $_patch"
    if [ "$_patch" = "patch_keyboard" ]; then
      echo "FATAL: core keyboard patch failed — aborting, nothing was changed." >&2
      exit 1
    fi
  fi
done

echo "==> Checking patched syntax"
"$PHP_BIN" -l "$TARGET/keyboard.php" >/dev/null
"$PHP_BIN" -l "$TARGET/panel/inc/layout_head.php" >/dev/null
"$PHP_BIN" -l "$TARGET/botapi.php" >/dev/null
"$PHP_BIN" -l "$TARGET/panel/bottext.php" >/dev/null
"$PHP_BIN" -l "$TARGET/admin.php" >/dev/null
"$PHP_BIN" -l "$TARGET/app/index.php" >/dev/null

echo "==> Running database migration (additive only)"
"$PHP_BIN" "$PACKAGE_DIR/migrate.php" "$TARGET"

echo "==> Applying Nexora display rebrand (exact-match only, safe)"
"$PHP_BIN" "$PACKAGE_DIR/rebrand.php" "$TARGET" --apply || echo "WARNING: rebrand step had skips (see above)."

if command -v restorecon >/dev/null 2>&1; then
  restorecon -R "$TARGET" >/dev/null 2>&1 || true
fi

if command -v php >/dev/null 2>&1; then
  php -r 'if (function_exists("opcache_reset")) { @opcache_reset(); echo "opcache reset\n"; }' 2>/dev/null || true
fi

echo
echo "==> Verifying installation completeness"
_missing=0
for _f in nexora_style.php keyboard.php index.php admin.php function.php panel/emojis.php panel/magic.php agent-panel/inc/auth.php migrate.php; do
  if [[ ! -f "$TARGET/$_f" ]]; then echo "  MISSING FILE: $_f"; _missing=1; fi
done
if [[ $_missing -eq 1 ]]; then echo "ERROR: required files missing." >&2; exit 1; fi
_lint_fail=0
while IFS= read -r -d '' _f; do
  if ! "$PHP_BIN" -l "$_f" >/dev/null 2>&1; then echo "  LINT FAIL: $_f"; _lint_fail=1; fi
done < <(find "$TARGET" -type f -name '*.php' -print0)
if [[ $_lint_fail -eq 1 ]]; then echo "ERROR: PHP syntax errors found in installed files." >&2; exit 1; fi
echo "    All required files present; all PHP files syntactically valid."

if [[ -n "$_skipped_patches" ]]; then
  echo
  echo "WARNING: the following patches were SKIPPED (some features may be missing):$_skipped_patches"
  echo "         Review the warnings above. Re-run the update or apply the patches manually."
  echo
  echo "Update installed PARTIALLY. See warnings above."
else
  echo
  echo "Update installed successfully."
fi
echo "Backup: $BACKUP_DIR"
echo
echo "Next:"
echo "  1. Open your panel: https://YOUR-DOMAIN/panel/emojis.php"
echo "     Add emojis manually, or send in bot: /savemoji <name> + premium emoji"
echo "  2. Open your panel: https://YOUR-DOMAIN/panel/btnstyle.php"
echo "     Set a color (blue/green/red) and premium emoji per product, category and panel."
echo "  3. Open your panel: https://YOUR-DOMAIN/panel/panels.php"
echo "     Edit panels (status, access, limit, color, emoji)."
echo "  4. Open your panel: https://YOUR-DOMAIN/panel/bottext.php"
echo "     Use the emoji picker to put premium emoji in ANY bot text."
echo "  5. Open your panel: https://YOUR-DOMAIN/panel/textemojis.php"
echo "     Set premium emoji for each main-menu button."
echo "  6. Agent panel: https://YOUR-DOMAIN/agent-panel/ (set passwords via /agentpass in bot)"
echo "  7. FrenzyEx gateway: Finance -> Payment settings -> FrenzyEx"
echo "  8. Optional speed: php tools/nexora-indexes.php"
echo "     Optional unpaid reminders cron: */30 * * * * php BOTDIR/cronbot/nudge_unpaid.php"
echo "  9. Check the bot and the panel — no restart needed."
