#!/usr/bin/env bash
set -Eeuo pipefail

# ============================================================
# Nexora Migration: ViraNaut → Nexora
# Single command: bash <(curl -s https://raw.githubusercontent.com/NexoraShield/Nexora/main/migrate-viranaut.sh)
# ============================================================

VIRA_DIR=""
for candidate in /var/www/html/viranaut /var/www/html/viranautconfig /var/www/html/legacybotconfig /var/www/html/vira_pro /var/www/vira_pro; do
    if [[ -f "$candidate/config.php" && -f "$candidate/index.php" && -f "$candidate/function.php" ]]; then
        VIRA_DIR="$candidate"
        break
    fi
done

if [[ -z "$VIRA_DIR" ]]; then
    echo "ERROR: No ViraNaut installation found." >&2
    exit 1
fi

echo "==> Found ViraNaut installation at: $VIRA_DIR"

# Backup
BACKUP_DIR="/root/viranaut-backup-$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"
cp "$VIRA_DIR/config.php" "$BACKUP_DIR/config.php"
dbhost=$(grep 'dbhost' "$VIRA_DIR/config.php" | cut -d"'" -f2)
dbname=$(grep 'dbname' "$VIRA_DIR/config.php" | cut -d"'" -f2)
dbuser=$(grep 'usernamedb' "$VIRA_DIR/config.php" | cut -d"'" -f2)
dbpass=$(grep 'passworddb' "$VIRA_DIR/config.php" | cut -d"'" -f2)
mysqldump -h "$dbhost" -u "$dbuser" -p"$dbpass" --no-tablespaces --ssl-mode=DISABLED "$dbname" > "$BACKUP_DIR/database.sql" 2>/dev/null || true
echo "==> Backup saved to $BACKUP_DIR"

# Download Nexora
NEXORA_DIR="/var/www/html/nexorabot"
mkdir -p "$NEXORA_DIR"
cd /tmp
rm -rf nexora-migrate-vira
git clone --depth 1 https://github.com/NexoraShield/Nexora.git nexora-migrate-vira 2>/dev/null || {
    echo "ERROR: Could not download Nexora. Check internet connection." >&2
    exit 1
}
cd nexora-migrate-vira

# Copy all files except config.php (preserve existing DB credentials)
for f in $(find . -type f -not -name 'config.php' -not -name 'migrate.sh' -not -name 'migrate-mirza.sh' -not -name 'migrate-viranaut.sh'); do
    rel="${f#./}"
    mkdir -p "$NEXORA_DIR/$(dirname "$rel")"
    cp -- "$f" "$NEXORA_DIR/$rel"
done

# Preserve config.php from ViraNaut
cp "$VIRA_DIR/config.php" "$NEXORA_DIR/config.php"

# Ensure vendor/ exists (copy from ViraNaut if available)
if [[ -d "$VIRA_DIR/vendor" && ! -d "$NEXORA_DIR/vendor" ]]; then
    cp -r "$VIRA_DIR/vendor" "$NEXORA_DIR/vendor"
fi

# Run migration (additive - adds new columns/tables)
cd "$NEXORA_DIR"
php migrate.php "$NEXORA_DIR" 2>&1 || true

# Run rebrand
php rebrand.php "$NEXORA_DIR" --apply 2>&1 || true

# Set up Apache
if [[ -f /etc/apache2/sites-enabled/*.conf ]]; then
    sed -i "s|DocumentRoot /var/www/html/viranaut|DocumentRoot $NEXORA_DIR|" /etc/apache2/sites-enabled/*.conf 2>/dev/null || true
    sed -i "s|DocumentRoot /var/www/html/viranautconfig|DocumentRoot $NEXORA_DIR|" /etc/apache2/sites-enabled/*.conf 2>/dev/null || true
    sed -i "s|DocumentRoot /var/www/html/legacybotconfig|DocumentRoot $NEXORA_DIR|" /etc/apache2/sites-enabled/*.conf 2>/dev/null || true
    sed -i "s|<Directory /var/www/html/viranaut>|<Directory $NEXORA_DIR>|" /etc/apache2/sites-enabled/*.conf 2>/dev/null || true
    sed -i "s|<Directory /var/www/html/viranautconfig>|<Directory $NEXORA_DIR>|" /etc/apache2/sites-enabled/*.conf 2>/dev/null || true
    sed -i "s|<Directory /var/www/html/legacybotconfig>|<Directory $NEXORA_DIR>|" /etc/apache2/sites-enabled/*.conf 2>/dev/null || true
    systemctl reload apache2 2>/dev/null || true
fi

echo
echo "==> Migration from ViraNaut to Nexora complete!"
echo "    Nexora installed at: $NEXORA_DIR"
echo "    Backup saved at: $BACKUP_DIR"
echo
echo "Next steps:"
echo "  1. Open panel: https://YOUR-DOMAIN/panel/"
echo "  2. Run: php $NEXORA_DIR/tools/nexora-indexes.php (optional, for performance)"
echo "  3. Set up cron: */30 * * * * php $NEXORA_DIR/cronbot/nudge_unpaid.php"
echo "  4. If web panel texts are wrong, go to Panel → Bot Texts and reset"
