#!/usr/bin/env bash
set -Eeuo pipefail
REMOVE_DB=0
TARGET=""
usage() { cat <<'USAGE'
Usage: sudo bash uninstall.sh [nexoraBOT_DIRECTORY] [--db]
USAGE
}
for arg in "$@"; do
  case "$arg" in
    --db) REMOVE_DB=1 ;;
    -h|--help) usage; exit 0 ;;
    -*)
      echo "Unknown option: $arg" >&2; usage; exit 2 ;;
    *)
      if [[ -n "$TARGET" ]]; then echo "Only one nexorabot directory." >&2; exit 2; fi
      TARGET="$arg" ;;
  esac
done
if [[ -z "$TARGET" ]]; then
  for candidate in /var/www/html/nexorabot /var/www/html/nexorabotconfig /var/www/nexorabot /var/www/html/bot /var/www/html/nexorabotconfig; do
    if [[ -f "$candidate/function.php" && -f "$candidate/index.php" && -f "$candidate/config.php" && -f "$candidate/keyboard.php" && -f "$candidate/botapi.php" ]]; then TARGET="$candidate"; break; fi
  done
fi
if [[ -z "$TARGET" ]]; then echo "nexorabot not found. Pass its directory." >&2; usage; exit 1; fi
TARGET="$(cd -- "$TARGET" 2>/dev/null && pwd)" || { echo "Target directory does not exist: $TARGET" >&2; exit 1; }
echo "==> Uninstall from $TARGET"
backup_root="${nexoraBOT_BACKUP_DIR:-/var/backups/nexorabot}"
BACKUP_DIR=""
if [[ -d "$backup_root" ]]; then BACKUP_DIR="$(ls -td "$backup_root"/nexorastyle-* 2>/dev/null | head -1 || true)"; fi
if [[ -n "$BACKUP_DIR" && -d "$BACKUP_DIR" ]]; then
  echo "==> Restoring from backup: $BACKUP_DIR"
  for f in keyboard.php panel/inc/layout_head.php botapi.php panel/bottext.php admin.php app/index.php; do
    if [[ -f "$BACKUP_DIR/$f" ]]; then cp -a -- "$BACKUP_DIR/$f" "$TARGET/$f"; echo "    restored $f"; fi
  done
  for _rb in lang/fa.php lang/en.php lang/ru.php lang/zh.php install/checks.php install/index.php api/index.html composer.json; do
    if [[ -f "$BACKUP_DIR/$_rb" ]]; then cp -a -- "$BACKUP_DIR/$_rb" "$TARGET/$_rb"; echo "    restored $_rb"; fi
  done
else
  echo "    No backup dir found under $backup_root — skipping restore."
fi
echo "==> Removing added files"
for f in nexora_style.php panel/btnstyle.php panel/textemojis.php panel/emojis.php panel/panels.php panel/health.php panel/export.php panel/inc/emoji_picker_bar.php panel/js/emoji-insert.js panel/css/nexora-glass.css agent-panel/login.php agent-panel/index.php agent-panel/logout.php agent-panel/inc/auth.php cronbot/nudge_unpaid.php cronbot/frenzyex.php tools/nexora-indexes.php app/assets/nexora-theme.css payment/frenzyex.php; do
  if [[ -e "$TARGET/$f" ]]; then rm -f -- "$TARGET/$f"; echo "    removed $f"; fi
done
if [[ "$REMOVE_DB" -eq 1 ]]; then
  echo "==> Removing database columns/table"
  if ! command -v php >/dev/null 2>&1; then echo "PHP CLI is required for --db." >&2; exit 1; fi
  php -r '
    $t = $argv[1];
    require $t . "/config.php";
    if (!isset($pdo) || !($pdo instanceof PDO)) { fwrite(STDERR, "No DB connection.\n"); exit(1); }
    foreach (["product", "category", "marzban_panel"] as $table) {
      foreach (["btn_style", "btn_emoji_id"] as $col) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $st->execute([$table, $col]);
        if ((int) $st->fetchColumn() > 0) { $pdo->exec("ALTER TABLE `{$table}` DROP COLUMN `{$col}`"); echo "    dropped {$table}.{$col}\n"; }
      }
    }
    $pdo->exec("DROP TABLE IF EXISTS bot_custom_emoji");
    echo "    dropped bot_custom_emoji (if existed)\n";
    $pdo->exec("DROP TABLE IF EXISTS agent_auth");
    echo "    dropped agent_auth (if existed)\n";
  ' "$TARGET"
else
  echo "    Database kept (pass --db to also drop added columns/table)."
fi
if command -v restorecon >/dev/null 2>&1; then restorecon -R "$TARGET" >/dev/null 2>&1 || true; fi
echo
echo "Uninstall complete. The bot and panel are back to stock nexorabot."
