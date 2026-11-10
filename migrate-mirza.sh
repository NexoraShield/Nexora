#!/usr/bin/env bash
set -Eeuo pipefail

# ============================================================
# Nexora Migration: Mirza → Nexora
# Single command: bash <(curl -s https://raw.githubusercontent.com/NexoraShield/Nexora/main/migrate-mirza.sh)
# ============================================================

MIRZA_DIR=""
for candidate in /var/www/html/mirzaprobotconfig /var/www/html/mirzabotconfig /var/www/html/mirza /var/www/html/bot; do
    if [[ -f "$candidate/config.php" && -f "$candidate/index.php" && -f "$candidate/function.php" ]]; then
        MIRZA_DIR="$candidate"
        break
    fi
done

if [[ -z "$MIRZA_DIR" ]]; then
    echo "ERROR: No Mirza installation found." >&2
    exit 1
fi

echo "==> Found Mirza installation at: $MIRZA_DIR"

# Backup
BACKUP_DIR="/root/mirza-backup-$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"
cp "$MIRZA_DIR/config.php" "$BACKUP_DIR/config.php"
mysqldump -h "$(grep 'dbhost' $MIRZA_DIR/config.php | cut -d\"'\" -f2)" \
          -u "$(grep 'usernamedb' $MIRZA_DIR/config.php | cut -d\"'\" -f2)" \
          -p"$(grep 'passworddb' $MIRZA_DIR/config.php | cut -d\"'\" -f2)" \
          "$(grep 'dbname' $MIRZA_DIR/config.php | cut -d\"'\" -f2)" > "$BACKUP_DIR/database.sql" 2>/dev/null || true
echo "==> Backup saved to $BACKUP_DIR"

# Download Nexora
NEXORA_DIR="/var/www/html/nexorabot"
mkdir -p "$NEXORA_DIR"
cd /tmp
rm -rf nexora-migrate
git clone --depth 1 https://github.com/NexoraShield/Nexora.git nexora-migrate 2>/dev/null || {
    echo "ERROR: Could not download Nexora. Check internet connection." >&2
    exit 1
}
cd nexora-migrate

# Copy all files except config.php (preserve existing DB credentials)
for f in $(find . -type f -not -name 'config.php' -not -name 'migrate.sh' -not -name 'migrate-mirza.sh' -not -name 'migrate-viranaut.sh'); do
    rel="${f#./}"
    mkdir -p "$NEXORA_DIR/$(dirname "$rel")"
    cp -- "$f" "$NEXORA_DIR/$rel"
done

# Preserve config.php from Mirza (DB credentials, bot token, domain)
cp "$MIRZA_DIR/config.php" "$NEXORA_DIR/config.php"

# Run migration
cd "$NEXORA_DIR"
php migrate.php "$NEXORA_DIR" 2>&1 || true

# Run rebrand
php rebrand.php "$NEXORA_DIR" --apply 2>&1 || true

# Set up Apache (if needed)
if [[ -f /etc/apache2/sites-enabled/*.conf ]]; then
    sed -i "s|DocumentRoot /var/www/html/mirzaprobotconfig|DocumentRoot $NEXORA_DIR|" /etc/apache2/sites-enabled/*.conf 2>/dev/null || true
    systemctl reload apache2 2>/dev/null || true
fi

# Set up webhook
BOT_TOKEN=$(grep 'APIKEY' "$NEXORA_DIR/config.php" | cut -d"'" -f2)
DOMAIN=$(grep 'domainhosts' "$NEXORA_DIR/config.php" | cut -d"'" -f2)
SECRET=$(grep 'webhook_secret' <(mysql -u "$(grep usernamedb $NEXORA_DIR/config.php | cut -d"'" -f2)" -p"$(grep passworddb $NEXORA_DIR/config.php | cut -d"'" -f2)" "$(grep dbname $NEXORA_DIR/config.php | cut -d"'" -f2)" -e "SELECT webhook_secret FROM setting LIMIT 1;" 2>/dev/null | tail -1) 2>/dev/null)
if [[ -n "$BOT_TOKEN" && -n "$DOMAIN" ]]; then
    curl -s "https://api.telegram.org/bot$BOT_TOKEN/setWebhook?url=https://$DOMAIN/index.php?secret=$SECRET" >/dev/null 2>&1 || true
fi

echo
echo "==> Migration from Mirza to Nexora complete!"
echo "    Nexora installed at: $NEXORA_DIR"
echo "    Backup saved at: $BACKUP_DIR"
echo "    Panel: https://$DOMAIN/panel/"
echo "    Bot: @$usernamebot"
echo
echo "Next steps:"
echo "  1. Open panel: https://$DOMAIN/panel/"
echo "  2. Run: php $NEXORA_DIR/tools/nexora-indexes.php (optional, for performance)"
echo "  3. Set up cron: */30 * * * * php $NEXORA_DIR/cronbot/nudge_unpaid.php"
