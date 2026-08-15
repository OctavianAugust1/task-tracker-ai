# План рефакторинга архитектуры и тестов

## Цель

Разделить HTTP, бизнес-правила и файловое хранение через DI, перевести API на
версию `v1` и привести тесты к `TESTS_INSTRUCTION.md`, адаптировав требования к
файловому хранилищу и неизменяемому стеку Laravel 12 / PHPUnit 11.

## Целевая архитектура

1. `TaskController` принимает `TaskService` через constructor injection и отвечает
   только за HTTP-коды и JSON envelope.
2. `TaskService` принимает `TaskRepository` и реализует use cases: список и фильтр,
   создание с defaults/timestamps, поиск, идемпотентное обновление и удаление.
3. `TaskRepository` задаёт независимый от носителя контракт получения и атомарного
   изменения состояния задач.
4. `JsonTaskRepository` — единственный слой, который знает путь файла и выполняет
   directory/lock/read/validate/temp-write/rename.
5. `AppServiceProvider` связывает интерфейс с JSON-реализацией и передаёт путь из
   `config('tasks.file')`; контроллер и сервис путь или файловые функции не знают.

## Этапы

1. Обновить `SPEC.md` и документацию: пять маршрутов становятся
   `/api/v1/tasks[...]`; старые unversioned routes отсутствуют.
2. Сначала написать/перестроить тесты под новую границу:
   - unit-тесты `TaskService` на чистом PHPUnit с Mockery, AAA, русскими PHPDoc и
     `CoversClass`; repository полностью замокан;
   - feature-тесты versioned HTTP API через реальный `JsonTaskRepository` и
     отдельный временный файл, без mocks, с `Http::preventStrayRequests()`;
   - integration/unit-набор JSON repository с реальными временными файлами;
   - не использовать Reflection и `file_get_contents` в тестах.
3. Выделить `TaskRepository`, `JsonTaskRepository` и `TaskService`; зарегистрировать
   binding в контейнере и сделать контроллер тонким.
4. Перевести маршруты на `v1`, обновить README, fixtures и все URL тестов.
5. Запустить полный red/green цикл, Pint, route list и реальный HTTP smoke test на
   `127.0.0.1:8000` с временным файлом.
6. Проверить `git diff --stat/status`, обновить журнал и `STATE.md`, затем сделать
   один отдельный содержательный коммит без `docker-compose.yml`.

## Адаптация спорных пунктов инструкции

- `DatabaseTransactions` не применяется: в проекте по правилам запрещены БД,
  Eloquent и миграции. Эквивалент изоляции — новый временный JSON-файл на тест.
- Версии Laravel/PHPUnit не понижаются до указанных во вводной инструкции.
- Infection не устанавливается без отдельного согласования зависимости.
- Feature-тесты не мокают service/repository и проверяют полный путь до файла.

## Критерии готовности

- В controller/service отсутствуют файловые операции и знание пути хранилища.
- Бизнес-правила отсутствуют в controller и покрыты isolated unit-тестами сервиса.
- Только JSON repository обращается к файлу.
- Контейнер Laravel разрешает все зависимости через интерфейс.
- Есть ровно пять маршрутов `/api/v1/tasks`, а `/api/tasks` возвращает 404.
- Feature-тесты используют только временные файлы и запрещают случайный HTTP.
- Все тесты и Pint проходят, реальные успешный и ошибочный HTTP-ответы показаны.
