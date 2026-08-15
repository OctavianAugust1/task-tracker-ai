# Task API

Однопользовательский REST API списка задач на Laravel 12 и PHP 8.2. Данные
хранятся в локальном JSON-файле; база данных, Docker и внешние сервисы не
используются.

Подробный контракт находится в [`SPEC.md`](SPEC.md), план реализации — в
[`IMPLEMENTATION_PLAN.md`](IMPLEMENTATION_PLAN.md), журнал работы — в
[`sessions/`](sessions/).

## Требования

- PHP 8.2 с расширениями `ctype`, `curl`, `dom`, `fileinfo`, `intl`, `json`,
  `mbstring`, `openssl`, `tokenizer`, `xml` и `zip`;
- Composer 2.

Проверенные версии: PHP 8.2.33, Composer 2.10.2, Laravel 12.66.0,
PHPUnit 11.5.56 и Laravel Pint 1.30.4.

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
| `GET` | `/api/v1/tasks` | Список задач; необязательный фильтр `?status=todo` |
| `GET` | `/api/v1/tasks/{id}` | Получить задачу |
| `POST` | `/api/v1/tasks` | Создать задачу |
| `PATCH` | `/api/v1/tasks/{id}` | Частично изменить задачу |
| `DELETE` | `/api/v1/tasks/{id}` | Удалить задачу |

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
curl --include 'http://127.0.0.1:8000/api/v1/tasks?status=todo'
```

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
php artisan route:list --path=api/v1/tasks
```

Тесты используют уникальные файлы в системной временной директории, не обращаются
в сеть и не создают `storage/app/tasks.json`.

## Архитектура

HTTP-запрос проходит через `TaskController` в `TaskService`. Сервис содержит
правила задач и зависит только от интерфейса `TaskRepository`. Контейнер Laravel
подставляет `JsonTaskRepository` — единственный класс, работающий с файлом,
блокировкой и атомарной записью. Благодаря этой границе сервис unit-тестируется с
Mockery, а feature-тесты проверяют полный путь на отдельном временном JSON-файле.

## Ограничения

- нет регистрации, авторизации и нескольких пользователей;
- нет пагинации и пользовательской сортировки;
- нет фильтра по сроку выполнения;
- нет корзины и восстановления;
- файловое хранилище рассчитано на небольшой учебный сервис, а не на распределённое
  развёртывание на нескольких серверах.

## Наивный прогон

Первый изолированный вариант сохранён в [`naive-run/`](naive-run/). Он намеренно
не является основой этого приложения: его упрощённый контракт нужен для сравнения
подхода «один промпт» с реализацией по заранее утверждённой спецификации.
