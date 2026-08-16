# План категорий задач

## Рекомендуемая модель

- публичное имя сущности: `category`;
- задача принадлежит максимум одной категории через nullable `category_id`;
- пользователь управляет категориями отдельным CRUD API;
- задача без категории допустима;
- фильтр списка: `category_ids[]=1&category_ids[]=2`, OR внутри категорий и AND с
  существующими `statuses[]` / `due_date`;
- удаление категории, используемой задачами, возвращает `409 category_in_use`;
- имя категории: обязательная trimmed строка 1–100 символов, уникальная без учёта
  регистра;
- ответы задач сохраняют `category_id`, но не встраивают category object.

## Новые endpoint'ы

```text
GET    /api/v1/categories
GET    /api/v1/categories/{id}
POST   /api/v1/categories
PATCH  /api/v1/categories/{id}
DELETE /api/v1/categories/{id}
```

Task POST/PATCH начинают принимать `category_id: integer|null`. Несуществующая
категория даёт 422 при записи задачи. Task list принимает `category_ids[]`.

## Формат файла

Предлагаемый state:

```json
{
  "next_task_id": 1,
  "next_category_id": 1,
  "tasks": [],
  "categories": []
}
```

Это несовместимо с текущими `next_id`/`tasks`. Возможны варианты:

1. Рекомендуемый: при первом чтении валидного старого state в памяти добавить
   пустые categories, а при следующей успешной mutation атомарно записать новый
   формат. Повреждённый файл по-прежнему не менять.
2. Считать старый формат unsupported corruption и потребовать ручной перенос.

## Подтверждённые решения

1. Одна nullable категория на задачу, не many-to-many tags.
2. Полный CRUD категорий.
3. Удаление используемой категории возвращает `409`.
4. Старый валидный JSON безопасно обновляется при следующей успешной записи.

## Этапы после выбора

1. Обновить SPEC и файловый контракт до кода.
2. Добавить красные Feature/unit/integration tests, включая migration, uniqueness,
   deletion conflict и комбинацию фильтров.
3. Расширить typed domain/service/repository layers без файлового I/O в controller
   или services.
4. Обновить Postman collection в существующем workspace и проверить read-back.
5. Выполнить Larastan, PHPUnit, Pint, concurrency, HTTP и независимый аудит.
6. Обновить README, журнал/STATE и сделать отдельный коммит.
