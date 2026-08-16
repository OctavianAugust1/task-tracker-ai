# План Postman

## Workspace

- имя: `Laravel Task API`;
- тип: `personal`;
- назначение: локальная разработка и ручная проверка учебного Task API.

## Collection

- имя: `Task API v1`;
- schema: Postman Collection v2.1;
- переменные: `base_url`, `task_id`, `due_date`;
- авторизация: отсутствует;
- requests:
  1. `GET /api/v1/tasks` с `statuses[]=todo`, `statuses[]=done`, `due_date`;
  2. `GET /api/v1/tasks/:task_id`;
  3. `POST /api/v1/tasks`;
  4. `PATCH /api/v1/tasks/:task_id`;
  5. `DELETE /api/v1/tasks/:task_id`.

Каждый request содержит описание и базовый Postman test ожидаемого status code.
Create сохраняет возвращённый `data.id` в collection variable `task_id`, чтобы
последующие show/update/delete могли использовать созданную задачу.

## Проверка

- прочитать созданный workspace;
- прочитать созданную collection и убедиться, что в ней пять requests;
- не публиковать документацию и не создавать внешние вызовы к localhost.
