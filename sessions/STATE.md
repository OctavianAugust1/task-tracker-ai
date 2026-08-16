# Состояние проекта

**Обновлено:** 2026-08-16, сессия 16

## Готово

- Изучены правила, материалы и полное Backend-задание.
- Выбрана тема: однопользовательский API списка задач.
- Согласованы продуктовые и технические решения.
- Исходный комплект зафиксирован root-коммитом `e561162` без Docker-файлов.
- Подготовлены и согласованы `SPEC.md` и Часть II `AGENTS.md`.
- Документальный этап завершён.
- В `naive-run/` создан и проверен изолированный наивный Task API на Laravel
  12.66.0 и PHP 8.2.33 с JSON-хранилищем.
- Наивный прогон покрыт 5 feature-тестами (23 утверждения), проходит Pint и ручную
  HTTP-проверку на порту 8000.
- Подготовлен `IMPLEMENTATION_PLAN.md` для основной реализации строго по
  `SPEC.md`, с отдельными этапами контракта, хранилища, проверки и аудита.
- План исправлен после независимой проверки и принят пользователем.
- В корне подготовлен основной каркас Laravel 12.66.0 на PHP 8.2.33 без Sail,
  Docker, Eloquent-моделей и миграций.
- Подключены ровно пять task API-маршрутов и конфиг JSON-файла.
- Базовый прогон основной заготовки: 2 теста, 2 assertions; Pint проходит.
- Добавлены 12 контрактных feature-тестов по `SPEC.md`.
- Реализованы HTTP-валидация, строгие allowlist, единая оболочка ошибок,
  принудительный JSON для API и ранний `400` для malformed JSON.
- Реализованы пять CRUD-действий и строгое JSON-хранилище с монотонным `next_id`,
  стабильным lock-файлом и атомарным `rename`.
- Контрактные тесты полностью зелёные; общий результат: 19 passed, 261 assertions;
  Pint проходит.
- Проверены 12 параллельных процессов: все записи сохранены, ID уникальны,
  `next_id` монотонен.
- Проверены безопасные `500` при сбое lock/rename и приоритет corruption над 404.
- Общий результат: 23 passed, 289 assertions; Pint проходит.
- Подготовлены проектный `README.md` и сравнение управляемого прогона с
  `naive-run/` в `COMPARISON.md`.
- Реальный сервер проверен на `127.0.0.1:8000`: успешные и ошибочные ответы,
  отсутствующий, пустой и повреждённый store; повреждённый файл не изменился.
- Независимый аудит обнаружил и помог исправить безопасную оболочку framework
  404/405, различение `tasks: {}` и `tasks: []`, формат ошибки пустого PATCH.
- Повторный аудит: Critical и Important отсутствуют.
- Финальный результат основной версии: 24 passed, 279 assertions; Pint проходит;
  зарегистрированы ровно пять task routes.
- Наивная версия повторно проверена: 5 passed, 23 assertions; Pint проходит.
- Основное приложение разделено на тонкий `TaskController`, бизнес-слой
  `TaskService`, интерфейс `TaskRepository` и файловый `JsonTaskRepository`.
- DI binding зарегистрирован в `AppServiceProvider`; controller/service не знают
  путь файла и не выполняют файловые операции.
- Все пять маршрутов имеют версию `/api/v1/tasks`; старый `/api/tasks` отвечает 404.
- Сервис покрыт isolated unit-тестами с Mockery; файловый repository и HTTP API —
  Feature-интеграциями на уникальных временных файлах.
- Все Feature-наборы вызывают `Http::preventStrayRequests()`; Reflection и
  `file_get_contents` в тестах отсутствуют.
- Финальный результат: 29 passed, 302 assertions; Pint проходит.
- Реальный v1 HTTP smoke test: POST 201, GET 200, unversioned GET 404.
- Независимый аудит архитектуры и тестов после исправлений: Critical/Important нет.
- Добавлен Larastan 3.10.0 как единственная прямая dev-зависимость; PHPStan 2.2.8
  установлен транзитивно.
- `composer analyse` проверяет 38 файлов на PHPStan level 8 без baseline и
  `ignoreErrors`; результат `[OK] No errors`.
- Во всех поддерживаемых PHP-файлах основного проекта включён
  `declare(strict_types=1)`; методы, функции и callbacks имеют нативные сигнатуры,
  формы задач/state и callable boundaries уточнены PHPDoc.
- Typed FormRequest accessors проверяют mixed-данные перед передачей в сервис.
- После типизации: 29 passed, 303 assertions; Pint и Composer validate проходят.
- Реальный HTTP: корректный POST 201, неверный тип PATCH 422.
- Независимый аудит PHPStan-конфигурации и типов после исправлений:
  Critical/Important отсутствуют.
- Удалены единственный Blade-шаблон и корневой web-маршрут; проект остаётся
  API-only, исходных `.blade.php` и вызовов `view()` нет.
- После удаления UI: Larastan level 8 без ошибок, 29 passed / 303 assertions,
  Pint passed; реальный API вернул POST 201 и validation 422.
- Список поддерживает комбинацию `statuses[]` (OR) и точного `due_date` (AND);
  старый одиночный `status` и неверные формы фильтров возвращают 422.
- Добавлен readonly `TaskFilters`; controller остаётся тонким, фильтрация находится
  в сервисе, файловый repository не изменён.
- После фильтров: Larastan level 8 без ошибок, 33 passed / 418 assertions, Pint
  passed; реальный комбинированный запрос 200 и duplicate statuses 422.
- Независимый аудит фильтров после исправлений: Critical/Important отсутствуют.
- В Postman создан personal workspace `Laravel Task API` и Collection v2.1
  `Task API v1` со всеми пятью endpoint'ами, variables и status-code tests.
- Read-back Postman подтвердил personal visibility и пять актуальных v1 requests;
  публичная документация, mocks и monitors не создавались.
- Добавлена сущность категории с пятью CRUD endpoint'ами; задача содержит nullable
  `category_id`, а удаление используемой категории возвращает 409.
- Задачи фильтруются по `category_ids[]` через OR внутри категорий и AND со
  `statuses[]` и `due_date`.
- Новый JSON state имеет task/category counters и collections. Валидный legacy
  state читается без записи и мигрирует при следующем успешном изменении.
- Postman collection расширена до 10 requests и переменной `category_id`; full
  read-back подтвердил состав в прежнем personal workspace.
- Финальная проверка: 44 passed / 573 assertions, Larastan level 8 без ошибок,
  Pint passed, ровно 10 API v1 routes; реальный HTTP вернул 201/200/409/422.
- Независимый аудит нашёл string `category_id`, приводивший к 500; добавлены
  strict validation rule и POST/PATCH regression tests. Остаточные findings закрыты.
- Добавлен runtime-пакет Scramble 0.13.41 и OpenAPI 3.1 документация всех 10
  операций непосредственно в controller/FormRequest PHP-коде.
- Локальные `/docs/api` и `/docs/api.json` защищены middleware без auth/session/DB;
  вне окружения local возвращается 403.
- `composer docs` экспортирует корневой `api.json`; артефакт существует, валиден,
  игнорируется `.gitignore` и не отслеживается Git.
- OpenAPI contract test проверяет операции, фильтры, request constraints, ответы и
  отсутствие body у 204. После интеграции: 47 passed / 613 assertions, Larastan
  level 8 без ошибок по 55 файлам, Pint и Composer validate проходят.
- Реальный HTTP подтвердил docs 200, POST 201, validation 422, а также корректную
  обработку отсутствующего, пустого и повреждённого JSON-хранилища.
- Независимый аудит Scramble не нашёл Critical; найденные расхождения validation
  constraints исправлены через document transformer и точные assertions.

## В работе

Нет.

## Не начато

Нет пунктов утверждённого плана.

## Активные инструменты

- Git 2.53.0.
- Codex, GPT-5.
- PHP 8.2.33 из `packages.sury.org/php`.
- Composer 2.10.2 (официальный PHAR).
- Laravel 12.66.0, PHPUnit 11.5.56, Pint 1.30.4 внутри `naive-run/`.
- Laravel 12.66.0, PHPUnit 11.5.56, Pint 1.30.4 в основном проекте.
- Larastan 3.10.0 и транзитивный PHPStan 2.2.8; настроен level 8.
- Scramble 0.13.41; OpenAPI export запускается через `composer docs`.
- Подключённый Postman connector; personal workspace ID
  `bdf83f6a-5b7c-42cd-9a94-f970641f71fc`.

## Известные проблемы

- `docker-compose.yml` сохранён на диске как неотслеживаемый файл и не входит в
  root-коммит по просьбе пользователя.
- Наивная реализация намеренно расходится с `SPEC.md`: использует только `title` и
  `completed`, JSON-массив и `max(id) + 1`; это материал для сравнения, не основа
  будущей реализации.
- Конкурентные записи и нагрузка наивного варианта не проверялись.
- Журнал сессии был заполнен не сразу, а после исходного Git-коммита; причина
  зафиксирована в `sessions/session-1.md`.
- Нагрузочные характеристики не измерялись; для маленького учебного API это вне
  согласованного объёма.
- Частичная запись проверяется сравнением числа записанных байтов, но отдельного
  regression-теста short write нет: для него потребовался бы seam файловой системы.
- Некоторые общие test helpers допускают дополнительные details; критичные случаи
  пустого PATCH и framework 404/405 проверяются exact JSON.
- Неоговорённые API exceptions нормализуются в безопасный 500, даже если новый
  framework-сценарий мог бы иметь иной HTTP-статус (например, 413).
- Поле даты и timestamps в сгенерированных response schemas имеют тип `string`
  без OpenAPI format: для их уточнения потребовались бы отдельные response DTO.
- UI Scramble проверен по HTTP, но не открывался в графическом браузере; стандартный
  Elements renderer получает frontend assets с CDN.

## Следующий шаг

Scramble интегрирован и проверен. Следующий шаг — просмотреть итоговый коммит и
подготовить репозиторий к сдаче.
