# Сессия 16 — OpenAPI-документация Scramble

- **Дата:** 2026-08-16
- **Ассистент:** Codex, GPT-5
- **Режим разрешений:** файловая система без ограничений и без запросов
  подтверждения; режим задан окружением. До принятия плана меняются только
  журнал и документ плана.
- **Статус:** завершена

## Промпты

1. > Добавь пакет https://scramble.dedoc.co/ в проект, и составь документацию с помощью него для API прямо в коде, а потом сгененрируй файл api.json, но его добавь в .gitignore
2. > Добавь пакет https://scramble.dedoc.co/ в проект, и составь документацию с помощью него для API прямо в коде, а потом сгененрируй файл api.json, но его добавь в .gitignore
3. > Добавь пакет https://scramble.dedoc.co/ в проект, и составь документацию с помощью него для API прямо в коде, а потом сгененрируй файл api.json, но его добавь в .gitignore
4. > Добавь пакет https://scramble.dedoc.co/ в проект, и составь документацию с помощью него для API прямо в коде, а потом сгененрируй файл api.json, но его добавь в .gitignore
5. > Подтверждаю

## Размышления

Scramble является runtime-пакетом: он регистрирует UI `/docs/api` и JSON-spec
`/docs/api.json`, поэтому установка только в `require-dev` сделала бы эти маршруты
недоступными после production-install. Рекомендуется обычный `composer require`.

Автоматического вывода типов недостаточно: контроллеры возвращают массивы из
сервисов, а не Laravel Resources/модели, поэтому generated schema нужно проверить
по фактическому OpenAPI. Документация должна жить рядом с кодом в атрибутах и
PHPDoc контроллеров/FormRequest, не в вручную поддерживаемом OpenAPI-файле.
`api.json` является генерируемым артефактом и должен быть создан для проверки, но
добавлен в корневой `.gitignore`.

Первый сгенерированный документ содержал все десять операций, но автоматический
вывод не восстановил все схемы ответов из массивов. Поэтому для стабильного
контракта добавлены Scramble-атрибуты контроллеров и FormRequest, а общие формы
ответов описаны PHPDoc-типами. Ручное ведение отдельного OpenAPI-файла отвергнуто:
оно дублировало бы код и validation rules.

При проверке реальным сервером `/docs/api` неожиданно вернул 500, хотя Feature-тест
был зелёным. Причиной оказался стандартный middleware `web`: фактический
`SESSION_DRIVER=database` пытался открыть SQLite, которой в проекте быть не должно.
Middleware `web` удалён с docs routes; оставлен отдельный local-only middleware,
не зависящий от auth, sessions и базы данных. Повторный HTTP-запрос вернул 200.

Независимый аудит обнаружил ещё одно расхождение: атрибуты параметров сохраняли
описания, но перекрывали часть ограничений validation rules. Добавлен небольшой
Scramble transformer, который переносит ограничения длины, положительные ID,
`minItems`/`maxItems`/`uniqueItems` и удаляет ошибочный enum с уровня массива
statuses. Тест OpenAPI усилен точными assertions; повторная генерация подтвердила
исправление. Форматы дат в response shapes остались обычными строками: Scramble
не позволяет выразить format внутри используемой строковой array-shape без
отдельных DTO, а вводить DTO только ради документации сочтено несоразмерным.

## Использованные инструменты

| Инструмент | Действие | Зачем |
|---|---|---|
| официальный сайт Scramble | Прочитаны installation, request/response, annotations и export | Проверить совместимость и рекомендуемый workflow |
| `git status`, `composer.json`, `STATE.md` | Проверено состояние проекта | Исключить частичные изменения после прерванных попыток |
| `apply_patch` | Созданы журнал и план до установки | Соблюсти порядок работы курса |
| Composer | Установлен `dedoc/scramble` v0.13.41 | Генерация OpenAPI и локального UI |
| Scramble attributes и transformer | Описаны операции, тела, ответы и ограничения | Согласовать generated schema с API-контрактом |
| PHPUnit | Запущены полный suite и точный тест OpenAPI | Проверить API и документацию без сетевых запросов |
| Larastan / Pint | Проверены типы и стиль | Не допустить статических и форматных регрессий |
| `artisan serve` и curl | Проверены docs, валидный/невалидный запрос и состояния файла | Найти различие между тестовым и реальным окружением |
| отдельный агент-аудитор | Выполнен read-only аудит generated `api.json` | Получить независимый взгляд |

## Изменения в проекте

- `sessions/session-16.md` — создан журнал новой задачи.
- `SCRAMBLE_PLAN.md` — записан план интеграции.
- `.gitignore` — добавлен генерируемый `/api.json`.
- `composer.json`, `composer.lock` — добавлены Scramble и команда `composer docs`.
- `config/scramble.php` — настроены API v1, метаданные, экспорт и local-only routes.
- `app/Http/Middleware/LocalDocumentationOnly.php` — закрыта документация вне local.
- `app/OpenApi/ApiDocTypes.php` — описаны общие формы API-ответов.
- `app/OpenApi/ScrambleOpenApiTransformer.php` — синхронизированы ограничения схем.
- `app/Providers/AppServiceProvider.php` — зарегистрирован transformer документа.
- `app/Http/Controllers/TaskController.php` — документированы пять task operations.
- `app/Http/Controllers/CategoryController.php` — документированы пять category operations.
- `app/Http/Requests/ListTasksRequest.php` — документированы query filters.
- `app/Http/Requests/StoreTaskRequest.php` — документировано тело создания задачи.
- `app/Http/Requests/UpdateTaskRequest.php` — документировано тело изменения задачи.
- `app/Http/Requests/StoreCategoryRequest.php` — документировано создание категории.
- `app/Http/Requests/UpdateCategoryRequest.php` — документировано изменение категории.
- `tests/Feature/ScrambleDocumentationTest.php` — проверены UI, доступ и OpenAPI-контракт.
- `README.md` — добавлены адреса и команды документации.
- `sessions/TOOLS.md` — записан новый пакет и способ проверки.
- `sessions/STATE.md` — обновлено фактическое состояние проекта.

## Финальный вердикт

Пункт закрыт. Scramble v0.13.41 генерирует OpenAPI 3.1 для 10 операций; локальные
`/docs/api` и `/docs/api.json` отвечают 200, вне local — 403. `composer docs`
создал корневой `api.json`, файл игнорируется Git и не индексирован.

Полная проверка: 47 tests / 613 assertions, Pint passed, Larastan level 8 без
ошибок по 55 файлам, Composer config valid. Реальный HTTP: отсутствующее и пустое
хранилище — 200 с пустым списком; POST — 201; неверный title — 422; повреждённое
хранилище — безопасный 500 и не перезаписано. Независимый аудит не нашёл Critical;
его Important-замечания к ограничениям OpenAPI исправлены и закреплены тестом.
Непроверенным остаётся визуальный рендеринг UI в браузере; HTTP HTML проверен, а
его frontend assets загружаются стандартным renderer Scramble с CDN.
