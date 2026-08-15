# Naive Task API

Изолированный наивный прогон простого API списка задач на Laravel 12 и PHP 8.2.
Данные хранятся в `storage/app/tasks.json` как JSON-массив.

## Запуск

```bash
composer install
php artisan serve --host=127.0.0.1 --port=8000
```

## Проверка

```bash
php artisan test
./vendor/bin/pint --test
```

## API

- `GET /api/tasks`
- `POST /api/tasks` — поля `title` и необязательное `completed`
- `GET /api/tasks/{id}`
- `PUT/PATCH /api/tasks/{id}`
- `DELETE /api/tasks/{id}`
