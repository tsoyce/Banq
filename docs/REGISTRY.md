# Реестр проекта TSOY.IN · Banq

> Карта кода: что где лежит, что от чего зависит, какие переменные и функции есть.
> **Правило:** любой PR, который добавляет модуль, таблицу, переменную, маршрут или настройку, обновляет этот файл.
> Метки `A01…` связывают код с записями в [JOURNAL.md](JOURNAL.md) — их же видно в комментариях в коде.

## 1. Дерево проекта

```
app/
├─ Enums/DepositType.php ............ A07 типы вкладов КФГД + подписи из Excel
├─ Enums/RatingKind.php ............. A18 вид рейтинга: author / objective / people (подпись, иконка, цвет серии)
├─ Http/
│  ├─ Middleware/EnsureInstalled.php  A03 глобальный: .env/APP_KEY, редирект на /install
│  ├─ Middleware/EnsureAdmin.php .... A09 роль admin|editor для /admin
│  └─ Controllers/
│     ├─ HomeController.php ......... A10 главная-дашборд
│     ├─ DepositController.php ...... A14 каталог вкладов, карточка, сравнение
│     ├─ BankController.php ......... A18 каталог банков /banks, страница банка
│     ├─ RatingController.php ....... A18 страница рейтингов /ratings
│     ├─ ReviewController.php ....... A18 отзыв (создать/править), «полезно»
│     ├─ Auth/RegisterController .... A18 регистрация посетителей (роль user)
│     ├─ Install/InstallController .. A03 мастер установки (3 шага)
│     ├─ Auth/LoginController ....... A09 вход/выход
│     └─ Admin/{Dashboard,Settings,Bank,Import}Controller  A05 A06 A08 A09
│        Admin/{Rating,Review}Controller ... A18 авторская оценка + данные банка, снимок; модерация отзывов
├─ Models/ Setting, Bank, DepositProduct, DepositRate, Import, User, Rating (A18), Review (A18)
├─ Services/
│  ├─ Installer/ EnvironmentChecker, EnvWriter, DatabaseConfigurator   A03
│  ├─ Import/ DepositSheetParser (чтение), DepositImporter (запись)   A08
│  └─ Deposits/ DepositCatalog (подбор, сетка, история, похожие), DepositFilters (URL ⇄ фильтры),
│               DepositOffer (вклад + лучшая ставка), Income (доход по ГЭСВ/капитализации)   A14
│  ├─ Banks/ BankDirectory (по банку: лучшая ставка ₸, число вкладов, типы)   A18
│  └─ Ratings/ ObjectiveRating (расчёт из данных), PeopleRating (байес. среднее отзывов),
│              RatingBoard (три оценки, места, ▲▼, snapshot), BankScore (DTO)   A18
├─ Support/ Installation (признак установки), Settings (настройки+кеш), Market (срез рынка)  A03 A05 A13
└─ helpers.php ...................... settings(), project_years(), rate_format(), plural()
config/banq.php ..................... версия, путь lock-файла, валюты, сроки
database/migrations/2026_11_26_* .... settings+role, banks, deposit_*, imports, ratings+reviews+review_votes+поля banks (A18)
database/seeders/BankSeeder.php ..... 18 банков РК с алиасами (черновик, сверить с АРРФР)
resources/
├─ css/app.css ...................... A13 дизайн-система v2 «Bento» (токены, карточки, пилюли, темы)
├─ js/app.js ........................ A04 Alpine: тема, x-count, калькулятор, reveal
└─ views/
   ├─ layouts/ app (витрина), admin, install
   ├─ partials/ head, logo, icon (линейные иконки), theme-toggle, backdrop (пусто в v2)
   ├─ home.blade.php, auth/login, auth/register (A18)
   ├─ banks/ index, show · ratings/ index · reviews/ _item, _form   A18
   ├─ partials/ gauge, stars, delta   A18
   ├─ deposits/ index (каталог), _results (список, отдаётся и по fetch), _offer (строка), show (карточка), compare  A14
   ├─ install/ welcome, database, site
   └─ admin/ dashboard, settings/edit, banks/{index,edit}, import/{index,preview}, ratings/{index,edit}, reviews/index (A18)
public/build/ ....................... собранный фронтенд (коммитится: на сервере нет Node)
public/uploads/branding/ ............ логотип и фавикон из админки (в git не попадают)
tests/ .............................. Unit: парсер Excel · Feature: установка, админка, импорт, каталог вкладов (A14), банки и рейтинги (A18)
routes/console.php .................. A18 команда banq:ratings-snapshot + расписание 03:10
deploy.sh ........................... A11 деплой на BrainyCP; A19: автопоиск PHP, кеши только очищаются (ARTISAN_CACHE=1 — строить)
docs/ .............................. PLAN, REGISTRY, JOURNAL, DESIGN (A17 дизайн-гайд), design/guide.html (A17 живые примеры)
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
              ├─ HomeController ──► Support\Market ──► DepositRate(current) → DepositProduct → Bank
              │                                    └─ DepositType
              ├─ DepositController ──► DepositCatalog ──► DepositProduct(active) + currentRates → Bank(active)
              │     ├─ DepositFilters::fromRequest (URL)      ├─ Income (та же формула в app.js depositIncome)
              │     └─ X-Partial: results → deposits._results └─ config banq.kdif_limits (гарантия КФГД)
              ├─ BankController ──► RatingBoard ──► ObjectiveRating ──► Market (ставки), banks (активы, КФГД, год)
              │     │                         ├─ PeopleRating ──► reviews(approved)
              │     │                         └─ ratings (авторская; прошлые снимки → ▲▼)
              │     ├─ BankDirectory ──► Market
              │     └─ DepositCatalog::search(banks:[slug]) → deposits._offer
              ├─ RatingController ──► RatingBoard, BankDirectory, reviews
              ├─ ReviewController (auth) ──► reviews, review_votes
              ├─ HomeController ──► RatingBoard::top() (блок рейтинга)
              ├─ layouts.app (View composer в AppServiceProvider) ──► Market::top() → бегущая строка
              ├─ LoginController ──► users
              └─ /admin (auth + EnsureAdmin)
                    ├─ SettingsController ──► Settings ──► settings, public/uploads/branding
                    ├─ BankController ──► banks
                    ├─ RatingController ──► ratings(author), banks(активы…), RatingBoard::snapshot
                    ├─ ReviewController ──► reviews (модерация)
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
| `deposit_products` | bank_id, type, name, slug, can_replenish/withdraw, online_opening, min/max_amount, term_min/max | импорт (firstOrCreate) | Home, каталог (A14) |
| `deposit_rates` | product_id, currency, term_months, amount_from/to, rate, effective_rate, valid_from, **valid_to (null = текущая)**, source, import_id | импорт | Home, каталог: null в term_months/amount_from = «любой срок/сумма» |
| `imports` | filename, path, rows_*, log json | DepositImporter | админка |
| `banks` (+A18) | assets_bn, capital_bn (млрд ₸), financials_at | админка → Рейтинги → «Оценить» | ObjectiveRating, страница банка |
| `ratings` | bank_id, kind (author/objective/people), score 0–5, breakdown json, votes, verdict, pros/cons json, user_id, **period** (unique bank+kind+period) | Admin\RatingController (author), RatingBoard::snapshot (objective, people) | RatingBoard: текущая авторская, прошлые снимки → ▲▼ |
| `reviews` | user_id, bank_id, deposit_product_id?, score 1–5, title, body, status pending/approved/rejected, reject_reason, reply, reply_author, helpful, moderated_by/at, published_at, ip; unique user+bank | ReviewController, Admin\ReviewController | страница банка, /ratings, PeopleRating |
| `review_votes` | review_id + user_id (PK) | ReviewController::vote | счётчик reviews.helpful |

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
| `PHP_BIN`, `COMPOSER_BIN`, `ARTISAN_CACHE` | окружение при запуске `deploy.sh` (не .env) | автопоиск PHP; `ARTISAN_CACHE=0` (A19) |

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
| GET | `/deposits` | deposits.index | все. Фильтры: `type[]` `currency` `term` `amount` `bank[]` (slug) `replenish` `withdraw` `online` `q` `sort`=rate\|effective\|income\|bank; заголовок `X-Partial: results` → только список |
| GET | `/deposits/compare?ids=1,2,3` | deposits.compare | все; до 4 вкладов, учитывает `amount`/`term` |
| GET | `/deposits/{slug}` | deposits.show | все; 404 для неактивного вклада/банка |
| GET | `/banks` | banks.index | все; `q` (имя/код/алиас), `sort`=objective\|people\|rate\|name (A18) |
| GET | `/banks/{slug}` | banks.show | все; 404 для неактивного банка; `reviews=helpful`, `p` — страница отзывов (A18) |
| GET | `/ratings` | ratings | все; `by`=author\|objective\|people (A18) |
| POST | `/banks/{slug}/reviews` | reviews.store | вошедшие; throttle 6/мин (A18) |
| POST | `/reviews/{review}/vote` | reviews.vote | вошедшие; throttle 30/мин (A18) |
| GET/POST | `/register` | register | гости; throttle 5/мин (A18) |
| GET/POST | `/install`, `/install/database`, `/install/database/test`, `/install/site` | install.* | только до установки |
| GET/POST | `/login`, POST `/logout` | login, logout | гости / вошедшие |
| GET | `/admin` | admin.dashboard | admin, editor |
| GET/PUT | `/admin/settings` | admin.settings.* | admin, editor |
| GET/PUT | `/admin/banks`, `/admin/banks/{bank}/edit` | admin.banks.* | admin, editor |
| GET/POST | `/admin/import`, `/admin/import/preview` | admin.import.* | admin, editor |
| GET, POST, GET/PUT | `/admin/ratings`, `/admin/ratings/snapshot`, `/admin/ratings/{bank}/edit` | admin.ratings.* | admin, editor (A18) |
| GET, PUT, DELETE | `/admin/reviews?status=`, `/admin/reviews/{review}` (action=approve\|reject\|reply) | admin.reviews.* | admin, editor (A18) |

## 7. Фронтенд: токены и функции (v2 «Bento», A13)

- CSS-переменные: `--bg --card --card-2 --border --line --text --muted --faint --ink --ink-text --primary --primary-text --brand-1..3 --ok --bad --warn --grad --grad-soft --grad-warm --radius --radius-sm --ease-out`. Темы: `[data-theme=light|dark]`, по умолчанию светлая (настройка `theme.default`).
- Классы (в `@layer components`): `.card` (= `.glass`), `.card-ink` (тёмная бенто-карточка с крупной цифрой), `.card-soft` (пастельный градиент), `.card-brand` (фирменный градиент), `.plate`, `.lift`, `.btn(-primary|-ghost|-outline|-brand)`, `.pill(.is-active)`, `.chip`, `.dot`, `.icon-tile`, `.list-row`, `.field`, `.input`, `.range`, `.table`, `.bank-mark`, `.meter`, `.topline` + `.ticker`, `.text-grad`, `.label`, `[data-reveal]`.
- Иконки: `@include('partials.icon', ['i' => 'piggy', 'c' => 'w-5 h-5'])`, список имён в самом partial.
- JS (Alpine): `$store.theme`, директива `x-count`, компонент `calc(rate)`; reveal через IntersectionObserver.
- A14: `$store.compare` (до 4 вкладов, localStorage `banq.compare`, панель `.compare-bar` в layouts.app), `depositFilters` (fetch списка без перезагрузки + replaceState), `depositCalc({amount, months, rate, effective})`, функция `depositIncome()`. Классы: `.check-pill .seg .switch .offer .feat .cmp-btn .compare-bar .rate-grid .cmp-table .spark-line .is-loading`. Иконки: check x plus minus filter clock trend down wallet refill withdraw phone lock back. Всё уважает `prefers-reduced-motion`.
- A18: токены `--s-author --s-objective --s-people`; классы `.kicker .num-hero .num-card .cells(.cols-2|.cols-3, .cell-label) .delta(-up|-down|-flat) .gauge .stars .star-pick .dist-row .bank-card .avatar`, `.seg > a(.is-active)` (сегмент-ссылки), `.table .is-sorted`. Partials: `gauge` ($score, $color, $size, $sub), `stars` ($score, $starSize), `delta` ($d, $unit). Иконки: pen users thumb quote award info user.
- Шрифты: Onest (текст и заголовки), JetBrains Mono (бегущая строка).
- **Дизайн-гайд (A17):** правила стиля — `docs/DESIGN.md`, наглядные примеры — `docs/design/guide.html`. Любой новый экран сверяется с чек-листом §12 гайда.
- Раскладка витрины: тёмная лента-тикер → шапка с пилюлями → левое меню 230px → контент; на главной правая колонка 340px.

## 8. Хелперы

| Функция | Что делает |
|---|---|
| `settings($key = null, $default = null)` | значение настройки или сам сервис Settings |
| `project_years()` | годы жизни проекта |
| `rate_format($rate)` | 16.350 → «16,35%» |
| `Bank::findByIdentifier($s)` | поиск по коду/имени/алиасу без учёта регистра, пробелов, дефисов |
| `DepositType::fromLabel($s)` | «Табу» → Savings и т.п. |
| `plural($n, 'вклад', 'вклада', 'вкладов')` | русское склонение по числу (A14) |
| `score_format($v, $decimals = 1)` | оценка 0–5: 4.62 → «4,6» (A18) |
| `money_format($v, $sign = '₸')` | «1 180 394 ₸» с неразрывными пробелами (A18) |
| `Income::calc($amount, $months, $rate, $effective)` | доход: по ГЭСВ, иначе номинал с ежемесячной капитализацией (A14) |

## 9. Конфиг `config/banq.php`

| Ключ | Что | Где используется |
|---|---|---|
| `currencies` | порядок валют | фильтр каталога, сетка ставок |
| `terms` | сроки в месяцах | сравнение (выбор срока) |
| `ratings.objective_weights` | веса критериев объективного рейтинга: rates .40, reliability .30, range .15, terms .15 | ObjectiveRating, методика на /ratings (A18) |
| `ratings.people_prior_weight`, `ratings.people_prior_mean` | байесовское среднее народного: 5 оценок по 3,5 | PeopleRating (A18) |
| `ratings.review_min_length` | минимум символов в отзыве (80) | ReviewController, форма (A18) |
| `kdif_limits.{savings,term,demand,foreign}` | гарантия КФГД, ₸: 20 / 10 / 10 / 5 млн (сверено с kdif.kz 26.11.2026) | карточка, сравнение (A14) |
