# Nexora Bot 🤖

[![English](https://img.shields.io/badge/🇬🇧-English-blue)](README.md)
[![فارسی](https://img.shields.io/badge/🇮🇷-فارسی-green)](README.fa.md)
[![Русский](https://img.shields.io/badge/🇷🇺-Русский-red)](README.ru.md)
[![中文](https://img.shields.io/badge/🇨🇳-中文-yellow)](README.zh.md)

**Nexora** یک ربات تلگرامی رایگان و متن‌باز برای فروش و مدیریت VPN است. با PHP نوشته شده و از پنل‌های مختلف VPN (Marzban, Marzneshin, X-UI, Hiddify, WGDashboard, MikroTik, IBSng و غیره) پشتیبانی می‌کند. دارای ایموجی پریمیوم، تم شیشه‌ای، و درگاه پرداخت FrenzyEx.

## ✨ امکانات

- 🎨 **تم شیشه‌ای (Glass Theme)** — طراحی مدرن glassmorphism برای بات و پنل وب
- 💎 **پشتیبانی از ایموجی پریمیوم** — استفاده از ایموجی‌های پریمیوم تلگرام در دکمه‌ها، محصولات و متون
- 🎨 **رنگ دکمه‌ها** — شخصی‌سازی رنگ دکمه‌ها (آبی/سبز/قرمز) برای هر محصول، دسته و پنل
- 💳 **درگاه پرداخت FrenzyEx** — یکپارچه‌سازی درگاه پرداخت FrenzyEx
- ⚡ **عملکرد بالا** — بهینه‌سازی کوئری‌های دیتابیس، ایندکس‌ها و کش
- 📊 **پنل وب** — پنل مدیریت کامل برای محصولات، کاربران، پنل‌ها و تنظیمات
- 🤝 **پنل نمایندگی** — پنل جداگانه برای نمایندگان
- 📱 **مینی اپ** — مینی اپ تلگرام برای کاربران
- 🔄 **پشتیبانی چندپنلی** — Marzban, Marzneshin, X-UI, Hiddify, WGDashboard, MikroTik, IBSng و غیره
- 📈 **آمار و گزارشات** — گزارش فروش، آمار کاربران، پیگیری پرداخت‌ها
- 🔔 **اعلان‌ها** — اعلان‌های خودکار برای انقضا، موجودی کم و غیره
- 🎁 **کدهای هدیه و تخفیف** — سیستم کدهای تخفیف و تبلیغاتی
- 🎰 **گردونه شانس** — گیمیفیکیشن برای جذب کاربر
- 📦 **مدیریت محصولات** — دسته‌ها، محصولات، قیمت‌گذاری، ترتیب نمایش
- 👥 **مدیریت کاربران** — پروفایل کاربران، موجودی، اشتراک‌ها
- 🔐 **امنیت** — محافظت CSRF، احراز هویت امن، مدیریت session

## 📋 پیش‌نیازها

- **PHP** >= 8.2
- **MySQL** >= 5.7 / MariaDB >= 10.3
- **Apache** با mod_rewrite فعال
- **Extensions:** PDO, PDO_MySQL, cURL, GD, mbstring, xml, zip, json
- **Composer** (برای dependencies)

## 🚀 نصب یک‌خطی

```bash
bash <(curl -s https://raw.githubusercontent.com/NexoraShield/Nexora/main/nexora-update.sh)
```

## 📦 نصب دستی

1. کلون کردن repository:
```bash
git clone https://github.com/NexoraShield/Nexora.git
cd Nexora
```

2. آپلود به سرور:
```bash
scp -r * root@YOUR_SERVER_IP:/var/www/html/mirzaprobotconfig/
```

3. اجرای installer:
```bash
cd /var/www/html/mirzaprobotconfig
sudo bash install.sh
```

4. پیکربندی توکن بات در `config.php`:
```php
$APIKEY = 'YOUR_BOT_TOKEN';
$adminnumber = 'YOUR_ADMIN_TELEGRAM_ID';
$domainhosts = 'your-domain.com';
$usernamebot = 'your_bot_username';
```

## 🔧 دستورات CLI

بعد از نصب، از دستور `nexora` استفاده کنید:

```bash
nexora status     # بررسی وضعیت بات و سرویس‌ها
nexora logs       # مشاهده لاگ‌های خطا
nexora restart    # ریستارت سرویس‌ها
nexora version    # نمایش نسخه نصب شده
nexora update     # به‌روزرسانی به آخرین نسخه
nexora help       # نمایش راهنما
```

## 🎨 پشتیبانی از ایموجی پریمیوم

Nexora از ایموجی‌های پریمیوم تلگرام پشتیبانی می‌کند. از سینتکس `{emoji:slug}` در نام محصولات، متن دکمه‌ها و پیام‌های بات استفاده کنید.

### اضافه کردن ایموجی:
1. پنل وب → ایموجی‌ها
2. اضافه کردن دستی، یا ارسال در بات: `/savemoji <name>` + ایموجی پریمیوم

### رنگ دکمه‌ها:
- **primary** (آبی)
- **success** (سبز)
- **danger** (قرمز)

## 💳 درگاه پرداخت FrenzyEx

1. بات → مالی → تنظیمات پرداخت → FrenzyEx
2. کلید API، آدرس پایه و رمز callback را وارد کنید
3. یا پنل وب → FrenzyEx Gateway

## ⚡ بهینه‌سازی عملکرد

Nexora شامل ایندکس‌های بهینه‌شده دیتابیس است. اجرای دستی:
```bash
php tools/nexora-indexes.php
```

## 🔄 به‌روزرسانی

```bash
nexora update
```

یا دستی:
```bash
cd /var/www/html/mirzaprobotconfig
sudo bash install.sh
```

## 📞 پشتیبانی

- **ربات:** @NexoraShield_bot
- **وبسایت:** NexoraShield.tr
- **ایمیل:** info@NexoraShield.tr
- **پشتیبانی:** @Nexora_supp
- **کانال:** @Nexorashieeld
- **آموزش:** @NexoraUsersHelp

## 💖 حمایت مالی

اگر از این پروژه راضی هستید، با یک دونیت کوچک به ادامه توسعه کمک کنید:

### ارز دیجیتال (USDT TRC20 / TRX)
```
TFvvFVAxBUkUfkw7cCt38RDehmnNkDHtfr
```

### تلگرام
- **ستاره/گیفت:** @Nexora_supp
- **وبسایت:** NexoraShield.tr

### سایر روش‌ها
- **ایمیل:** info@NexoraShield.tr
- **پشتیبانی:** @Nexora_supp

حمایت شما به ادامه توسعه این پروژه رایگان کمک می‌کند! 🙏

## 📄 لایسنس

این پروژه تحت لایسنس MIT منتشر شده است.

## 🙏 تشکر

- Mirzabot اصلی توسط [mahdiMGF2](https://github.com/mahdiMGF2/mirzabot)
- امکانات ViraNaut به Nexora منتقل شده
- یکپارچه‌سازی درگاه پرداخت FrenzyEx
