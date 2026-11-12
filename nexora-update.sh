#!/usr/bin/env bash
# Nexora Bot - Self-updating installer
# Usage: sudo bash nexora-update.sh
# Or one-line: curl -sL https://raw.githubusercontent.com/NexoraShield/Nexora/main/nexora-update.sh -o /tmp/nexora-install.sh && sudo bash /tmp/nexora-install.sh
# Or pipe: bash <(curl -s https://raw.githubusercontent.com/NexoraShield/Nexora/main/nexora-update.sh)
set -Eeuo pipefail

if [ "$(id -u)" -ne 0 ]; then
    echo "Run as root: sudo bash $0" >&2
    exit 1
fi

command -v git   >/dev/null 2>&1 || { apt-get update -qq && apt-get install -y -qq git;   }
command -v unzip >/dev/null 2>&1 || { apt-get update -qq && apt-get install -y -qq unzip; }
command -v php   >/dev/null 2>&1 || { echo "PHP is required. Install PHP first." >&2; exit 1; }

echo "==> Nexora Bot Installer"
echo "==> Fetching latest code from GitHub..."

TMPD=$(mktemp -d)
trap "rm -rf $TMPD" EXIT

# Clone latest source (shallow, single branch)
if ! git clone --depth 1 -b main https://github.com/NexoraShield/Nexora.git "$TMPD/pkg" 2>/dev/null; then
    echo "==> Git clone failed, trying direct download..."
    if ! curl -fsSL "https://github.com/NexoraShield/Nexora/archive/refs/heads/main.zip" -o "$TMPD/pkg.zip"; then
        echo "==> Failed to download source." >&2
        exit 1
    fi
    unzip -o -q "$TMPD/pkg.zip" -d "$TMPD"
    mv "$TMPD"/Nexora-* "$TMPD/pkg" 2>/dev/null || true
fi

cd "$TMPD/pkg"

# Regenerate manifest (exclude .git so the file list matches what gets installed)
find . -type f ! -name manifest.sha256 ! -path './.git/*' -print0 | sort -z | xargs -0 sha256sum | sed 's| \./| |' > manifest.sha256

echo "==> Running installer..."
bash install.sh "$@"

exit 0
