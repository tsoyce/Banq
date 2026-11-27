# Выкладка на боевой (A35)

## Как это работает

1. Push в `main` (обычно — слияние PR) запускает «Tests» (`.github/workflows/tests.yml`).
2. Тесты зелёные → «Deploy» (`.github/workflows/deploy.yml`) заходит по SSH ключом выкладки
   и передаёт `deploy <sha>` — ровно тот коммит, который прошёл тесты.
3. На сервере ключ привязан к принудительной команде `/usr/local/sbin/banq-deploy`
   (копия `deploy/banq-deploy`). Другую команду этим ключом выполнить нельзя.
4. `banq-deploy`:
   - одна выкладка за раз (`flock`), старый коммит после нового не выкатывается;
   - копия базы (SQLite — `sqlite3 .backup` или файл; MySQL — `mysqldump`) и `.env`
     в `/var/backups/banq/<время>-<sha>/`, хранятся последние 15;
   - от имени `tsoy`: `git merge --ff-only <sha>`, затем `DEPLOY_SKIP_PULL=1 bash deploy.sh`
     (composer, права, миграции, очистка кешей);
   - проверка `curl -I https://bank.tsoy.in/` — нужен ответ 2xx/3xx;
   - ошибка → `git reset --keep` на прежний коммит, composer, база из копии, повторная проверка.
5. Итог видно в Actions → Deploy; журнал на сервере — `/var/log/banq-deploy.log`.

Руками: Actions → Deploy → Run workflow (выкладывает текущий `main`), или на сервере
`bash deploy.sh`, как раньше.

## Секреты репозитория (Settings → Secrets and variables → Actions)

| Секрет | Что |
|---|---|
| `DEPLOY_HOST` | адрес сервера |
| `DEPLOY_SSH_KEY` | приватный ключ выкладки (ed25519, без пароля) |
| `DEPLOY_KNOWN_HOSTS` | строки `known_hosts` сервера (`ssh-keyscan -t ed25519,rsa <адрес>`, сверить с отпечатком) |

## Установка на сервере (один раз, root)

```bash
install -o root -g root -m 0755 deploy/banq-deploy /usr/local/sbin/banq-deploy
# в /root/.ssh/authorized_keys — одной строкой:
command="/usr/local/sbin/banq-deploy",restrict ssh-ed25519 AAAA... banq-github-deploy
```

Скрипт ставится копией, а не ссылкой на папку сайта: её может менять `tsoy`, а скрипт
работает от root. После правок `deploy/banq-deploy` копию надо обновить той же командой.

Проверка: `ssh -i <ключ> root@<адрес> status` — текущий коммит и хвост журнала.
