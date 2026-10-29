# Nexora Bot 🤖

<h2 align="center">
  <a href="README.md"><kbd>🇬🇧 English</kbd></a> &nbsp;
  <a href="README.fa.md"><kbd>🇮🇷 فارسی</kbd></a> &nbsp;
  <a href="README.ru.md"><kbd>🇷🇺 Русский</kbd></a> &nbsp;
  <a href="README.zh.md"><kbd>🇨🇳 中文</kbd></a>
</h2>

**Nexora** 是一个免费的开源 Telegram 机器人，用于 VPN 销售和管理。使用 PHP 编写，支持多种 VPN 面板（Marzban、Marzneshin、X-UI、Hiddify、WGDashboard、MikroTik、IBSng 等）。包含高级表情、玻璃主题 UI 和 FrenzyEx 支付网关。

## ✨ 功能特性

- 🎨 **玻璃主题 UI** — 现代 glassmorphism 设计，适用于机器人和网页面板
- 💎 **高级表情支持** — 在按钮、产品和文本中使用 Telegram 高级表情
- 🎨 **按钮颜色** — 为每个产品、类别和面板自定义按钮颜色（蓝色/绿色/红色）
- 💳 **FrenzyEx 支付网关** — 内置 FrenzyEx 支付集成
- ⚡ **高性能** — 优化的数据库查询、索引和缓存
- 📊 **网页面板** — 功能完整的管理面板，管理产品、用户、面板和设置
- 🤝 **代理/分销商面板** — 独立的分销商面板
- 📱 **Mini App** — 用户的 Telegram Mini App
- 🔄 **多面板支持** — Marzban、Marzneshin、X-UI、Hiddify、WGDashboard、MikroTik、IBSng 等
- 📈 **分析与报表** — 销售报表、用户统计、支付跟踪
- 🔔 **通知** — 自动通知到期、余额不足等
- 🎁 **礼品码与折扣** — 促销码和折扣系统
- 🎰 **幸运转盘** — 用户参与的趣味功能
- 📦 **产品管理** — 类别、产品、定价、显示顺序
- 👥 **用户管理** — 用户资料、余额、订阅
- 🔐 **安全** — CSRF 保护、安全认证、会话管理

## 📋 系统要求

- **PHP** >= 8.2
- **MySQL** >= 5.7 / MariaDB >= 10.3
- **Apache** 启用 mod_rewrite
- **扩展：** PDO, PDO_MySQL, cURL, GD, mbstring, xml, zip, json
- **Composer**（用于依赖管理）

## 🚀 一键安装

```bash
bash <(curl -s https://raw.githubusercontent.com/NexoraShield/Nexora/main/nexora-update.sh)
```

## 📦 手动安装

1. 克隆仓库：
```bash
git clone https://github.com/NexoraShield/Nexora.git
cd Nexora
```

2. 上传到服务器：
```bash
scp -r * root@YOUR_SERVER_IP:/var/www/html/nexorabotconfig/
```

3. 运行安装程序：
```bash
cd /var/www/html/nexorabotconfig
sudo bash install.sh
```

## 🔧 CLI 命令

安装后使用 `nexora` 命令：

```bash
nexora status     # 检查机器人和服务状态
nexora logs       # 查看最近的错误日志
nexora restart    # 重启服务
nexora version    # 显示已安装版本
nexora update     # 更新到最新版本
nexora help       # 显示帮助
```

## 🎨 高级表情支持

Nexora 支持 Telegram 高级表情。在产品名称、按钮文本和机器人消息中使用 `{emoji:slug}` 语法。

## 💳 FrenzyEx 支付网关

1. 机器人 → 财务 → 支付设置 → FrenzyEx
2. 输入 API 密钥、基础 URL 和回调密钥
3. 或网页面板 → FrenzyEx Gateway

## ⚡ 性能优化

Nexora 包含优化的数据库索引：
```bash
php tools/nexora-indexes.php
```

## 🔄 更新

```bash
nexora update
```

## 📞 支持

- **机器人:** @NexoraShield_bot
- **网站:** NexoraShield.tr
- **邮箱:** info@NexoraShield.tr
- **支持:** @Nexora_supp
- **频道:** @Nexorashieeld
- **教程:** @NexoraUsersHelp

## 💖 支持项目

如果您喜欢这个项目，请考虑小额捐赠支持开发：

### 加密货币 (USDT TRC20 / TRX)
```
TFvvFVAxBUkUfkw7cCt38RDehmnNkDHtfr
```

### Telegram
- **Stars/Gifts:** @Nexora_supp
- **Website:** NexoraShield.tr

### 其他方式
- **Email:** info@NexoraShield.tr
- **Support:** @Nexora_supp

您的支持帮助这个免费项目继续发展！ 🙏

## 📄 许可证

本项目基于 MIT 许可证发布。

## 🙏 致谢

- Nexora Bot 由 [NexoraShield](https://github.com/NexoraShield) 开发
- 基于开源 VPN 机器人技术
- FrenzyEx 支付网关集成
- 高级表情和玻璃主题支持
