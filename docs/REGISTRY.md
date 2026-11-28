# Реестр проекта TSOY.IN · Banq

> Карта кода: что где лежит, что от чего зависит, какие переменные и функции есть.
> **Правило:** любой PR, который добавляет модуль, таблицу, переменную, маршрут или настройку, обновляет этот файл.
> Метки `A01…` связывают код с записями в [JOURNAL.md](JOURNAL.md) — их же видно в комментариях в коде.

## 1. Дерево проекта

```
app/
├─ Admin/ Resource (A24 описание ресурса: колонки, поля, фильтры, массовые действия), ResourceRegistry,
│         Resources/{Country,Bank,DepositProduct,DepositRate,User}Resource   A24
│         Resources/{Card,ServicePackage,PaymentSystem,CardTier}Resource   A32 (раздел меню «Карты»)
├─ Console/Commands/ banq:import, banq:export, banq:schema, banq:token   A22 A23
├─ Enums/DepositType.php ............ A07 типы вкладов КФГД + подписи из Excel
├─ Enums/RatingKind.php ............. A18 вид рейтинга: author / objective / people (подпись, иконка, цвет серии)
├─ Http/
│  ├─ Middleware/EnsureInstalled.php  A03 глобальный: .env/APP_KEY, редирект на /install
│  ├─ Middleware/EnsureAdmin.php .... A09 роль admin|editor для /admin
│  ├─ Middleware/ResolveCountry.php . A21 текущая страна витрины (?country= → cookie → настройка)
│  ├─ Middleware/Typography.php ..... A36 неразрывные пробелы в HTML-ответах (web, после ResolveCountry) → Support/Typograph
│  ├─ Middleware/TrailingSlash.php .. A44 глобальный: /адрес/ → один 301 (сразу по «Редиректам»); вместо правила .htaccess
│  ├─ Middleware/AuthenticateApiToken A23 Bearer-токен для /api/v1, право read|write|delete (delete — A39)
│  └─ Controllers/
│     ├─ HomeController.php ......... A10 главная-дашборд
│     ├─ DepositController.php ...... A14 каталог вкладов, карточка, сравнение
│     ├─ BankController.php ......... A18 каталог банков /banks, страница банка
│     ├─ RatingController.php ....... A18 страница рейтингов /ratings
│     ├─ ReviewController.php ....... A18 отзыв (создать/править), «полезно»
│     ├─ Auth/RegisterController .... A18 регистрация посетителей (роль user) → A28 в кабинет
│     ├─ Account/AccountController .. A28 кабинет: обзор, мои отзывы, избранное, сравнения, настройки
│     ├─ Account/SettingsController . A28 профиль+аватар, почта, пароль, уведомления, удаление аккаунта
│     ├─ ProfileController .......... A28 публичная карточка автора /u/{username}
│     ├─ FavoriteController ......... A28 избранное (вклад/банк), JSON или редирект
│     ├─ ComparisonController ....... A28 сохранённые сравнения: сохранить, переименовать, удалить
│     ├─ Install/InstallController .. A03 мастер установки (3 шага)
│     ├─ Auth/LoginController ....... A09 вход/выход
│     ├─ Api/ExchangeController ..... A23 /api/v1: schema, export, {entity}, import, imports; A39 about (без токена), openapi.json, POST/DELETE {entity}, Idempotency-Key, постранично + updated_since
│     └─ Admin/{Dashboard,Settings,Import}Controller  A05 A08 A09 (A24: обзор со «здоровьем данных»)
│        Admin/ResourceController ... A24 универсальный CRUD по App\Admin\Resource (+ /bulk)
│        Admin/ExchangeController ... A22 обмен данными: check (dry-run) → apply, export, schema, шаблоны, токены
│        Admin/GridController ....... A39 табличный редактор «как Excel»: show, rows (экспорт с _id + подсказки), save (dry-run/запись, allowDelete)
│        Admin/SearchController ..... A24 поиск ⌘K (JSON)
│        Admin/{Rating,Review}Controller ... A18 авторская оценка + данные банка, снимок; модерация отзывов
├─ Models/ Setting, Bank, DepositProduct, DepositRate, Import, User, Rating (A18), Review (A18), Country (A21), ApiToken (A23)
├─ Models/ Setting, Bank, DepositProduct, DepositRate, Import, User, Rating (A18), Review (A18), Favorite (A28), SavedComparison (A28)
├─ Models/ PaymentSystem, CardTier, Card, ServicePackage (A30) · Enums/CardKind (A30: debit/credit/prepaid) · Enums/CardAudience (A54: personal/business — физлицам/бизнесу)
├─ Http/Controllers/ CardController (каталог, карточка, сравнение), PaymentSystemController, ServicePackageController  A33
├─ Services/Database/DatabaseSwitcher  A34 переключение SQLite ⇄ MySQL: миграции, перенос таблиц, копии .env, откат; A38 только таблицы своей базы, explain() ошибок SQL; A41 пересоздание остатков прошлой попытки
├─ Http/Controllers/Admin/DatabaseController  A34 /admin/database (только admin)
├─ Services/Currency/ CurrencyRates (scoped: sync, backfill, ensureFresh → defer, board, available, статус в кеше), RateProvider,
│                    Providers/{Nbrk (KZ), Cbu (UZ), Cbr (RU)}Provider   A40 официальные курсы центробанков
├─ Services/Ticker/ Ticker (scoped: группы ленты, длительность круга), TickerSource, Sources/{Currency,DepositRates,Text}Source   A40
├─ Models/CurrencyRate · Support/CurrencyInfo (название, знак, флаг валюты)   A40
├─ Http/Controllers/Admin/{Ticker,Currency}Controller   A40 /admin/ticker, /admin/currency
├─ views: partials/ticker (лента), partials/fx-card (карточка на главной), admin/ticker/edit, admin/currency/index   A40
├─ Services/Cards/ CardCatalog (scoped), CardFilters, CardScore (оценка 1–5 к рынку), CardOffer  A33
├─ Services/Exchange/Entities/ PaymentSystem, CardTier, Card, ServicePackage Entity + Concerns/ResolvesBank  A31
├─ Models/ Post, PostCategory, Tag, PostReaction, Comment  A37 журнал; Post::LINK_TYPES, cascade(), syncLinks(), about()
├─ Support/Blog/ HtmlSanitizer (белый список тегов/атрибутов), PostRenderer (оглавление, якоря, живые вставки, таблицы)  A37
├─ Http/Controllers/BlogController (лента, рубрика, тег, статья, реакции, комментарии, RSS)  A37
├─ Http/Controllers/Admin/BlogController (загрузка картинок, поиск связей, превью вставки)  A37
├─ Admin/Resources/{Post,PostCategory,Comment}Resource  A37 (раздел меню «Блог»; PostResource — свой редактор admin.posts.editor)
├─ Services/Exchange/Entities/ PostCategoryEntity, PostEntity (tags, links «type:slug», body или body_markdown)  A37
├─ Models/Page (posts.type = page; nav(), кеш pages.nav), Models/Redirect (normalize, match, remember)  A42
├─ Admin/Resources/{Page,Redirect}Resource  A42 (Page — тот же редактор admin.posts.editor с $isPage; Redirect — Сайт → Редиректы)
├─ Http/Controllers/PageController .. A42 Route::fallback: страница /{slug} → редирект со старого адреса → 404; redirectFor() — и для 404 на маршрутах (bootstrap/app.php)
├─ Services/WordPress/ WpSource (REST API → снимок), WxrReader (файл экспорта WXR → тот же снимок), WpContent (scan — проверка на взлом,
│                     transform — HTML WordPress → журнал), WpMedia (проверка по сигнатуре, код внутри, перекодирование GD без EXIF, ≤ 2400px),
│                     WpImporter (scan/plan → step по бюджету времени → report; состояние storage/app/wordpress/{state,snapshot}.json)  A43; переименованные оригиналы «-scaled-1» — A44
├─ Console/Commands/WordPressImportCommand  A43 banq:wp-import --url= | --file= [--dry-run] [--resume] [--update] [--no-external] [--author=]
├─ Http/Controllers/Admin/WordPressController  A43 /admin/wordpress (только admin): scan, step (JSON), report (JSON-файл), reset
├─ Support/Founder ................. A55 основатель (settings author.user_id, иначе первый админ): user(), is(), urlFor() — подпись автора ведёт на /author
├─ Services/Social/SocialStats ...... A55 соцсети: NETWORKS (подпись, цвет, способ счёта), profiles() (число API → ручное), refresh() (YouTube API, t.me / Bot API, VK API, GitHub), ensureFresh() (defer, 12 ч), compact(), handle()
├─ Http/Controllers/AuthorController  A55 /author: профиль, соцсети, публикации автора (X-Partial: posts, ?c=рубрика), поддержка
├─ Http/Controllers/Admin/AuthorController  A55 /admin/author: автор, соцсети (порядок, ручные числа, видимость), ключи API (не отдаются в HTML), донаты; refresh
├─ views: author/show, admin/author/edit, partials/{socials (grid|row), social-icon (знаки сетей), donate (полный | compact)} · css/author.css · public/images/brand/ta-500.png (монограмма TA)  A55
├─ Notifications/ReviewModerated .... A28 письмо автору: опубликован / отклонён / ответ банка (если включено)
├─ Services/
│  ├─ Installer/ EnvironmentChecker, EnvWriter, DatabaseConfigurator   A03
│  ├─ Import/ DepositSheetParser (чтение), DepositImporter (запись)   A08 — старая «матрица Excel», A21 учитывает страну
│  ├─ Exchange/ DataExchange (parse/import/export/render), EntityRegistry (+schema), Entity (поля, типы, normalize, persist),
│  │            Entities/{Country,Bank,DepositProduct,DepositRate}Entity, Formats/{Json,Csv,Xlsx}Format (+Tabular), A39: Formats/{Xml,Yaml,Ndjson}Format, CsvFormat("\t") = TSV, OpenApi (спецификация из реестра); служебные поля строки _id/_delete (DataExchange::meta, Entity::locate/remove),
│  │            ImportOptions (mode, dryRun, country, source), ImportContext, ImportReport, RowError   A22
│  └─ Deposits/ DepositCatalog (подбор, сетка, история, похожие), DepositFilters (URL ⇄ фильтры),
│               DepositOffer (вклад + лучшая ставка), Income (доход по ГЭСВ/капитализации)   A14
│  ├─ Banks/ BankDirectory (по банку: лучшая ставка ₸, число вкладов, типы)   A18
│  └─ Ratings/ ObjectiveRating (расчёт из данных), PeopleRating (байес. среднее отзывов),
│              RatingBoard (три оценки, места, ▲▼, snapshot), BankScore (DTO)   A18
├─ Support/ Installation (признак установки), Settings (настройки+кеш), Market (срез рынка, A21 — по стране)  A03 A05 A13
│          CurrentCountry (текущая страна, scoped)  A21
├─ Support/Typograph.php ........... A36 экранная типографика русского текста (только текст между тегами)
└─ helpers.php ...................... settings(), project_years(), rate_format(), plural(), country(), currency_symbol() (A21), percent_format() (A36)
├─ Support/ Installation (признак установки), Settings (настройки+кеш), Market (срез рынка)  A03 A05 A13
│          Username (генерация/правила адреса профиля), Countries (страны для анкеты, ISO-2)  A28
└─ helpers.php ...................... settings(), project_years(), rate_format(), plural()
config/banq.php ..................... версия, путь lock-файла, валюты, сроки
database/migrations/2026_11_26_* .... settings+role, banks, deposit_*, imports, ratings+reviews+review_votes+поля banks (A18)
database/migrations/2026_11_27_003000_create_card_tables  A30 карты, пакеты, платёжные системы (+ PaymentSystemSeeder: 10 систем, 32 уровня)
database/migrations/2026_11_27_* .... countries + banks.country_id/deposit_insured (A21), api_tokens (A23), поля imports (A22)
lang/ru/validation.php .............. A22 русские сообщения валидации
routes/api.php ...................... A23 /api/v1
database/seeders/BankSeeder.php ..... 18 банков РК с алиасами (черновик, сверить с АРРФР)
resources/
├─ css/app.css ...................... A13 дизайн-система v2 «Bento» (токены, карточки, пилюли, темы)
├─ js/app.js ........................ A04 Alpine: тема, x-count, калькулятор, reveal
├─ js/sheet.js ...................... A39 табличный редактор: класс Grid (виртуальная отрисовка, выделение, правка, буфер TSV, отмена) + Alpine.data('sheet')
├─ css/blog.css ..................... A37 журнал: лента, обложки, статья (.prose-banq), реакции, комментарии, вставки .embed, редактор .ed-*
├─ js/blog.js ....................... A37 loadMore, share, $store.post (реакции), readProgress, tocSpy
├─ js/editor.js ..................... A37 редактор Tiptap (грузится отдельным чанком только на странице [data-post-editor])
└─ views/
   ├─ layouts/ app (витрина), admin, install
   ├─ partials/ head, logo, icon (линейные иконки), theme-toggle, backdrop (пусто в v2)
   ├─ home.blade.php, auth/login, auth/register (A18)
   ├─ banks/ index, show · ratings/ index · reviews/ _item, _form   A18
   ├─ partials/ gauge, stars, delta   A18
   ├─ account/ _shell (шапка+вкладки), index, reviews, _review-row, favorites, comparisons, settings  A28
   ├─ profile/ show (публичный профиль)  A28 · partials/ avatar, fav (кнопка-сердце)  A28
   ├─ deposits/ index (каталог), _results (список, отдаётся и по fetch), _offer (строка), show (карточка), compare  A14
   ├─ install/ welcome, database, site
   ├─ cards/{index,_results,_offer,show,compare}, payment-systems/{index,show}, packages/{index,_card,show}  A33 · cards/_split (вкладки «Физлицам/Бизнесу» × виды, на странице системы и банка) A54
   ├─ partials/plastic (объёмная карта, x-tilt), partials/ps-mark (знак платёжной системы; A54 — загруженный logo / logo_alt)  A33 · partials/tier-badge (значок уровня: загруженный или SVG «мини-карта», металл по ступени) A54
   ├─ partials/country-switch ....... A21 переключатель стран в ленте
   └─ admin/ dashboard, settings/edit, import/{index,preview}, ratings/{index,edit}, reviews/index (A18)
            resource/{index,form,field,aside-bank,aside-deposit} (A24), aside-payment-system (A32), database/index (A34), exchange/{index,report,_rows,show,tokens} (A22 A23), exchange/grid (A39)
            posts/editor (A37: редактор публикации — Tiptap, обложка, связи с каскадом, SEO)
   ├─ blog/ index, show, rss, _card, _cover, _meta, _category, _grid, _reactions, _share, _linked, _comment(s), _about,
   │        embeds/{bank,deposit,card,package,payment_system}  A37 (_about — блок «В журнале» на страницах банка, вклада, карты, пакета, системы)
public/build/ ....................... собранный фронтенд (коммитится: на сервере нет Node)
public/uploads/branding/ ............ логотип и фавикон из админки (в git не попадают)
public/flags/{4x3,1x1}/*.svg ........ A46 библиотека флагов flag-icons 7.5 (MIT, LICENSE.txt рядом), 271 код: страны ISO + eu, un, gb-sct…
public/uploads/flags/ ............... A46 свои флаги из админки «Сайт → Флаги» (в git не попадают)
app/Support/Flags.php ............... A46 флаги: url/img/name, forCurrency (USD→us, EUR→eu), свои флаги (настройка flags.custom), проверка загрузки (SVG без скриптов)
app/Support/AdminScope.php .......... A46 страна в плашке админки (session admin.country) + страны сотрудника (users.admin_countries): apply/permits/clampCode
resources/views/partials/flag ....... A46 вывод флага: code|currency, round, c, fallback
tests/ .............................. Unit: парсер Excel · Feature: установка, админка, импорт, каталог вкладов (A14), банки и рейтинги (A18),
                                       обмен данными + API (A22 A23: DataExchangeTest), ресурсы админки и страны на витрине (A24: AdminResourceTest)
public/uploads/avatars/ ............. A28 фото пользователей (в git не попадают)
public/uploads/blog/wp/ГГГГ/ММ/ ...... A43 файлы, перенесённые с WordPress (в git не попадают)
tests/ .............................. Unit: парсер Excel · Feature: установка, админка, импорт, каталог вкладов (A14), банки и рейтинги (A18), профиль и кабинет (A28), карты (A33: CardCatalogTest; fixtures/cards-demo.json — вымышленные условия; A54: CardAudienceTest)
routes/console.php .................. A18 команда banq:ratings-snapshot + расписание 03:10
deploy.sh ........................... A11 деплой на BrainyCP; A19: автопоиск PHP, кеши только очищаются (ARTISAN_CACHE=1 — строить); A29: путь SQLite из chroot; A35: DEPLOY_SKIP_PULL
deploy/banq-deploy .................. A35 принудительная команда ключа выкладки (копия в /usr/local/sbin): бэкап базы, deploy.sh от tsoy, проверка, откат
.github/workflows/deploy.yml ........ A35 автодеплой после зелёных Tests на main (секреты DEPLOY_HOST, DEPLOY_SSH_KEY, DEPLOY_KNOWN_HOSTS; docs/DEPLOY.md)
docs/ .............................. PLAN, REGISTRY, JOURNAL, DESIGN (A17 дизайн-гайд), design/guide.html (A17 живые примеры), API.md (A23)
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
              ├─ Account\AccountController (auth) ──► users, reviews, favorites → DepositCatalog::bestRate, RatingBoard, BankDirectory; saved_comparisons
              ├─ Account\SettingsController ──► users, public/uploads/avatars
              ├─ ProfileController ──► users(is_public), reviews(approved)
              ├─ FavoriteController / ComparisonController (auth) ──► favorites / saved_comparisons
              ├─ Admin\ReviewController ──► ReviewModerated (если user->wants(...)) ──► mail (MAIL_*)
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
Витрина ──► ResolveCountry ──► CurrentCountry ──► countries; country()/currency_symbol() в шаблонах
           Market, DepositCatalog, RatingBoard, BankDirectory, Home ──► Bank::inCountry() (A21)

Обмен данными (A22/A23):
  /admin/exchange (check → apply) ─┐
  /admin/exchange/grid (A39) ──────┤
  php artisan banq:import/export ──┼─► DataExchange ──► Formats (json/ndjson/xml/yaml/csv/tsv/xlsx) ──► пакет {entity: rows}
  /api/v1/import, /export ─────────┘        └─► EntityRegistry ──► Country/Bank/DepositProduct/DepositRate Entity
     (AuthenticateApiToken → api_tokens)          normalize → find (естественный ключ) → persist (дифф) ──► imports
  ResourceController::storeRate ──► DataExchange (новая ставка закрывает старую)
  A31: … ──► PaymentSystem(tiers) → CardTier → Card(networks «СИСТЕМА:уровень») → ServicePackage(cards)
       Entity::relations()/syncRelations() — связи многие-ко-многим, изменения попадают в дифф

Карты (A33): /cards ──► CardController ──► CardCatalog ──► Card::active()->inCountry() + CardFilters + CardScore
  /cards/{slug} ──► CardScore, RatingBoard (рейтинги банка), similar · /cards/compare ◄── $store.cardCompare
  /payment-systems ──► PaymentSystem + tiers + cards страны · /packages ──► ServicePackage::inCountry()
  banks/show, home, account/favorites ──► Card, ServicePackage

Админка (A24, A32): /admin/{countries|banks|deposits|rates|users|cards|packages|payment-systems|card-tiers} ──► ResourceController ──► ResourceRegistry ──► Resource

Журнал (A37): /blog ──► BlogController ──► Post::feed() (published + forCountry) ──► blog/index, _grid (подгрузка X-Partial)
  /blog/{slug} ──► PostRenderer ──► HtmlSanitizer-чистый body + embeds (BankDirectory, DepositCatalog::bestRate, Card, ServicePackage)
  Админка posts ──► PostResource::saving (санитайзер, обложка, статус) / saved ──► Tag::idsFor, Post::syncLinks
  Связи: postables (post_id, postable_type, postable_id, is_primary). Каскад Post::cascade():
    card → банк, пакеты, платёжные системы, уровни · deposit → банк · package → банк, карты · card_tier → система
  Страницы банка/вклада/карты/пакета/системы ──► Post::about($model) ──► blog/_about; главная ──► 4 последние
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
| `countries` (A21) | code ISO (unique), slug, name + name_genitive/locative/en/native, flag (эмодзи — только для текста), **flag_code** (A46: код SVG-флага, пусто — по ISO), currency, currency_symbol, currencies json, locale, timezone, phone_code, regulator json, central_bank json, deposit_guarantee json {short, name, url, limits{savings,term,demand,foreign}}, meta, is_active, sort | миграция (KZ), админка, обмен | CurrentCountry, все витрины, Country::guaranteeLimit |
| `banks` (+A21) | **country_id** (FK, restrict), unique(country_id, code), `is_kdif_member` → **deposit_insured** | админка, обмен | всё, что выше, — по стране |
| `api_tokens` (A23) | name, token_hash sha256 (unique), prefix, abilities json [read, write], last_used_at, last_ip, expires_at, user_id | админка, banq:token | AuthenticateApiToken |
| `imports` (+A22) | api_token_id, format (json/ndjson/xml/yaml/csv/tsv/xlsx/grid/matrix/form; A39), rows_updated включает удалённые (A39), source (admin/cli/api), mode, rows_failed, stats json; kind = список сущностей | DataExchange, DepositImporter | админка (история, журнал), API |
| `users` (+A28) | username (unique, /u/…), avatar (путь в public), city, country_code ISO-2, bio ≤280, is_public, notify json {review_status, review_reply, rate_changes}, last_seen_at | регистрация (username — хук creating), кабинет → настройки | шапка, кабинет, профиль, отзывы |
| `users` (+A46) | **admin_countries** json — id стран сотрудника (null — все); ставит только админ без ограничений | админка → Пользователи | AdminScope: списки, правка, выгрузки, таблица, импорт по умолчанию |
| `favorites` | user_id, favoritable_type (`deposit`\|`bank`\|`card` A30), favoritable_id; unique тройка | FavoriteController | кабинет, кнопки-сердца (User::hasFavorite) |
| `payment_systems` (A30) | code (unique), slug, name, short_name, aliases json, scope international/regional/national, home_country, countries json, color, color_2, logo, logo_alt (A54: второй вариант знака), website, founded_year, headquarters, wallets json, description, meta, is_active, sort | миграция (сидер), админка, обмен | /payment-systems, карты |
| `card_tiers` (A30) | payment_system_id, code, slug (unique), name, level 1–6, segment, description, privileges json, badge (A54: свой значок), badge_text (A54: подпись, «MIR Privilege»), meta (A54: tone — металл значка); unique(payment_system_id, code) | сидер, админка, обмен | карточка карты, страница системы |
| `cards` (A30) | bank_id, kind (CardKind), audience (A54: CardAudience personal/business, индекс), name, slug, segment (без business — с A54 это audience), format, currencies, issue/annual/monthly fee, fee_note, cashback_max/base/note, balance_rate, credit_limit_max, grace_days, credit_rate(_effective), min_payment, cash_fee, free_cash_limit, transfer_fee, online_opening, delivery, validity_years, age_from, features json, color, color_2, image, description, conditions json, source_url, is_active, sort | админка, обмен | /cards, банк, главная, избранное |
| `card_networks` (A30) | card_id, payment_system_id, card_tier_id nullable | CardEntity (networks), CardResource (флажки) | Card::networks() |
| `service_packages` (A30) + `card_service_package` | bank_id, name, slug, segment, monthly/annual fee, fee_note, perks json, conditions, description, color, source_url, is_active, sort | админка, обмен | /packages, карточка карты, банк |
| `saved_comparisons` | user_id, title, product_ids json, amount, term_months | ComparisonController (≤30 на пользователя) | кабинет → Сравнения |
| `currency_rates` (A40) | country_id, currency ISO, name, nominal (курс за N единиц), rate, change (к прошлому курсу по источнику), date, source (nbrk/cbu/cbr); unique(country_id, currency, date) | CurrencyRates::sync (cron `banq:fx-sync`, заход посетителя, админка) | карточка курсов, лента, админка → Курсы валют |
| `post_categories` (A37) | slug, name, description, color, icon, is_active, sort (сидер: news, analytics, reviews, guides, opinion) | миграция, админка, обмен | лента, рубрики |
| `posts` (A37) | country_id?, category_id?, author_id?, kind (article/news/review/guide/analytics), status draft/published, published_at (в будущем — отложено), title, slug, lead, body (HTML после санитайзера), cover, cover_alt, cover_caption, reading_minutes, is_featured (один), allow_comments, views, seo_title, seo_description, source_name, source_url, meta | редактор, обмен | /blog, главная, страницы продуктов |
| `posts` + A42 | **type** post/page, **in_feed** (страница в ленте), **nav** aside/footer/null, **nav_sort**; meta.wp_key / meta.wp — откуда перенесено (A43) | PageResource, WpImporter | Post — глобальный скоуп `type` (публикации + страницы in_feed), Page — только страницы; /{slug} |
| `redirects` (A42) | from_path (нормализованный, кириллица как есть), from_hash (sha256, уникальный), to_url, status 301/302, source wp/manual, note, hits, last_hit_at | RedirectResource, WpImporter | PageController (fallback), 404 на маршрутах, /?p= на главной |
| `tags`, `post_tag` (A37) | slug, name · post_id + tag_id | Tag::idsFor (редактор, обмен) | /blog/tag/{slug} |
| `postables` (A37) | post_id, postable_type (morph: bank, deposit, card, package, payment_system, card_tier), postable_id, **is_primary** (выбрано автором; false — добавлено каскадом) | Post::syncLinks | Post::about(), блок «О ком материал» |
| `post_reactions` (A37) | post_id, user_id, kind (like/fire/think/money); unique тройка | BlogController::react | реакции |
| `comments` (A37) | post_id, user_id, parent_id (один уровень ответов), body, status approved/pending/hidden, ip | BlogController::comment, админка | статья, модерация |

## 4. Переменные окружения (.env)

| Переменная | Кто задаёт | Значение по умолчанию |
|---|---|---|
| `APP_KEY` | EnsureInstalled (автоматически при первом запросе) | — |
| `APP_NAME`, `APP_ENV=production`, `APP_DEBUG=false` | мастер установки (шаг 3) | из .env.example |
| `APP_URL` | мастер установки (шаг 2) | http://localhost |
| `APP_TIMEZONE` | вручную | Asia/Almaty |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_PREFIX` | мастер установки (шаг 2); A34: админка → База данных (копии прежнего .env — `storage/app/env-backups`) | sqlite |
| `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync` | .env.example | — до установки БД нет, поэтому file |
| `DB_ENGINE` | вручную (необязательно) | InnoDB (A20: MyISAM на BrainyCP не вмещает ключи) |
| `BANQ_INSTALL_LOCK` | тесты | storage/app/installed.json |
| `BANQ_TYPOGRAPH` | phpunit.xml (`false`), вручную | true — A36 middleware типографики включён |
| `BANQ_FX_AUTO_REFRESH` | .env (A40) | `true` — обновлять курсы при первом заходе за день; в тестах `false` |
| `BANQ_SOCIAL_AUTO_REFRESH` | .env (A55) | `true` — счётчики соцсетей старше 12 ч обновляются после ответа посетителю; в тестах `false` |
| `BANQ_YOUTUBE_API_KEY`, `BANQ_TELEGRAM_BOT_TOKEN`, `BANQ_VK_SERVICE_TOKEN` | .env (A55), необязательно | пусто; ключи из админки («Автор и соцсети») важнее |
| `PHP_BIN`, `COMPOSER_BIN`, `ARTISAN_CACHE`, `DEPLOY_SKIP_PULL` | окружение при запуске `deploy.sh` (не .env) | автопоиск PHP; `ARTISAN_CACHE=0` (A19); `DEPLOY_SKIP_PULL=0` (A35: автодеплой сам ставит коммит) |

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
| `country.default` | админка → Настройки (A21) | CurrentCountry: страна нового посетителя (slug, по умолчанию первая по sort) |
| `ticker.enabled`, `ticker.sources` (порядок ключей из `banq.ticker.sources`), `ticker.text`, `ticker.speed` 1–10 | админка → Бегущая строка (A40) | Ticker → partials/ticker в шапке |
| `fx.currencies` {"KZ": ["USD","EUR","RUB","CNY"]}, `fx.home_card` | админка → Курсы валют (A40) | CurrencyRates::codes(), карточка на главной |
| `flags.custom` {код: {name?, url?}} | админка → Сайт → Флаги (A46) | Flags::url/name — свой флаг поверх библиотечного или новый код |
| `blog.premoderation` | админка → Настройки → Журнал (A37) | новые комментарии читателей — на модерацию (pending) |
| `author.user_id`, `author.headline`, `author.slogan`, `author.about` | админка → Сайт → Автор и соцсети (A55) | Founder, страница /author, подпись в статье |
| `social.enabled`, `social.networks` [{network, url, label, followers (ручное), visible}] | там же (A55); по умолчанию — 6 сетей из шапки tsoy.in | SocialStats → /author, подвал |
| `social.counts` {network: {count, at, source, error}}, `social.refreshed_at` | SocialStats::refresh (A55: cron 04:20, кнопка, заход посетителя) | число подписчиков с API |
| `social.keys` {youtube, telegram, vk} | админка (A55), в HTML не выводятся | SocialStats::key() → иначе config banq.social.keys |
| `donate.enabled`, `donate.title`, `donate.text`, `donate.items` [{label, currency, url, details, note}] | админка (A55); по умолчанию Т-Банк RUB и ForteBank KZT | partials/donate: /author#donate, сайдбар статьи основателя, подвал |

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
| GET | `/account`, `/account/{reviews,favorites,comparisons,settings}` | account.* | вошедшие (A28); `reviews?status=` |
| PUT | `/account/settings/{profile,email,password,notifications}`, DELETE `/account/settings/avatar`, DELETE `/account` | account.settings.*, account.destroy | вошедшие; почта/пароль — throttle 6/мин, удаление — 3/мин, админа удалить нельзя (A28) |
| GET | `/u/{username}` | profile.show | все, если профиль открыт; владелец и сотрудники — всегда (A28) |
| POST | `/favorites` (type, id) | favorites.toggle | вошедшие; JSON при Accept: application/json (A28) |
| POST, PUT, DELETE | `/comparisons`, `/comparisons/{comparison}` | comparisons.* | вошедшие, только свои (A28) |
| GET/POST | `/install`, `/install/database`, `/install/database/test`, `/install/site` | install.* | только до установки |
| GET/POST | `/login`, POST `/logout` | login, logout | гости / вошедшие |
| GET | `/admin` | admin.dashboard | admin, editor |
| GET/PUT | `/admin/settings` | admin.settings.* | admin, editor |
| GET/POST | `/admin/import`, `/admin/import/preview` | admin.import.* | admin, editor |
| GET, POST, GET/PUT | `/admin/ratings`, `/admin/ratings/snapshot`, `/admin/ratings/{bank}/edit` | admin.ratings.* | admin, editor (A18) |
| GET, PUT, DELETE | `/admin/reviews?status=`, `/admin/reviews/{review}` (action=approve\|reject\|reply) | admin.reviews.* | admin, editor (A18) |
| любой | все публичные страницы `?country=kz` | — | A21: выбрать страну (запоминается в cookie `banq_country`) |
| GET, GET, POST, POST, GET, PUT, DELETE | `/admin/{key}`, `/{key}/create`, `/{key}`, `/{key}/bulk`, `/{key}/{id}/edit`, `/{key}/{id}` — key: countries\|banks\|deposits\|rates\|users\|cards\|packages\|payment-systems\|card-tiers (A32) | admin.resource.* | admin, editor; users — только admin (A24). Список: `q`, фильтры ресурса, `sort`, `dir`, `per` |
| GET | `/admin/search?q=` | admin.search | JSON для ⌘K (A24) |
| GET, POST, POST, POST | `/admin/database`, `/database/test`, `/database/switch`, `/database/restore` | admin.database.* | только admin (A34); switch/restore — throttle 5/мин, switch требует `confirm` |
| GET, POST, POST, GET, GET, GET, GET | `/admin/exchange?tab=`, `/exchange/check`, `/exchange/apply`, `/exchange/export`, `/exchange/schema`, `/exchange/template/{entity}/{format}`, `/exchange/history/{import}` | admin.exchange.* | admin, editor (A22) |
| GET, POST, DELETE | `/admin/exchange/tokens`, `/exchange/tokens/{token}` | admin.exchange.tokens* | выпуск/отзыв — только admin (A23) |
| GET | `/api/v1/schema`, `/api/v1/export`, `/api/v1/imports`, `/api/v1/imports/{id}`, `/api/v1/{entity}` | api.* | токен с правом read (A23), throttle 120/мин |
| POST | `/api/v1/import` | api.import | токен с правом write (A23); `allow_delete=1` — ещё право delete (A39) |
| GET | `/api/v1`, `/api/v1/openapi.json` | api.about, api.openapi | без токена: руководство для агентов и OpenAPI 3.1 (A39) |
| POST, DELETE | `/api/v1/{entity}` | api.entity.upsert, api.entity.destroy | write; DELETE — ещё право delete (A39) |
| GET, GET, GET, POST | `/admin/exchange/grid`, `/exchange/grid/{entity}`, `/exchange/grid/{entity}/rows`, `/exchange/grid/{entity}` | admin.exchange.grid* | admin, editor — табличный редактор (A39) |
| GET | `/cards?audience=&kind=&system=&bank=&currency=&segment=&feat[]=&free=&sort=` (A54: audience по умолчанию personal, `business`, `all`), `/cards/compare?ids=`, `/cards/{card:slug}?from=ps:{slug}|bank` (A54: хлебные крошки назад) | cards.index, cards.compare, cards.show | все (A33); список — частичный ответ при `X-Requested-With` |
| GET | `/payment-systems`, `/payment-systems/{system:slug}` | payment-systems.* | все (A33) |
| GET | `/packages`, `/packages/{package:slug}` | packages.* | все (A33) |
| GET, PUT | `/admin/ticker` | admin.ticker.* | admin, editor (A40) |
| GET, PUT, POST | `/admin/currency`, `/admin/currency/{country}/sync` (`days=30` — история) | admin.currency.* | admin, editor (A40); sync — throttle 10/мин |
| GET, POST, POST, GET, POST | `/admin/wordpress`, `/wordpress/scan`, `/wordpress/step`, `/wordpress/report`, `/wordpress/reset` | admin.wordpress.* | только admin (A43); scan — throttle 10/мин |
| POST | `/admin/country` (country=id\|пусто, _back) | admin.country | admin, editor (A46): страна в плашке админки |
| GET, POST, DELETE | `/admin/flags`, `/admin/flags/{code}` | admin.flags.* | admin, editor (A46): библиотека флагов, свой флаг (throttle 30/мин), вернуть библиотечный |
| ANY | fallback `/{slug}` и любой несуществующий адрес | page.fallback | A42: страница → редирект → 404 |
| GET | `/blog?q=&kind=`, `/blog/c/{category:slug}`, `/blog/tag/{tag:slug}`, `/blog/rss.xml`, `/blog/{post:slug}` | blog.index, blog.category, blog.tag, blog.rss, blog.show | все (A37); черновик — только сотрудникам; лента — частичный ответ при `X-Partial: posts` |
| POST, POST, DELETE | `/blog/{post}/react`, `/blog/{post}/comments`, `/blog/comments/{comment}` | blog.react, blog.comment, blog.comments.destroy | вошедшие (A37); лимиты `blog-reactions` 60/мин, `blog-comments` 6/мин |
| POST, GET, GET | `/admin/blog/upload`, `/admin/blog/lookup?q=&type=&keys=`, `/admin/blog/embed?type=&slug=` | admin.blog.* | admin, editor (A37): картинка в текст, поиск связей с каскадом, превью вставки |
| GET | `/author?c=рубрика` | author | все (A55): страница основателя; публикации — частичный ответ при `X-Partial: posts` |
| GET | `/donate` | donate | все (A55): 302 → `/author#donate` |
| GET, PUT, POST | `/admin/author`, `/admin/author/refresh` | admin.author.* | admin, editor (A55); refresh — throttle 10/мин |

## 7. Фронтенд: токены и функции (v2 «Bento», A13)

- CSS-переменные: `--bg --card --card-2 --border --line --text --muted --faint --ink --ink-text --primary --primary-text --brand-1..3 --ok --bad --warn --grad --grad-soft --grad-warm --radius --radius-sm --ease-out`. Темы: `[data-theme=light|dark]`, по умолчанию светлая (настройка `theme.default`).
- Классы (в `@layer components`): `.card` (= `.glass`), `.card-ink` (тёмная бенто-карточка с крупной цифрой), `.card-soft` (пастельный градиент), `.card-brand` (фирменный градиент), `.plate`, `.lift`, `.btn(-primary|-ghost|-outline|-brand)`, `.pill(.is-active)`, `.chip`, `.dot`, `.icon-tile`, `.list-row`, `.field`, `.input`, `.range`, `.table`, `.bank-mark`, `.meter`, `.topline` + `.ticker`, `.text-grad`, `.label`, `[data-reveal]`.
- Иконки: `@include('partials.icon', ['i' => 'piggy', 'c' => 'w-5 h-5'])`, список имён в самом partial.
- JS (Alpine): `$store.theme`, директива `x-count`, компонент `calc(rate)`; reveal через IntersectionObserver.
- A14: `$store.compare` (до 4 вкладов, localStorage `banq.compare`, панель `.compare-bar` в layouts.app), `depositFilters` (fetch списка без перезагрузки + replaceState), `depositCalc({amount, months, rate, effective})`, функция `depositIncome()`. Классы: `.check-pill .seg .switch .offer .feat .cmp-btn .compare-bar .rate-grid .cmp-table .spark-line .is-loading`. Иконки: check x plus minus filter clock trend down wallet refill withdraw phone lock back. Всё уважает `prefers-reduced-motion`.
- A18: токены `--s-author --s-objective --s-people`; классы `.kicker .num-hero .num-card .cells(.cols-2|.cols-3, .cell-label) .delta(-up|-down|-flat) .gauge .stars .star-pick .dist-row .bank-card .avatar`, `.seg > a(.is-active)` (сегмент-ссылки), `.table .is-sorted`. Partials: `gauge` ($score, $color, $size, $sub), `stars` ($score, $starSize), `delta` ($d, $unit). Иконки: pen users thumb quote award info user.
- A28: классы `.avatar.is-gen` (буква на градиенте, `--h` — оттенок пользователя), `.avatar-xl`, `.fav-btn(.is-on, .btn-like)`, `.pop` (меню в шапке), `.tabs` (вкладки кабинета), `.toast` (session status), `.level`, `.status-{approved,pending,rejected}` + `.status-dot`, `.cells.cols-4`, `.empty`. Alpine `favorite(on)` — переключение избранного fetch'ем. Иконки: heart bell camera trash pin grid calendar link.
- A33: `$store.cardCompare` (localStorage `banq.cardCompare`, та же фабрика `compareStore`), `cardFilters` (общий `catalogFilters` с вкладами), директива `x-tilt` (наклон и блик). Классы `.plastic*`, `.mini-card`, `.ps-*`, `.ps-tile`, `.level-dots`, `.card-offer`, `.key-nums`, `.perk`, `.score-bar`, `.ps-card`, `.tier-step`. Иконки: gift percent wifi plane coins cash layers crown.
- A54: `.aud-tabs` (переключатель «Физлицам / Бизнесу / Все»), `.tier-badge` (`.is-img`), `.ps-logo`. Поле админки `type => 'image'` (`Resource::absorbUploads` → `public/uploads/{ключ ресурса}/`, SVG проверяется `Resource::safeSvg`; рядом остаётся поле пути/URL).
- A36: Blade-компонент `<x-hscroll label="…">` (resources/views/components/hscroll.blade.php, Alpine `hscroll`) — горизонтальная лента без скроллбара с фейдом и стрелками (`.hscroll`, `.hscroll-end`); `partials.catalog-soon` ($title, $text, $items, $links, $class) — пустой каталог; классы `.qs-bar .qs-field .qs-label .qs-chevron .qs-submit` (быстрый подбор на главной), `.hero-title .hero-rate` (кегль в `cqi`), `.ink-plate`, `.offers` (контейнер) + области `.offer-head/-rate/-feats/-actions`. `partials.fav`: параметр `$wide` вместо `$label`. `CardKind::genitivePlural()`; `.cmp-scroll` (закреплённая колонка сравнения), `.cb-short/.cb-long` (панель сравнения на телефоне). Глобально: `text-wrap: balance` у заголовков, `pretty` у абзацев, тонкие скроллбары.
- A37 журнал: `.blog-hero`, `.post-grid` (`.is-3`), `.post-card`, `.post-cover` (сгенерированная обложка с цветом рубрики, если картинки нет), `.read-progress`, `.post-sheet/.post-title/.post-byline`, `.toc` (+ `.toc-inline` на телефоне), `.reactions`, `.prose-banq` (типографика статьи: врезки `aside[data-tone=info|tip|warn|money]`, `.prose-table`, `td.num`), `.embed` (живая карточка продукта в тексте), `.ed-*` (редактор, `.ed-focus` — режим фокуса). Иконки: eye, share, copy, rss, tag, send, image, bold…redo, table, hr, bulb, box, save, highlight, maximize, reply.
- A32 админка: тип поля `checks` (флажки, группы «Группа|подпись»), поля `virtual` с `value`-замыканием, хук `Resource::saved($model, $data)`.
- Шрифты: Onest (текст и заголовки), JetBrains Mono (бегущая строка).
- **Дизайн-гайд (A17):** правила стиля — `docs/DESIGN.md`, наглядные примеры — `docs/design/guide.html`. Любой новый экран сверяется с чек-листом §12 гайда.
- Раскладка витрины: тёмная лента-тикер → шапка с пилюлями → левое меню 230px → контент; на главной правая колонка 340px.

## 8. Хелперы

| Функция | Что делает |
|---|---|
| `settings($key = null, $default = null)` | значение настройки или сам сервис Settings |
| `project_years()` | годы жизни проекта |
| `rate_format($rate)` | 16.350 → «16,35%»; A36: всегда два знака — 18 → «18,00%» |
| `percent_format($v)` | A36: без лишних нулей — 10 → «10%», 1.5 → «1,5%» (кэшбэк, доли) |
| `Bank::findByIdentifier($s)` | поиск по коду/имени/алиасу без учёта регистра, пробелов, дефисов |
| `DepositType::fromLabel($s)` | «Табу» → Savings и т.п. |
| `plural($n, 'вклад', 'вклада', 'вкладов')` | русское склонение по числу (A14) |
| `score_format($v, $decimals = 1)` | оценка 0–5: 4.62 → «4,6» (A18) |
| `money_format($v, $sign = '₸')` | «1 180 394 ₸» с неразрывными пробелами (A18) |
| `User::hasFavorite($model)`, `User::wants($key)`, `User::level($reviews, $helpful)`, `User::profileUrl()`, `avatarUrl()`, `location()` | избранное (один запрос на страницу), уведомления с умолчаниями `User::NOTIFY`, ступень автора `User::LEVELS` (A28) |
| `Username::generate($name)` | латиница из имени, уникально, без служебных слов (A28) |
| `Income::calc($amount, $months, $rate, $effective)` | доход: по ГЭСВ, иначе номинал с ежемесячной капитализацией (A14) |
| `country()` | текущая страна витрины или null (A21) |
| `currency_symbol($code = null)` | знак валюты: основная валюта страны → её знак (₸, сум), иначе $ € ₽ ¥ или код (A21) |
| `money_format($v, $sign = null)` | A21: по умолчанию знак валюты текущей страны |
| `Bank::findByIdentifier($s, $country = null)`, `Bank::inCountry($c = null)` | A21: поиск и выборка внутри страны (null — текущая) |
| `Country::guaranteeLimit($type, $currency)`, `guaranteeName()`, `regulatorName()`, `centralBankName()`, `genitive()` | A21: национальные данные страны |
| `php artisan banq:import {file} [--entity] [--format] [--mode] [--country] [--dry-run] [--json]` | A22 импорт в любом формате |
| `php artisan banq:export [entities*] [--format] [--country] [--bank=*] [--current] [--output]` | A22 экспорт |
| `php artisan banq:schema [entity]`, `php artisan banq:token {name} [--write] [--days]` | A22 схема формата, A23 токен API |
| `php artisan banq:fx-sync [--country=KZ] [--days=N] [--date=Y-m-d]` | A40 курсы центробанков; в расписании — каждый час (:07) |

## 9. Конфиг `config/banq.php`

| Ключ | Что | Где используется |
|---|---|---|
| `currencies` | порядок валют | фильтр каталога, сетка ставок |
| `terms` | сроки в месяцах | сравнение (выбор срока) |
| `ratings.objective_weights` | веса критериев объективного рейтинга: rates .40, reliability .30, range .15, terms .15 | ObjectiveRating, методика на /ratings (A18) |
| `ratings.people_prior_weight`, `ratings.people_prior_mean` | байесовское среднее народного: 5 оценок по 3,5 | PeopleRating (A18) |
| `ratings.review_min_length` | минимум символов в отзыве (80) | ReviewController, форма (A18) |
| `cards.features`, `cards.wallets` | подписи возможностей карт и кошельков (A30) | фильтры, карточка, админка |
| `typograph` | A36: вкл/выкл неразрывных пробелов (env `BANQ_TYPOGRAPH`) | Middleware Typography |
| `cards.compare_limit` | до 4 карт в сравнении | $store.cardCompare (A33) |
| `cards.score.debit`, `cards.score.credit` | веса «Оценки Banq» | CardScore (A33) |
| `fx.providers` | A40: код страны → класс RateProvider (KZ → НБРК, UZ → ЦБ РУз, RU → ЦБ РФ) | CurrencyRates::provider() |
| `fx.defaults`, `fx.auto_refresh` (env `BANQ_FX_AUTO_REFRESH`), `fx.retry_minutes`, `fx.trend_days` | A40: валюты по умолчанию, обновление при заходе, пауза между попытками (30 мин), глубина спарклайна (30 дн.) | CurrencyRates |
| `ticker.sources` | A40: типы контента ленты (ключ → TickerSource) | Ticker, админка → Бегущая строка |
| `social.auto_refresh`, `social.keys.{youtube,telegram,vk}` | A55: обновление счётчиков при заходе, ключи API из .env | SocialStats |
| `Post::cascade($type, $model)`, `Post::syncLinks($keys, &$missing)`, `Post::about($model)`, `Post::linked()`, `Post::resolveLink('card:slug')` | A37 связи публикации с каскадом (не конфиг — справка) | редактор, обмен, страницы продуктов |
| ~~`kdif_limits`~~ | A21: удалено — лимиты живут в `countries.deposit_guarantee.limits` (для KZ те же 20 / 10 / 10 / 5 млн ₸) | `Country::guaranteeLimit()` |

## 10. Как добавить новый раздел (карты, кредиты…) — A21–A24

1. Модель и миграция с `bank_id` (страна — через банк; для международных сущностей вроде платёжных систем — свой `country_id` nullable или связь многие-ко-многим).
2. Сущность обмена `App\Services\Exchange\Entities\XxxEntity` + строка в `EntityRegistry` → сразу работает импорт/экспорт в админке, CLI и API, появляется в схеме.
3. Ресурс админки `App\Admin\Resources\XxxResource` + строка в `ResourceRegistry` → список, фильтры, форма, массовые действия; пункт меню — в `layouts/admin`.
4. Витрина: выборки через `Bank::inCountry()`, суммы — `currency_symbol()`, национальные названия — из `country()`.

Пример раздела по этой схеме — «Карты» (A30–A33): `Card` + `CardEntity` + `CardResource` + `CardCatalog`. Международная сущность без страны — `PaymentSystem` (`home_country`, `countries`), карты страны выбираются через банк.

## 11. Курсы валют и бегущая строка — A40

- **Новая страна с курсами:** класс `App\Services\Currency\Providers\XxxProvider implements RateProvider` (fetch → `{date, rates[{currency, name, nominal, rate, change}]}`) + строка в `config('banq.fx.providers')` и валюты по умолчанию в `fx.defaults`. Всё остальное (хранение, карточка, лента, админка, cron) общее.
- **Новый тип ленты** (новости блога, лучшие карты…): класс `TickerSource` (label, hint, group(country) → `{title, href, items[{label, value?, delta?, href?}]}`) + строка в `config('banq.ticker.sources')` — появится в админке сам.
- Фолбэк: источник недоступен → статус в кеше `banq.fx.status.{KZ}`, витрина показывает последний сохранённый курс (`stale` — старше 3 дней — с пометкой).


## 12. Флаги и страна в админке — A46

- **Флаг где угодно:** `@include('partials.flag', ['code' => 'KZ'])`, `['currency' => 'USD', 'round' => true]`, в PHP — `Flags::img()` или `$country->flagImg()`. Эмодзи-флаги (`countries.flag`) — только там, где картинка невозможна (`<option>`): на Windows эмодзи-флаг — две буквы.
- **Новый ресурс админки со страной:** `countryScope()` → путь к id страны (`'country_id'`, `'bank.country_id'`, `'product.bank.country_id'`; `'id'` — сами страны), `countryNullable()` — запись без страны общая. ResourceController сам сузит список по плашке, вернёт 404 на чужую запись и не даст сохранить в чужую страну.
- **Свои страницы админки:** `app(AdminScope::class)->apply($query, 'country_id')`, код страны для выгрузок — `clampCode()` (сотрудник с ограничениями всегда получает свою страну).
- Ограничения сотрудника пока не касаются API-токенов и CLI (`banq:import`), а в табличном редакторе и импорте — только страна по умолчанию: строки с явным чужим банком движок обмена не отсекает.
