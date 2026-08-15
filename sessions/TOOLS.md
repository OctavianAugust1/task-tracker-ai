# Журнал инструментов

> Только дополняется, старые записи не редактируются. Сюда попадает всё, что вы
> добавили в проект или окружение ради этой задачи: библиотеки, CLI-утилиты,
> линтеры, MCP-серверы, плагины и скиллы агента, хуки, внешние сервисы, контейнеры.
>
> Разовый вызов уже установленного инструмента сюда не пишется — он идёт
> в таблицу инструментов внутри файла сессии.
>
> Тупиковая попытка — тоже запись: поставили, не подошло, сняли. Это как раз интересно.

## Шаблон записи

```markdown
## YYYY-MM-DD · Сессия N · <инструмент> vX.Y

- **Тип:** библиотека / CLI / MCP / плагин / скилл / хук / сервис
- **Установка:** `<точная команда>`
- **Зачем:** какую задачу решает
- **Область:** проект или глобально
- **Проверка:** чем убедились, что работает
```

Удалённый или заменённый инструмент фиксируется так же — с пометкой
**удалён** / **заменён на X** и причиной.

---

## 2026-08-15 · Сессия 1 · Git v2.53.0

- **Тип:** CLI
- **Установка:** установлено пользователем; точная команда ассистенту не сообщена
- **Зачем:** зафиксировать исходный комплект и последующие этапы работы
- **Область:** окружение
- **Проверка:** `git --version` вывела `git version 2.53.0`; создан root-коммит
  `e561162`

## 2026-08-15 · Сессия 4 · Laravel Framework v12.66.0

- **Тип:** библиотека / фреймворк
- **Установка:** `composer create-project --no-interaction --no-scripts laravel/laravel /tmp/task-api-laravel-q9s397 '12.*'`
- **Зачем:** создать чистый каркас основной реализации на утверждённом стеке
- **Область:** основной проект
- **Проверка:** `php artisan --version` вывела `Laravel Framework 12.66.0`;
  `php artisan test` — 2 passed, 2 assertions; `./vendor/bin/pint --test` — passed

Стандартная dev-зависимость `laravel/sail` v1.66.0 удалена до переноса каркаса,
поскольку Docker запрещён правилами проекта. Вместе с ней удалён неиспользуемый
`symfony/yaml` v7.4.15. Проверка: `composer show laravel/sail` не находит пакет.

## 2026-08-15 · Сессия 2 · software-properties-common

- **Тип:** CLI
- **Установка:** `apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y software-properties-common`
- **Зачем:** добавить репозиторий с требуемым PHP 8.2, отсутствующим в штатном
  репозитории Ubuntu 26.04
- **Область:** окружение
- **Проверка:** команда `add-apt-repository` стала доступна

## 2026-08-15 · Сессия 2 · ondrej/php PPA — удалён

- **Тип:** сервис
- **Установка:** `add-apt-repository -y ppa:ondrej/php`
- **Зачем:** первая попытка получить PHP 8.2 для Ubuntu 26.04
- **Область:** окружение
- **Проверка:** `apt-get update` вернул `404` для `resolute`; запись удалена командой
  `add-apt-repository --remove -y ppa:ondrej/php`, поскольку PPA направил Ubuntu
  26.04 на `packages.sury.org/php`

## 2026-08-15 · Сессия 2 · PHP 8.2.33 и Composer 2.9.5

- **Тип:** CLI
- **Установка:** ключ и репозиторий добавлены по `https://packages.sury.org/php/README.txt`, затем выполнено `DEBIAN_FRONTEND=noninteractive apt-get install -y php8.2-cli php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip composer php8.2-intl`
- **Зачем:** создать, запустить и проверить Laravel 12 на зафиксированной версии PHP
- **Область:** окружение
- **Проверка:** `php -v` вывела `PHP 8.2.33`; `composer --version` вывела
  `Composer version 2.9.5` и `PHP version 8.2.33`

## 2026-08-15 · Сессия 2 · Composer 2.10.2 — заменяет системный 2.9.5

- **Тип:** CLI
- **Установка:** официальный установщик скачан с `https://getcomposer.org/installer`, проверен SHA-384 по `https://composer.github.io/installer.sig` и запущен как `php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer`
- **Зачем:** системная сборка Composer 2.9.5 падала на PHP 8.2 при разрешении
  зависимостей из-за вызова отсутствующей функции `array_all()`
- **Область:** окружение
- **Проверка:** `composer --version` вывела `Composer version 2.10.2` и
  `PHP version 8.2.33`; `composer install --no-interaction` успешно завершилась

## 2026-08-15 · Сессия 2 · Laravel 12.66.0

- **Тип:** библиотека
- **Установка:** `composer create-project laravel/laravel naive-run '^12.0' --no-interaction`, затем после замены Composer — `composer install --no-interaction`
- **Зачем:** создать изолированный наивный прогон Task API
- **Область:** `naive-run/`
- **Проверка:** `php artisan about --only=environment` показала Laravel 12.66.0 и
  PHP 8.2.33; `php artisan test` — 5 пройденных тестов
