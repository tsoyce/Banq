# TSOY.IN · Banq — обмен данными и API (A22, A23)

Один движок (`App\Services\Exchange\DataExchange`) и один формат для админки, консоли и API.
Цель — чтобы данные можно было собрать где угодно (скрипт, таблица, Claude) и залить одной командой.

## Формат пакета

```json
{
  "countries": [{"code": "KZ", "name": "Казахстан", "currency": "KZT", "deposit_guarantee": {"short": "КФГД", "limits": {"savings": 20000000, "term": 10000000, "demand": 10000000, "foreign": 5000000}}}],
  "banks": [
    {"country": "KZ", "code": "BCC", "name": "Банк ЦентрКредит", "aliases": ["ЦентрКредит"],
     "deposit_products": [
       {"name": "Депозит «Срочный»", "type": "term", "min_amount": 50000,
        "rates": [{"currency": "KZT", "term_months": 12, "rate": 14.5, "effective_rate": 15.4}]}]}
  ],
  "deposit_rates": [{"bank": "BCC", "product": "Депозит «Срочный»", "term_months": 6, "rate": 14}]
}
```

- Сущности: `countries` → `banks` → `deposit_products` → `deposit_rates` → `payment_systems` → `card_tiers` → `cards` → `service_packages` (импортируются в этом порядке; A31 — раздел «Карты»).
- A31 · Карты и пакеты можно вложить в банк (`cards`, `service_packages`), уровни — в платёжную систему (`tiers`). У карты `networks` — список «СИСТЕМА:уровень»: `["VISA:gold", "Mastercard World Elite", "UnionPay"]`; у пакета `cards` — slug или названия карт того же банка. Пример — `tests/fixtures/cards-demo.json` (условия вымышленные). Полный список полей, типов и примеров — `GET /api/v1/schema`, `php artisan banq:schema` или админка → «Обмен данными» → «Формат и API».
- **Естественные ключи** вместо id: страна — ISO-код; банк — страна + `code` (или имя/алиас); вклад — `slug` или банк + название (+ `type`); ставка — вклад + валюта + срок + порог суммы. Повторная заливка того же пакета ничего не меняет.
- **Вложенность:** банк внутри страны (`banks`), вклад внутри банка (`deposit_products` / `products` / `deposits`), ставки внутри вклада (`rates`). Ссылки на родителя подставляются сами.
- **Ставки не перезаписываются:** строка без `valid_to` — текущая ставка; если отличается от действующей, старая закрывается (`valid_to` = `valid_from` новой). Строка с `valid_to` — историческая запись.
- Без поля `country` берётся страна импорта (`country=KZ` в запросе / `--country`), затем страна по умолчанию.
- Значения прощают человеческий ввод: `«14,5%»`, `«50 000»`, `«да/нет»`, русские подписи типов (`Пополнение`, `Табу`), русские заголовки CSV (`Банк;Вклад;Срок;Ставка;ГЭСВ`).
- **CSV:** одна сущность на файл (укажи `entity`) или колонка `_entity`; вложенный JSON — колонки через точку (`deposit_guarantee.limits.term`), списки — через `|`.
- **XLSX:** лист = сущность (`banks` или «Банки»), первая строка — заголовки.
- **Режимы:** `upsert` (по умолчанию), `create` (только новые), `update` (только существующие).
- **dry_run:** весь прогон в транзакции с откатом — отчёт показывает, что будет создано/изменено (с диффом), ничего не записывая.

## Отчёт

```json
{"totals": {"created": 3, "updated": 1, "unchanged": 10, "skipped": 0, "error": 1},
 "stats": {"deposit_rates": {"created": 2, "updated": 1, "unchanged": 4, "skipped": 0, "error": 1}},
 "rows": [{"entity": "deposit_rates", "row": 2, "action": "updated", "key": "bcc-srochnyj · KZT · 12 мес", "changes": {"rate": [14.5, 15]}},
          {"entity": "deposit_rates", "row": 5, "action": "error", "key": "x", "message": "банк «NOPE» не найден в стране KZ"}],
 "import_id": 12}
```

Ошибка в строке не останавливает импорт: строка пропускается, причина — в отчёте и в журнале импорта.

## API `/api/v1`

Токен: админка → «Токены API» (только администратор) или `php artisan banq:token "Claude" --write`. Передаётся как `Authorization: Bearer banq_…` (или `X-Api-Token`). Права: `read` — схема и выгрузки, `write` — импорт. Лимит — 120 запросов в минуту.

| Метод | Путь | Право | Что |
|---|---|---|---|
| GET | `/api/v1/schema` | read | схема формата |
| GET | `/api/v1/{entity}?country=KZ&bank=BCC,KASPI&current=1` | read | строки сущности JSON `{entity, count, data}` |
| GET | `/api/v1/export?entities=banks,deposit_rates&format=json\|csv\|xlsx&country=KZ&current=1` | read | выгрузка файлом |
| POST | `/api/v1/import?dry_run=1&mode=upsert&country=KZ&entity=…&format=…` | write | тело — JSON-пакет, CSV или файл (`multipart`, поле `file`) |
| GET | `/api/v1/imports`, `/api/v1/imports/{id}` | read | история и журнал импортов |

```bash
curl -H "Authorization: Bearer $TOKEN" https://bank.tsoy.in/api/v1/schema
curl -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
     --data @kz-deposits.json "https://bank.tsoy.in/api/v1/import?dry_run=1"
curl -X POST -H "Authorization: Bearer $TOKEN" -F file=@rates.csv "https://bank.tsoy.in/api/v1/import?entity=deposit_rates&country=KZ"
```

Ответ импорта — отчёт (см. выше); `422`, если файл не разобрался или все строки с ошибками.

## Консоль

```bash
php artisan banq:schema [entity]
php artisan banq:import data.json [--dry-run] [--entity=banks] [--format=csv] [--mode=upsert] [--country=KZ] [--json]
php artisan banq:export [countries banks deposit_products deposit_rates] [--format=json|csv|xlsx] [--country=KZ] [--bank=BCC] [--current] [--output=file]
php artisan banq:token "Имя" [--write] [--days=30]
```
