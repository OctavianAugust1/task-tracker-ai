# План интеграции Scramble

## Решения

- установить `dedoc/scramble` обычной runtime-зависимостью через Composer;
- использовать совместимую актуальную stable-версию, выбранную Composer;
- опубликовать `config/scramble.php` и ограничить документацию `api/v1`;
- оставить стандартные локальные маршруты `/docs/api` и `/docs/api.json`;
- задать название, описание и версию Task API в конфигурации;
- документировать все 10 операций непосредственно атрибутами/PHPDoc в
  `TaskController`, `CategoryController` и validation-классах;
- описать operation ID, summary, path/query/body parameters, успешные ответы и
  контрактные 400/404/409/422/500;
- выполнить `php artisan scramble:export`, получив `/workspace/api.json`;
- добавить `/api.json` в корневой `.gitignore`, сам файл не коммитить.

## Проверки

1. `composer validate --strict` и `composer show dedoc/scramble`.
2. `php artisan route:list` подтверждает UI/JSON docs и прежние 10 API routes.
3. Generated OpenAPI 3.1 содержит ровно 10 API operations, корректные paths,
   request schemas, фильтры и основные response codes.
4. `/docs/api.json` отвечает 200 локально и согласуется с exported `api.json`.
5. `api.json` существует на диске, игнорируется Git и отсутствует в индексе.
6. Полный PHPUnit, Pint, Larastan и реальный HTTP smoke.
7. Независимый read-only аудит generated specification и diff.

## Границы

- не менять бизнес-контракт API и JSON-хранилище;
- не добавлять базу данных, внешние runtime-сервисы или frontend проекта;
- не коммитить generated `api.json`;
- не включать существующий неотслеживаемый `docker-compose.yml` в коммит.
