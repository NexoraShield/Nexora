# Nexora Bot 🤖

<p align="center">
  <a href="README.md"><img src="https://flagcdn.com/28x21/gb.png" height="24" alt="English">&nbsp;<img src="https://img.shields.io/badge/English-007bff?style=for-the-badge" height="24" alt="English"></a>
  &nbsp;
  <a href="README.fa.md"><img src="https://flagcdn.com/28x21/ir.png" height="24" alt="فارسی">&nbsp;<img src="https://img.shields.io/badge/%D9%81%D8%A7%D8%B1%D8%B3%DB%8C-28a745?style=for-the-badge" height="24" alt="فارسی"></a>
  &nbsp;
  <a href="README.ru.md"><img src="https://flagcdn.com/28x21/ru.png" height="24" alt="Русский">&nbsp;<img src="https://img.shields.io/badge/%D0%A0%D1%83%D1%81%D1%81%D0%BA%D0%B8%D0%B9-dc3545?style=for-the-badge" height="24" alt="Русский"></a>
  &nbsp;
  <a href="README.zh.md"><img src="https://flagcdn.com/28x21/cn.png" height="24" alt="中文">&nbsp;<img src="https://img.shields.io/badge/%E4%B8%AD%E6%96%87-ffc107?style=for-the-badge" height="24" alt="中文"></a>
</p>

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
scp -r * root@YOUR_SERVER_IP:/var/www/html/nexorabotconfig/
```

3. اجرای installer:
```bash
cd /var/www/html/nexorabotconfig
sudo bash install.sh
```

4. پیکربندی توکن بات در `config.php`:
```php
$APIKEY = 'YOUR_BOT_TOKEN';
$adminnumber = 'YOUR_ADMIN_TELEGRAM_ID';
$domainhosts = 'your-domain.com';
$usernamebot = 'your_bot_username';
```

## 🔄 مهاجرت از ربات‌های دیگر

### از ربات Mirza
اگر یک نصب **Mirza** موجود دارید، می‌توانید با یک دستور به Nexora مهاجرت کنید. تمام کاربران، موجودی‌ها، فاکتورها، محصولات و داده‌های پنل شما حفظ می‌شوند.

```bash
bash <(curl -s https://raw.githubusercontent.com/NexoraShield/Nexora/main/migrate-mirza.sh)
```

اسکریپت:
1. نصب Mirza شما را به‌صورت خودکار شناسایی می‌کند
2. از دیتابیس و config بکاپ می‌گیرد
3. آخرین نسخهٔ Nexora را دانلود می‌کند
4. تمام فایل‌ها را جایگزین می‌کند (config.php و داده‌های شما حفظ می‌شوند)

### از ربات ViraNaut
```bash
bash <(curl -s https://raw.githubusercontent.com/NexoraShield/Nexora/main/migrate-viranaut.sh)
```

## 📁 ساختار پروژه

```
Nexora/
├── index.php              # نقطهٔ ورود بات (webhook)
├── admin.php              # رابط بات ادمین
├── function.php           # توابع اصلی
├── keyboard.php           # تولید کیبورد
├── nexora_style.php       # توابع style و emoji
├── panels.php             # مدیریت پنل
├── config.php             # پیکربندی (دستی)
├── panel/                 # پنل وب
│   ├── index.php          # داشبورد
│   ├── login.php          # ورود
│   ├── emojis.php         # مدیریت ایموجی پریمیوم
│   ├── btnstyle.php       # مدیریت رنگ/ایموجی دکمه‌ها
│   ├── panels.php         # مدیریت پنل‌ها
│   ├── health.php         # بررسی سلامت
│   ├── export.php         # خروجی CSV
│   ├── textemojis.php     # ایموجی دکمه‌های منو
│   ├── bottext.php        # ویرایشگر متن بات
│   ├── frenzyex.php       # تنظیمات درگاه FrenzyEx
│   └── ...
├── payment/               # درگاه‌های پرداخت
│   ├── frenzyex.php       # handler callback FrenzyEx
│   └── ...
├── cronbot/               # کارهای cron
│   ├── frenzyex.php       # polling FrenzyEx
│   ├── nudge_unpaid.php   # یادآوری فاکتورهای پرداخت‌نشده
│   └── ...
├── agent-panel/           # پنل نمایندگی/عامل
├── app/                   # مینی اپ
├── db/                    # schema و migrationهای دیتابیس
├── lang/                  # فایل‌های زبان (fa, en, ru, zh)
├── tools/                 # اسکریپت‌های کمکی
├── install.sh             # نصب‌کننده
├── migrate.php            # migration دیتابیس
└── README.md              # این فایل
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
cd /var/www/html/nexorabotconfig
sudo bash install.sh
```

## 📱 مینی اپ

Nexora شامل یک تلگرام مینی اپ است. آن را در BotFather پیکربندی کنید و به دامنهٔ خود اشاره دهید.

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

## 🤝 مشارکت

مشارکت‌ها خوش‌آمد هستند! لطفاً یک issue باز کنید یا pull request ارسال کنید.

## 📄 لایسنس

این پروژه تحت لایسنس MIT منتشر شده است.

## 🙏 تشکر

- Nexora Bot توسعه داده شده توسط [NexoraShield](https://github.com/NexoraShield)
- بر پایه تکنولوژی ربات VPN متن‌باز
- یکپارچه‌سازی درگاه پرداخت FrenzyEx
- پشتیبانی از ایموجی پریمیوم و تم شیشه‌ای
