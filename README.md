# Task API

Однопользовательский REST API списка задач на Laravel 12 и PHP 8.2. Данные
хранятся в локальном JSON-файле; база данных, Docker и внешние сервисы не
используются. Проект не содержит web-интерфейса и Blade-шаблонов.

Подробный контракт находится в [`SPEC.md`](SPEC.md), план реализации — в
[`IMPLEMENTATION_PLAN.md`](IMPLEMENTATION_PLAN.md), журнал работы — в
[`sessions/`](sessions/).

## Требования

- PHP 8.2 с расширениями `ctype`, `curl`, `dom`, `fileinfo`, `intl`, `json`,
  `mbstring`, `openssl`, `tokenizer`, `xml` и `zip`;
- Composer 2.

Проверенные версии: PHP 8.2.33, Composer 2.10.2, Laravel 12.66.0,
PHPUnit 11.5.56, Laravel Pint 1.30.4, Larastan 3.10.0, PHPStan 2.2.8 и
Scramble 0.13.41.

## Установка

```bash
composer install
cp .env.example .env
php artisan key:generate
```

`.env` содержит локальные настройки и не коммитится. Для этого API не нужно
настраивать подключение к базе данных.

## Запуск

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

По умолчанию задачи сохраняются в `storage/app/tasks.json`. Для изолированного
запуска путь можно заменить переменной окружения:

```bash
TASKS_FILE=/tmp/task-api/tasks.json php artisan serve --host=127.0.0.1 --port=8000
```

Родительский каталог создаётся автоматически. Отсутствующий или нулевой файл
считается пустым хранилищем; повреждённый файл не перезаписывается.

## API

| Метод | Путь | Назначение |
|---|---|---|
| `GET` | `/api/v1/tasks` | Список; фильтры `statuses[]`, `due_date`, `category_ids[]` |
| `GET` | `/api/v1/tasks/{id}` | Получить задачу |
| `POST` | `/api/v1/tasks` | Создать задачу |
| `PATCH` | `/api/v1/tasks/{id}` | Частично изменить задачу |
| `DELETE` | `/api/v1/tasks/{id}` | Удалить задачу |
| `GET` | `/api/v1/categories` | Список категорий |
| `GET` | `/api/v1/categories/{id}` | Получить категорию |
| `POST` | `/api/v1/categories` | Создать категорию |
| `PATCH` | `/api/v1/categories/{id}` | Переименовать категорию |
| `DELETE` | `/api/v1/categories/{id}` | Удалить неиспользуемую категорию |

Допустимые статусы: `todo`, `in_progress`, `done`. Поле `due_date` принимает дату
`YYYY-MM-DD`, включая дату в прошлом. Успешные ответы используют оболочку `data`.

Пример создания:

```bash
curl --include \
  --request POST \
  --header 'Content-Type: application/json' \
  --data '{"title":"Write tests","status":"todo","due_date":"2026-08-17"}' \
  http://127.0.0.1:8000/api/v1/tasks
```

Пример фильтра:

```bash
curl --include \
  'http://127.0.0.1:8000/api/v1/tasks?statuses[]=todo&statuses[]=done&due_date=2026-08-17'
```

Значения `statuses[]` и `category_ids[]` объединяются через OR внутри своей
группы. Статусы, категории и точная дата `due_date` объединяются через AND.
Задача содержит nullable `category_id`; неизвестная категория отклоняется с 422.
Старый одиночный параметр `status` не поддерживается.

Ошибки имеют единую форму:

```json
{
  "error": {
    "code": "validation_failed",
    "message": "Request validation failed",
    "details": [
      {
        "field": "title",
        "message": "The title field is required."
      }
    ]
  }
}
```

## Проверка

```bash
php artisan test
./vendor/bin/pint --test
composer analyse
php artisan route:list --path=api/v1/tasks
php artisan route:list --path=api/v1/categories
```

Тесты используют уникальные файлы в системной временной директории, не обращаются
в сеть и не создают `storage/app/tasks.json`.

`composer analyse` проверяет основной PHP-код на PHPStan level 8 с Laravel-aware
расширением Larastan. Конфигурация не использует baseline или `ignoreErrors`.

## OpenAPI-документация

Scramble формирует OpenAPI 3.1 непосредственно из validation rules, PHPDoc и
атрибутов в контроллерах и FormRequest. В окружении `local` доступны:

- `http://127.0.0.1:8000/docs/api` — интерактивный UI;
- `http://127.0.0.1:8000/docs/api.json` — актуальная JSON-спецификация.

В других окружениях оба маршрута отвечают `403`, поскольку проект не имеет
аутентификации. Чтобы экспортировать спецификацию в корневой `api.json`:

```bash
composer docs
```

`api.json` является генерируемым файлом и указан в `.gitignore`. Автотест
проверяет все 10 operation ID, фильтры, request schemas, основные response codes
и отсутствие тела у ответов `204`.

## Postman

В подключённом Postman-аккаунте создан personal workspace `Laravel Task API` и
коллекция `Task API v1` со всеми десятью endpoint'ами. Коллекция использует
переменные `base_url=http://127.0.0.1:8000`, `task_id`, `category_id` и `due_date`;
запросы Create сохраняют созданные ID для последующих операций.

- Workspace ID: `bdf83f6a-5b7c-42cd-9a94-f970641f71fc`.
- Collection UID: `15088864-85dfa2f5-31b8-4a87-90bd-5d787cedbd3f`.

## Доказательство запуска

Видеозапись запуска и проверки проекта сохранена в
[`proof-of-launch/2026-08-17 04-03-51.mp4`](proof-of-launch/2026-08-17%2004-03-51.mp4).

- размер: `9 040 500` байт;
- SHA-256: `4bd1faf887f9b697523aa0e5a89aaa059a4eb89000d9b15b16992e0290d1f94e`.

Контрольная сумма позволяет проверить, что приложенное к сдаче видео не было
заменено после фиксации.

## Архитектура

HTTP-запрос проходит через task/category controller в соответствующий сервис.
Сервисы содержат бизнес-правила и зависят от интерфейсов репозиториев. Контейнер
Laravel подставляет общий `JsonTaskRepository` — единственный класс, работающий с файлом,
блокировкой и атомарной записью. Благодаря этой границе сервис unit-тестируется с
Mockery, а feature-тесты проверяют полный путь на отдельном временном JSON-файле.

## Ограничения

- нет регистрации, авторизации и нескольких пользователей;
- нет пагинации и пользовательской сортировки;
- нет диапазонов дат и фильтров «до/после» срока выполнения;
- нет корзины и восстановления;
- файловое хранилище рассчитано на небольшой учебный сервис, а не на распределённое
  развёртывание на нескольких серверах.

## Наивный прогон

Первый изолированный вариант сохранён в [`naive-run/`](naive-run/). Он намеренно
не является основой этого приложения: его упрощённый контракт нужен для сравнения
подхода «один промпт» с реализацией по заранее утверждённой спецификации.
