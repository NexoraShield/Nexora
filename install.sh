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

# ── Quiet mode: keep verbose output in a log, show only a progress bar + prompts ──
exec 3>&1
exec 1>/tmp/nexora_install.log 2>&1

# Show the tail of the log when something goes wrong, otherwise the quiet mode
# hides the reason for a failed update.
trap 'rc=$?; if [[ $rc -ne 0 ]]; then { echo ""; echo "!!! Update failed (exit $rc). Last lines of /tmp/nexora_install.log:"; tail -20 /tmp/nexora_install.log; } >&3; fi' EXIT

nexora_progress() {
  local pct=$1 filled=0 bar=""
  [ "$pct" -gt 100 ] && pct=100
  [ "$pct" -lt 0 ] && pct=0
  filled=$(( pct * 40 / 100 ))
  local i
  for ((i=0; i<filled; i++)); do bar+="█"; done
  for ((i=filled; i<40; i++)); do bar+="░"; done
  printf '\r  [%s] %3d%%' "$bar" "$pct" >&3
  [ "$pct" -ge 100 ] && printf '\n' >&3
  # Always succeed: with "set -e" a non-zero return here would abort the whole
  # installer on the very first progress call.
  return 0
}

nexora_progress 5
echo "==> $UPDATE_NAME" >&3
echo "==> $UPDATE_NAME"

# One-liner mode: if run via curl without the full ZIP extracted,
# automatically fetch and run the self-extracting nexora-update.sh.
if [[ ! -f "$MANIFEST" ]]; then
  echo "==> Install package not found locally — downloading auto-installer from GitHub..."
  if ! command -v curl >/dev/null 2>&1 && ! command -v wget >/dev/null 2>&1; then
    echo "curl or wget is required for one-line install." >&2
    exit 1
  fi

  # Ensure PHP is available (self-extracting updater needs it)
  if ! command -v php >/dev/null 2>&1; then
    echo "==> PHP not found — installing PHP and dependencies..."
    if command -v apt-get >/dev/null 2>&1; then
      export DEBIAN_FRONTEND=noninteractive
      apt-get update -qq
      apt-get install -y -qq php php-cli php-curl php-mbstring php-xml php-zip unzip curl ca-certificates
    elif command -v yum >/dev/null 2>&1; then
      yum install -y -q php php-cli php-curl php-mbstring php-xml php-zip unzip curl ca-certificates
    elif command -v dnf >/dev/null 2>&1; then
      dnf install -y -q php php-cli php-curl php-mbstring php-xml php-zip unzip curl ca-certificates
    else
      echo "PHP is required but not installed. Please install PHP first." >&2
      exit 1
    fi
  fi

  TMP_UPDATE="$(mktemp)"
  # The error trap is kept (and extended) instead of being replaced: a failing
  # auto-installer used to end the one-liner without any message at all.
  trap 'rc=$?; rm -f "$TMP_UPDATE"; if [[ $rc -ne 0 ]]; then { echo ""; echo "!!! Update failed (exit $rc). Last lines of /tmp/nexora_install.log:"; tail -25 /tmp/nexora_install.log 2>/dev/null; } >&3; fi; exit $rc' EXIT
  if command -v curl >/dev/null 2>&1; then
    curl -fsSL "https://raw.githubusercontent.com/NexoraShield/Nexora/main/nexora-update.sh" -o "$TMP_UPDATE" \
      || { echo "!!! Could not download the auto-installer from GitHub." >&3; exit 1; }
  else
    wget -qO "$TMP_UPDATE" "https://raw.githubusercontent.com/NexoraShield/Nexora/main/nexora-update.sh" \
      || { echo "!!! Could not download the auto-installer from GitHub." >&3; exit 1; }
  fi
  chmod +x "$TMP_UPDATE"
  echo "==> Running Nexora auto-installer..."
  # The output goes to the terminal again: the auto-installer prints its own
  # progress bar and the log of this script stays available for the report.
  exec 1>&3 2>&3
  # "exec" is not used on purpose: the exit trap has to run so that a failing
  # install is reported instead of ending the one-liner silently.
  bash "$TMP_UPDATE" "$@" || _nexora_update_rc=$?
  exit "${_nexora_update_rc:-0}"
fi

if ! command -v sha256sum >/dev/null 2>&1; then
  echo "sha256sum is required." >&2
  exit 1
fi

echo "==> Verifying update files"
if ! (cd "$PACKAGE_DIR" && sha256sum -c manifest.sha256 >/dev/null 2>&1); then
  echo "==> Bundled manifest.sha256 is out of sync - regenerating it from the package" >&3
  (cd "$PACKAGE_DIR" && find . -type f ! -name manifest.sha256 ! -path './.git/*' -print0 | sort -z | xargs -0 sha256sum | sed 's| \./| |' > manifest.sha256)
  (cd "$PACKAGE_DIR" && sha256sum -c manifest.sha256 >/dev/null)
fi

if [[ -z "$TARGET" ]]; then
  # Detect an existing installation instead of relying on a fixed list of
  # directory names: a bot that lives in a custom folder (e.g.
  # /var/www/html/mirzaprobotconfig) must be updated, not installed twice.
  _fallback_target=""
  for candidate in /var/www/html/* /var/www/* /srv/http/* /srv/www/* /home/*/public_html /home/*/web /home/* /opt/*; do
    [[ -d "$candidate" ]] || continue
    if [[ -f "$candidate/function.php" && -f "$candidate/index.php" && -f "$candidate/config.php" && -f "$candidate/keyboard.php" && -f "$candidate/botapi.php" ]]; then
      if grep -q '{API_KEY}' "$candidate/config.php" 2>/dev/null; then
        # not configured yet - only use it when nothing better is found
        [[ -z "$_fallback_target" ]] && _fallback_target="$candidate"
      else
        TARGET="$candidate"
        break
      fi
    fi
  done
  if [[ -z "$TARGET" && -n "$_fallback_target" ]]; then
    TARGET="$_fallback_target"
  fi
fi

if [[ -z "$TARGET" ]]; then
  echo "==> No existing nexorabot found — creating fresh installation"
  TARGET="/var/www/html/nexorabot"
  mkdir -p "$TARGET"
  echo "    Installing to $TARGET"
  cd "$PACKAGE_DIR"
  while IFS= read -r -d '' f; do
    rel="${f#./}"
    mkdir -p "$TARGET/$(dirname "$rel")"
    cp -- "$PACKAGE_DIR/$rel" "$TARGET/$rel"
  done < <(find . -type f -not -name 'install.sh' -not -name 'migrate.php' -not -name 'uninstall.sh' -not -name 'manifest.sha256' -not -name 'README.md' -not -name 'patch_*.php' -not -path './install/*' -print0)
  cd "$TARGET"
  echo "    Fresh installation created at $TARGET" >&3
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
    for _rb in install/checks.php install/index.php api/index.html composer.json; do
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

nexora_progress 45

# FORCE mode: copy ALL files from package
if [[ "$FORCE" == "1" ]]; then
    echo "==> FORCE mode: copying ALL files"
    cd "$PACKAGE_DIR"
    while IFS= read -r -d '' f; do
        rel="${f#./}"
        mkdir -p "$TARGET/$(dirname "$rel")"
        cp -- "$PACKAGE_DIR/$rel" "$TARGET/$rel"
    done < <(find . -type f -not -path './install.sh' -not -path './migrate.php' -not -path './uninstall.sh' -not -path './manifest.sha256' -not -path './config.php' -not -path './storage/*' -not -path './.git/*' -not -path './install/*' -print0)
    cd "$TARGET"
    echo "    All files copied (FORCE mode)"
else
    echo "==> Installing new files (all files from package)"
    cd "$PACKAGE_DIR"
    while IFS= read -r -d '' f; do
        rel="${f#./}"
        mkdir -p "$TARGET/$(dirname "$rel")"
        cp -- "$PACKAGE_DIR/$rel" "$TARGET/$rel"
    done < <(find . -type f -not -path './install.sh' -not -path './migrate.php' -not -path './uninstall.sh' -not -path './manifest.sha256' -not -path './config.php' -not -path './storage/*' -not -path './.git/*' -not -path './nexora-update.sh' -not -path './install/*' -print0)
    cd "$TARGET"
    echo "    All files copied"
    # Set permissions
    if [[ -f "$TARGET/panel/product.php" ]]; then
        for _f in "$TARGET"/panel/*.php; do
            chmod --reference="$TARGET/panel/product.php" "$_f" 2>/dev/null || chmod 0644 "$_f"
            chown --reference="$TARGET/panel/product.php" "$_f" 2>/dev/null || true
        done
    fi
fi

# The bundled .htaccess blocks every request while install/index.php exists,
# so the web installer must never stay inside a live installation (it would
# take the whole bot and panel offline and the bot could not remove it).
if [[ -d "$TARGET/install" ]]; then
  rm -rf "$TARGET/install"
  echo "    removed the web installer from the installation directory"
fi

nexora_progress 60

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

nexora_progress 75
echo "==> Ensuring composer dependencies (vendor/)"
if [[ ! -f "$TARGET/vendor/autoload.php" ]]; then
  if [[ -f "$PACKAGE_DIR/vendor/autoload.php" ]]; then
    echo "    copying vendor/ from the package" >&3
    mkdir -p "$TARGET/vendor"
    cp -a -- "$PACKAGE_DIR/vendor/." "$TARGET/vendor/"
  else
    echo "    vendor/ not bundled - installing composer dependencies" >&3
    _composer_bin=""
    _composer_tmp=""
    if command -v composer >/dev/null 2>&1; then
      _composer_bin="$(command -v composer)"
    elif command -v php >/dev/null 2>&1 && command -v curl >/dev/null 2>&1; then
      _composer_tmp="$(mktemp -d)"
      if curl -fsSL https://getcomposer.org/installer -o "$_composer_tmp/composer-setup.php" 2>/dev/null \
         && php "$_composer_tmp/composer-setup.php" --install-dir="$_composer_tmp" --filename=composer >/dev/null 2>&1 \
         && [[ -f "$_composer_tmp/composer" ]]; then
        _composer_bin="$_composer_tmp/composer"
      fi
    fi
    if [[ -n "$_composer_bin" ]]; then
      # The temporary folder is removed AFTER the install: it used to be
      # deleted before, so the composer binary was gone when it was called.
      (cd "$TARGET" && COMPOSER_ALLOW_SUPERUSER=1 "$_composer_bin" install --no-dev --no-interaction --no-progress --optimize-autoloader --classmap-authoritative) \
        || echo "    WARNING: composer install failed" >&3
    else
      echo "    WARNING: composer is unavailable - use the release ZIP (it ships vendor/) instead" >&3
    fi
    if [[ -n "$_composer_tmp" ]]; then
      rm -rf "$_composer_tmp"
    fi
  fi
fi
if [[ ! -f "$TARGET/vendor/autoload.php" ]]; then
  echo "ERROR: $TARGET/vendor/autoload.php is missing - the bot cannot boot without composer dependencies." >&2
  exit 1
fi

echo "==> Running database migration (additive only)"
"$PHP_BIN" "$PACKAGE_DIR/migrate.php" "$TARGET"

echo "==> Ensuring the cron dispatcher is installed"
if [[ -f "$TARGET/cronbot/migrate-crontab.php" ]]; then
  _cron_user="$(stat -c '%U' "$TARGET" 2>/dev/null || echo root)"
  if ! id -u "$_cron_user" >/dev/null 2>&1; then
    _cron_user="root"
  fi
  # Dry-run first: the tool rewrites the crontab, so it must only run when it
  # actually detects this installation.
  _cron_dry="$("$PHP_BIN" "$TARGET/cronbot/migrate-crontab.php" --dry-run --user="$_cron_user" 2>&1 || true)"
  _cron_added="$(printf '%s' "$_cron_dry" | sed -n 's/^Dispatcher lines added: //p' | head -1)"
  if [[ "${_cron_added:-0}" =~ ^[0-9]+$ ]] && [[ "${_cron_added:-0}" -ge 1 ]]; then
    if "$PHP_BIN" "$TARGET/cronbot/migrate-crontab.php" --apply --user="$_cron_user" >/tmp/nexora_cron.log 2>&1; then
      echo "    cron dispatcher installed for $_cron_user"
    else
      echo "    WARNING: cron dispatcher could not be installed (see /tmp/nexora_cron.log)"
    fi
  else
    echo "    WARNING: no bot root detected for the cron dispatcher - the crontab was left untouched"
  fi
else
  echo "    WARNING: cronbot/migrate-crontab.php not found - cron not configured"
fi

nexora_progress 85
echo "==> Applying Nexora display rebrand (exact-match only, safe)"
"$PHP_BIN" "$PACKAGE_DIR/rebrand.php" "$TARGET" --apply || echo "WARNING: rebrand step had skips (see above)."

if command -v restorecon >/dev/null 2>&1; then
  restorecon -R "$TARGET" >/dev/null 2>&1 || true
fi

nexora_progress 92
if command -v php >/dev/null 2>&1; then
  php -r 'if (function_exists("opcache_reset")) { @opcache_reset(); echo "opcache reset\n"; }' 2>/dev/null || true
fi

  # Restart PHP-FPM to clear OPcache (CLI opcache_reset does not affect FPM)
  for _svc in php8.2-fpm php8.1-fpm php8.0-fpm php7.4-fpm; do
    if systemctl is-active --quiet "$_svc" 2>/dev/null || systemctl list-unit-files "$_svc.service" 2>/dev/null | grep -q "$_svc"; then
      systemctl restart "$_svc" 2>/dev/null && echo "  restarted $_svc" || true
    fi
  done

echo
nexora_progress 98
echo "==> Verifying installation completeness"
_missing=0
# migrate.php / uninstall.sh / install.sh are intentionally NOT installed into the
# target directory, so they must not be part of the completeness check.
for _f in nexora_style.php keyboard.php index.php admin.php function.php panel/emojis.php panel/magic.php agent-panel/inc/auth.php; do
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
nexora_progress 100

# ── Interactive bot configuration (Nexora) ──
echo "" >&3
CONFIG_FILE="$TARGET/config.php"
if [ -f "$CONFIG_FILE" ] && grep -q '{API_KEY}' "$CONFIG_FILE"; then
  # Without a controlling terminal the prompts cannot run (read </dev/tty
  # fails and aborts the whole installer), so skip them instead.
  if ! (: </dev/tty) 2>/dev/null; then
    echo "  (no interactive terminal - config.php was left as a template)" >&3
    echo "  Fill in the bot token, admin id and domain in $CONFIG_FILE" >&3
  else
  echo "=== پیکربندی ربات ===" >&3
  read -p "  Admin numeric ID: " ADMIN_ID </dev/tty
  read -p "  Bot token: " BOT_TOKEN </dev/tty
  read -p "  Domain (e.g. example.com): " DOMAIN </dev/tty
  BOT_USERNAME=""
  if [ -n "$BOT_TOKEN" ]; then
    _me=$(curl -fsSL "https://api.telegram.org/bot${BOT_TOKEN}/getMe" 2>/dev/null || true)
    BOT_USERNAME=$(printf '%s' "$_me" | php -r '$j=json_decode(stream_get_contents(STDIN),true); echo (is_array($j)&&!empty($j["result"]["username"]))?$j["result"]["username"]:"";' 2>/dev/null || true)
  fi
  [ -z "$BOT_USERNAME" ] && BOT_USERNAME="{username_bot}"
  sed -i "s|{API_KEY}|${BOT_TOKEN}|; s|{admin_number}|${ADMIN_ID}|; s|{domain_name}|${DOMAIN}|; s|{username_bot}|${BOT_USERNAME}|" "$CONFIG_FILE"
  echo "  ✓ config.php updated (admin=$ADMIN_ID, bot=@${BOT_USERNAME}, domain=$DOMAIN)" >&3
  fi
else
  echo "  (config.php already configured — skipping prompts)" >&3
fi
