# Nexora Bot 🤖

<h2 align="center">
  <a href="README.md">🇬🇧 English</a> &nbsp;•&nbsp;
  <a href="README.fa.md">🇮🇷 فارسی</a> &nbsp;•&nbsp;
  <a href="README.ru.md">🇷🇺 Русский</a> &nbsp;•&nbsp;
  <a href="README.zh.md">🇨🇳 中文</a>
</h2>

**Nexora** — бесплатный Telegram-бот с открытым исходным кодом для продажи и управления VPN. Написан на PHP, поддерживает множество VPN-панелей (Marzban, Marzneshin, X-UI, Hiddify, WGDashboard, MikroTik, IBSng и другие). Включает премиум-эмодзи, glass-тему и платёжный шлюз FrenzyEx.

## ✨ Возможности

- 🎨 **Glass Theme UI** — современный glassmorphism-дизайн для бота и веб-панели
- 💎 **Премиум-эмодзи** — использование премиум-эмодзи Telegram в кнопках, продуктах и текстах
- 🎨 **Цвета кнопок** — настройка цветов (синий/зелёный/красный) для каждого продукта, категории и панели
- 💳 **Платёжный шлюз FrenzyEx** — встроенная интеграция FrenzyEx
- ⚡ **Высокая производительность** — оптимизированные запросы к БД, индексы, кэширование
- 📊 **Веб-панель** — полноценная панель управления продуктами, пользователями, панелями и настройками
- 🤝 **Панель агента/реселлера** — отдельная панель для реселлеров
- 📱 **Mini App** — Telegram Mini App для пользователей
- 🔄 **Поддержка множества панелей** — Marzban, Marzneshin, X-UI, Hiddify, WGDashboard, MikroTik, IBSng и другие
- 📈 **Аналитика и отчёты** — отчёты о продажах, статистика пользователей, отслеживание платежей
- 🔔 **Уведомления** — автоматические уведомления об истечении, низком балансе и т.д.
- 🎁 **Промокоды и скидки** — система промокодов и скидок
- 🎰 **Колесо фортуны** — геймификация для вовлечения пользователей
- 📦 **Управление продуктами** — категории, продукты, цены, порядок отображения
- 👥 **Управление пользователями** — профили, балансы, подписки
- 🔐 **Безопасность** — CSRF-защита, безопасная аутентификация, управление сессиями

## 📋 Требования

- **PHP** >= 8.2
- **MySQL** >= 5.7 / MariaDB >= 10.3
- **Apache** с включённым mod_rewrite
- **Расширения:** PDO, PDO_MySQL, cURL, GD, mbstring, xml, zip, json
- **Composer** (для зависимостей)

## 🚀 Установка одной командой

```bash
bash <(curl -s https://raw.githubusercontent.com/NexoraShield/Nexora/main/nexora-update.sh)
```

## 📦 Ручная установка

1. Клонировать репозиторий:
```bash
git clone https://github.com/NexoraShield/Nexora.git
cd Nexora
```

2. Загрузить на сервер:
```bash
scp -r * root@YOUR_SERVER_IP:/var/www/html/nexorabotconfig/
```

3. Запустить установщик:
```bash
cd /var/www/html/nexorabotconfig
sudo bash install.sh
```

## 🔧 CLI-команды

После установки используйте команду `nexora`:

```bash
nexora status     # Проверка статуса бота и сервисов
nexora logs       # Просмотр логов ошибок
nexora restart    # Перезапуск сервисов
nexora version    # Показать установленную версию
nexora update     # Обновить до последней версии
nexora help       # Справка
```

## 🎨 Премиум-эмодзи

Nexora поддерживает премиум-эмодзи Telegram. Используйте синтаксис `{emoji:slug}` в названиях продуктов, текстах кнопок и сообщениях бота.

## 💳 Платёжный шлюз FrenzyEx

1. Бот → Финансы → Настройки платежей → FrenzyEx
2. Введите API-ключ, базовый URL и секрет callback
3. Или веб-панель → FrenzyEx Gateway

## ⚡ Оптимизация производительности

Nexora включает оптимизированные индексы базы данных:
```bash
php tools/nexora-indexes.php
```

## 🔄 Обновление

```bash
nexora update
```

## 📞 Поддержка

- **Бот:** @NexoraShield_bot
- **Сайт:** NexoraShield.tr
- **Email:** info@NexoraShield.tr
- **Поддержка:** @Nexora_supp
- **Канал:** @Nexorashieeld
- **Обучение:** @NexoraUsersHelp

## 💖 Поддержать проект

Если вам нравится проект, поддержите разработку небольшим донатом:

### Криптовалюта (USDT TRC20 / TRX)
```
TFvvFVAxBUkUfkw7cCt38RDehmnNkDHtfr
```

### Telegram
- **Stars/Gifts:** @Nexora_supp
- **Website:** NexoraShield.tr

### Другие способы
- **Email:** info@NexoraShield.tr
- **Поддержка:** @Nexora_supp

Ваша поддержка помогает развивать этот бесплатный проект! 🙏

## 📄 Лицензия

Проект распространяется под лицензией MIT.

## 🙏 Благодарности

- Nexora Bot разработан [NexoraShield](https://github.com/NexoraShield)
- На основе открытой технологии VPN-бота
- Интеграция платёжного шлюза FrenzyEx
- Поддержка премиум-эмодзи и glass-темы
