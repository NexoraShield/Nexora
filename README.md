# Nexora Bot 🤖

<h2 align="center">
  <a href="README.md">🇬🇧 English</a> &nbsp;•&nbsp;
  <a href="README.fa.md">🇮🇷 فارسی</a> &nbsp;•&nbsp;
  <a href="README.ru.md">🇷🇺 Русский</a> &nbsp;•&nbsp;
  <a href="README.zh.md">🇨🇳 中文</a>
</h2>

**Nexora** is a free, open-source Telegram bot for VPN sales and management. Built with PHP, it supports multiple VPN panels (Marzban, Marzneshin, X-UI, Hiddify, WGDashboard, MikroTik, IBSng, and more), premium emoji support, glass theme UI, and the FrenzyEx payment gateway.

## ✨ Features

- 🎨 **Glass Theme UI** — Modern glassmorphism design for both bot and web panel
- 💎 **Premium Emoji Support** — Use Telegram premium emojis in buttons, products, and texts
- 🎨 **Button Colors** — Customize button colors (blue/green/red) per product, category, and panel
- 💳 **FrenzyEx Payment Gateway** — Built-in FrenzyEx payment integration
- ⚡ **High Performance** — Optimized database queries, indexes, and caching
- 📊 **Web Panel** — Full-featured admin panel for managing products, users, panels, and settings
- 🤝 **Agent/Reseller Panel** — Separate panel for resellers
- 📱 **Mini App** — Telegram Mini App for users
- 🔄 **Multi-Panel Support** — Marzban, Marzneshin, X-UI, Hiddify, WGDashboard, MikroTik, IBSng, and more
- 📈 **Analytics & Reports** — Sales reports, user statistics, payment tracking
- 🔔 **Notifications** — Automated notifications for expiry, low balance, etc.
- 🎁 **Gift Codes & Discounts** — Promotional codes and discount system
- 🎰 **Wheel of Fortune** — Gamification for user engagement
- 📦 **Product Management** — Categories, products, pricing, display order
- 👥 **User Management** — User profiles, balances, subscriptions
- 🔐 **Security** — CSRF protection, secure authentication, session management

## 📋 Requirements

- **PHP** >= 8.2
- **MySQL** >= 5.7 / MariaDB >= 10.3
- **Apache** with mod_rewrite enabled
- **Extensions:** PDO, PDO_MySQL, cURL, GD, mbstring, xml, zip, json
- **Composer** (for dependencies)

## 🚀 One-Line Installation

```bash
bash <(curl -s https://raw.githubusercontent.com/NexoraShield/Nexora/main/install.sh)
```

Or if you already have the package:

```bash
sudo bash nexora-update.sh
```

## 📦 Manual Installation

1. Clone the repository:
```bash
git clone https://github.com/NexoraShield/Nexora.git
cd Nexora
```

2. Upload to your server:
```bash
scp -r * root@YOUR_SERVER_IP:/var/www/html/nexorabotconfig/
```

3. Run the installer:
```bash
cd /var/www/html/nexorabotconfig
sudo bash install.sh
```

4. Configure your bot token in `config.php`:
```php
$APIKEY = 'YOUR_BOT_TOKEN';
$adminnumber = 'YOUR_ADMIN_TELEGRAM_ID';
$domainhosts = 'your-domain.com';
$usernamebot = 'your_bot_username';
```

5. Set up the webhook:
```
https://api.telegram.org/botYOUR_BOT_TOKEN/setWebhook?url=https://your-domain.com/index.php?secret=YOUR_SECRET
```

## 🔄 Migration from Other Bots

### From Mirza Bot
If you have an existing **Mirza** bot installation, you can migrate to Nexora with a single command. All your users, balances, invoices, products, and panel data will be preserved.

```bash
bash <(curl -s https://raw.githubusercontent.com/NexoraShield/Nexora/main/migrate-mirza.sh)
```

The script will:
1. Auto-detect your Mirza installation
2. Back up your database and config
3. Download the latest Nexora
4. Replace all files (keeping your `config.php` and database)
5. Run database migrations and rebrand
6. Set up the webhook

### From ViraNaut Bot
If you have an existing **ViraNaut** bot installation:

```bash
bash <(curl -s https://raw.githubusercontent.com/NexoraShield/Nexora/main/migrate-viranaut.sh)
```

The script will:
1. Auto-detect your ViraNaut installation
2. Back up your database and config
3. Download the latest Nexora
4. Replace all files (keeping your `config.php` and database)
5. Run database migrations and rebrand
6. Set up the webhook

### Manual Migration
If the auto-migration script doesn't work, you can migrate manually:

1. Back up your database:
   ```bash
   mysqldump -u YOUR_DB_USER -p YOUR_DB_NAME > backup.sql
   ```
2. Back up your `config.php`
3. Upload Nexora files to your server (overwrite existing files, but keep `config.php`)
4. Run the migration:
   ```bash
   php migrate.php /var/www/html/nexorabot
   ```
5. Apply rebrand:
   ```bash
   php rebrand.php /var/www/html/nexorabot --apply
   ```
6. Reload Apache:
   ```bash
   systemctl reload apache2
   ```

---

## 🔧 CLI Commands

After installation, use the `nexora` command:

```bash
nexora status     # Check bot & service status
nexora logs       # View recent error logs
nexora restart    # Restart Apache & services
nexora version    # Show installed version
nexora update     # Update to latest version
nexora help       # Show help
```

## 📁 Project Structure

```
Nexora/
├── index.php              # Main bot entry point
├── function.php           # Core functions
├── keyboard.php           # Keyboard generation
├── botapi.php             # Telegram Bot API wrapper
├── config.php             # Configuration
├── nexora_style.php        # Style & emoji helpers
├── admin.php              # Admin panel bot interface
├── panels.php             # Panel management
├── panels/                # Panel drivers
├── panel/                 # Web panel
│   ├── index.php          # Dashboard
│   ├── login.php          # Login
│   ├── emojis.php         # Premium emoji management
│   ├── btnstyle.php       # Button color/emoji management
│   ├── panels.php         # Panel management
│   ├── health.php         # Health check
│   ├── export.php         # CSV export
│   ├── textemojis.php     # Menu button emojis
│   ├── bottext.php        # Bot text editor
│   ├── frenzyex.php       # FrenzyEx gateway settings
│   └── ...
├── payment/               # Payment gateways
│   ├── frenzyex.php       # FrenzyEx callback handler
│   └── ...
├── cronbot/               # Cron jobs
│   ├── frenzyex.php       # FrenzyEx polling
│   ├── nudge_unpaid.php   # Unpaid invoice reminders
│   └── ...
├── agent-panel/           # Reseller/agent panel
├── app/                   # Mini App
├── db/                    # Database schema & migrations
├── lang/                  # Language files (fa, en, ru, zh)
├── tools/                 # Utility scripts
├── install.sh             # Installer
├── migrate.php            # Database migration
└── README.md              # This file
```

## 🎨 Premium Emoji Support

Nexora supports Telegram premium emojis. Use the `{emoji:slug}` syntax in product names, button texts, and bot messages.

### Adding Emojis:
1. Open the web panel → Emojis
2. Add emojis manually, or send in bot: `/savemoji <name>` + premium emoji

### Button Colors:
- **primary** (blue)
- **success** (green)
- **danger** (red)

Set colors per product, category, and panel in the web panel → Button Style.

## 💳 FrenzyEx Payment Gateway

1. Open the bot → Finance → Payment settings → FrenzyEx
2. Enter your API key, base URL, and callback secret
3. Or use the web panel → FrenzyEx Gateway

## ⚡ Performance Optimization

Nexora includes optimized database indexes. Run manually:
```bash
php tools/nexora-indexes.php
```

## 🔄 Updating

```bash
nexora update
```

Or manually:
```bash
cd /var/www/html/nexorabotconfig
sudo bash install.sh
```

## 📱 Mini App

Nexora includes a Telegram Mini App. Configure it in BotFather and point it to your domain.

## 🤝 Contributing

Contributions are welcome! Please open an issue or submit a pull request.

## 📄 License

This project is licensed under the MIT License.

## 🙏 Credits

- Nexora Bot developed by [NexoraShield](https://github.com/NexoraShield)
- Based on open-source VPN bot technology
- FrenzyEx payment gateway integration
- Premium emoji & glass theme support

## 📞 Support

- **Bot:** @NexoraShield_bot
- **Website:** NexoraShield.tr
- **Email:** info@NexoraShield.tr
- **Support:** @Nexora_supp
- **Channel:** @Nexorashieeld
- **Tutorial:** @NexoraUsersHelp

## 💖 Donate

If you find this project useful, please consider supporting its development:

### Crypto (USDT TRC20 / TRX)
```
TFvvFVAxBUkUfkw7cCt38RDehmnNkDHtfr
```

### Telegram
- **Stars/Gifts:** @Nexora_supp
- **Website:** NexoraShield.tr

### Other
- **Email:** info@NexoraShield.tr
- **Support:** @Nexora_supp

Your support helps keep this project free and maintained! 🙏
