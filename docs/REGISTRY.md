# Реестр проекта TSOY.IN · Banq

> Карта кода: что где лежит, что от чего зависит, какие переменные и функции есть.
> **Правило:** любой PR, который добавляет модуль, таблицу, переменную, маршрут или настройку, обновляет этот файл.
> Метки `A01…` связывают код с записями в [JOURNAL.md](JOURNAL.md) — их же видно в комментариях в коде.

## 1. Дерево проекта

```
app/
├─ Enums/DepositType.php ............ A07 типы вкладов КФГД + подписи из Excel
├─ Http/
│  ├─ Middleware/EnsureInstalled.php  A03 глобальный: .env/APP_KEY, редирект на /install
│  ├─ Middleware/EnsureAdmin.php .... A09 роль admin|editor для /admin
│  └─ Controllers/
│     ├─ HomeController.php ......... A10 главная-дашборд
│     ├─ Install/InstallController .. A03 мастер установки (3 шага)
│     ├─ Auth/LoginController ....... A09 вход/выход
│     └─ Admin/{Dashboard,Settings,Bank,Import}Controller  A05 A06 A08 A09
├─ Models/ Setting, Bank, DepositProduct, DepositRate, Import, User
├─ Services/
│  ├─ Installer/ EnvironmentChecker, EnvWriter, DatabaseConfigurator   A03
│  └─ Import/ DepositSheetParser (чтение), DepositImporter (запись)   A08
├─ Support/ Installation (признак установки), Settings (настройки+кеш)  A03 A05
└─ helpers.php ...................... settings(), project_years(), rate_format()
config/banq.php ..................... версия, путь lock-файла, валюты, сроки
database/migrations/2026_11_26_* .... settings+role, banks, deposit_*, imports
database/seeders/BankSeeder.php ..... 18 банков РК с алиасами (черновик, сверить с АРРФР)
resources/
├─ css/app.css ...................... A04 дизайн-система (токены, glass, aurora, анимации)
├─ js/app.js ........................ A04 Alpine: тема, x-count, калькулятор, reveal, glow
└─ views/
   ├─ layouts/ app (витрина), admin, install
   ├─ partials/ head, logo, backdrop, theme-toggle
   ├─ home.blade.php, auth/login
   ├─ install/ welcome, database, site
   └─ admin/ dashboard, settings/edit, banks/{index,edit}, import/{index,preview}
public/build/ ....................... собранный фронтенд (коммитится: на сервере нет Node)
public/uploads/branding/ ............ логотип и фавикон из админки (в git не попадают)
tests/ .............................. Unit: парсер Excel · Feature: установка, админка, импорт
deploy.sh ........................... A11 деплой на BrainyCP
```

## 2. Граф зависимостей

```
HTTP запрос
  └─ EnsureInstalled ──► Installation::isInstalled() ──► storage/app/installed.json
        ├─ нет → /install → InstallController
        │        ├─ EnvironmentChecker
        │        ├─ DatabaseConfigurator ──► EnvWriter ──► .env
        │        ├─ Artisan migrate ──► все миграции
        │        ├─ Settings::set ──► settings
        │        └─ BankSeeder ──► banks
        └─ да → web routes
              ├─ HomeController ──► DepositRate(current) → DepositProduct → Bank
              │                  └─ DepositType
              ├─ LoginController ──► users
              └─ /admin (auth + EnsureAdmin)
                    ├─ SettingsController ──► Settings ──► settings, public/uploads/branding
                    ├─ BankController ──► banks
                    └─ ImportController ──► DepositImporter
                                              ├─ DepositSheetParser ──► PhpSpreadsheet, DepositType
                                              └─ Bank::findByIdentifier (code/name/aliases)
                                                   → DepositProduct::firstOrCreate → DepositRate, Import

Все шаблоны ──► settings()/project_years() ──► Support\Settings ──► кеш 'banq.settings' ──► settings
```

## 3. Таблицы

| Таблица | Ключевые поля | Кто пишет | Кто читает |
|---|---|---|---|
| `settings` | key (unique), value json, group | Settings::set (установка, админка) | Support\Settings → все шаблоны |
| `users` | + role: admin/editor/user | установка, (этап 3: регистрация) | Auth, EnsureAdmin |
| `banks` | code (unique), slug, aliases json, brand_color, status | BankSeeder, BankController, импорт | Home, админка, импорт |
| `deposit_products` | bank_id, type, name, slug, can_replenish/withdraw | импорт (firstOrCreate) | Home |
| `deposit_rates` | product_id, currency, term_months, amount_from, rate, effective_rate, valid_from, **valid_to (null = текущая)**, source, import_id | импорт | Home (scope current) |
| `imports` | filename, path, rows_*, log json | DepositImporter | админка |

## 4. Переменные окружения (.env)

| Переменная | Кто задаёт | Значение по умолчанию |
|---|---|---|
| `APP_KEY` | EnsureInstalled (автоматически при первом запросе) | — |
| `APP_NAME`, `APP_ENV=production`, `APP_DEBUG=false` | мастер установки (шаг 3) | из .env.example |
| `APP_URL` | мастер установки (шаг 2) | http://localhost |
| `APP_TIMEZONE` | вручную | Asia/Almaty |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_PREFIX` | мастер установки (шаг 2) | sqlite |
| `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync` | .env.example | — до установки БД нет, поэтому file |
| `BANQ_INSTALL_LOCK` | тесты | storage/app/installed.json |

## 5. Настройки сайта (таблица settings)

| Ключ | Где меняется | Где используется |
|---|---|---|
| `site.name` | установка, админка | `<title>`, логотип, копирайт |
| `site.tagline`, `site.description` | установка, админка | герой, meta description, подвал |
| `site.logo`, `site.favicon` | админка (загрузка) | partials/logo, partials/head |
| `site.founded_year` | установка, админка | `project_years()` → «2026» или «2024–2026» |
| `site.copyright`, `site.footer_text` | админка | подвал |
| `site.contact_email` | админка | подвал |
| `site.counters` | админка | вставляется как HTML в `<head>` (только admin может менять) |
| `theme.default` | админка | `data-theme` на `<html>` |

## 6. Маршруты

| Метод | URL | Имя | Доступ |
|---|---|---|---|
| GET | `/` | home | все (после установки) |
| GET/POST | `/install`, `/install/database`, `/install/database/test`, `/install/site` | install.* | только до установки |
| GET/POST | `/login`, POST `/logout` | login, logout | гости / вошедшие |
| GET | `/admin` | admin.dashboard | admin, editor |
| GET/PUT | `/admin/settings` | admin.settings.* | admin, editor |
| GET/PUT | `/admin/banks`, `/admin/banks/{bank}/edit` | admin.banks.* | admin, editor |
| GET/POST | `/admin/import`, `/admin/import/preview` | admin.import.* | admin, editor |

## 7. Фронтенд: токены и функции

- CSS-переменные: `--bg --bg-2 --surface --surface-strong --border --border-glow --text --muted --faint --a1..--a4 --ok --bad --grad --radius --blur --ease-out`. Темы: `[data-theme=dark|light]`.
- Классы: `.glass .glow-edge .lift .text-grad .btn(-primary|-ghost) .chip .dot .field .input .table .bank-mark .orb .ticker [data-reveal]` (компоненты в `@layer components`, утилиты Tailwind их переопределяют).
- JS (Alpine): `$store.theme`, директива `x-count`, компонент `calc(rate)`; reveal через IntersectionObserver; подсветка граней `--mx/--my`; параллакс аур. Всё уважает `prefers-reduced-motion`.
- Шрифты: Unbounded / Manrope / JetBrains Mono (Google Fonts).

## 8. Хелперы

| Функция | Что делает |
|---|---|
| `settings($key = null, $default = null)` | значение настройки или сам сервис Settings |
| `project_years()` | годы жизни проекта |
| `rate_format($rate)` | 16.350 → «16,35%» |
| `Bank::findByIdentifier($s)` | поиск по коду/имени/алиасу без учёта регистра, пробелов, дефисов |
| `DepositType::fromLabel($s)` | «Табу» → Savings и т.п. |
