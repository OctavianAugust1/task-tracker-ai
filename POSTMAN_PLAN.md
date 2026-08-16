# План Postman

## Workspace

- имя: `Laravel Task API`;
- тип: `personal`;
- назначение: локальная разработка и ручная проверка учебного Task API.

## Collection

- имя: `Task API v1`;
- schema: Postman Collection v2.1;
- переменные: `base_url`, `task_id`, `category_id`, `due_date`;
- авторизация: отсутствует;
- requests:
  1. `GET /api/v1/tasks` с `statuses[]`, `due_date`, `category_ids[]`;
  2. `GET /api/v1/tasks/:task_id`;
  3. `POST /api/v1/tasks`;
  4. `PATCH /api/v1/tasks/:task_id`;
  5. `DELETE /api/v1/tasks/:task_id`.
  6. `GET /api/v1/categories`;
  7. `GET /api/v1/categories/:category_id`;
  8. `POST /api/v1/categories`;
  9. `PATCH /api/v1/categories/:category_id`;
  10. `DELETE /api/v1/categories/:category_id`.

Каждый request содержит описание и базовый Postman test ожидаемого status code.
Create-запросы сохраняют `data.id` в `task_id` и `category_id`.

## Проверка

- прочитать созданный workspace;
- прочитать collection и убедиться, что в ней десять requests;
- не публиковать документацию и не создавать внешние вызовы к localhost.
