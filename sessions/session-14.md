# Сессия 14 — Публикация коллекции в Postman

- **Дата:** 2026-08-16
- **Ассистент:** Codex, GPT-5; Postman connector
- **Режим разрешений:** файловая система без ограничений и без запросов
  подтверждения; режим задан окружением. Пользователь явно разрешил создание
  workspace и API-коллекции во внешнем Postman-аккаунте; публичная публикация,
  monitors и mock servers не создаются.
- **Статус:** завершена

## Промпты

1. > Добавь в Postman API текущего проекта, и создай для этого новый workspace

## Размышления

Запрос требует внешнего изменения. Подключённый Postman connector умеет создать
workspace и Collection v2.1 напрямую. Под «API» выбран практический артефакт для
ручной работы — коллекция с пятью текущими endpoint'ами, а не публичная API-
документация или mock server. Workspace создаётся personal, чтобы случайно не
раскрыть учебный локальный API команде или публичной сети.

Коллекция использует только несекретные переменные `base_url` со значением
`http://127.0.0.1:8000`, `task_id=1` и `due_date=2026-08-20`. В ней должны быть
list с комбинированными фильтрами, show, create, patch и delete; JSON-запросы
содержат `Content-Type: application/json`, каждый request имеет базовый test
ожидаемого HTTP-кода. Реальный локальный сервер из облачного Postman не вызывается.

Postman API создал workspace ID `bdf83f6a-5b7c-42cd-9a94-f970641f71fc` и
collection UID `15088864-85dfa2f5-31b8-4a87-90bd-5d787cedbd3f`. В ответе чтения
workspace внутреннее поле `type` равно `team`, но определяющее доступ поле
`visibility` равно `personal`: workspace виден только владельцу, как и требовалось.

Full read-back подтвердил пять requests, актуальные `/api/v1/tasks` URL,
комбинированные `statuses[]` + `due_date`, JSON bodies, test scripts и три
несекретные variables. Документация публично не публиковалась, mock/monitor/API
Network не создавались, запросы к локальному серверу из Postman не запускались.

## Использованные инструменты

| Инструмент | Действие | Зачем |
|---|---|---|
| plugin-management skill | Проверена возможность подключить внешний Postman | Не предлагать ручной обход при доступном connector |
| Postman connector | Запланировано создание workspace и коллекции | Добавить API непосредственно в аккаунт пользователя |
| `apply_patch` | Созданы журнал и план до внешнего изменения | Соблюсти порядок курса |
| Postman `createWorkspace` | Создан personal workspace `Laravel Task API` | Изолировать артефакты текущего проекта |
| Postman `createCollection` | Создана Collection v2.1 `Task API v1` | Добавить пять endpoint'ов, variables и tests |
| Postman read-back | Прочитаны workspace и full collection | Проверить visibility, принадлежность и содержимое |

## Изменения в проекте

- `sessions/session-14.md` — создан журнал текущей сессии.
- `POSTMAN_PLAN.md` — записан состав внешних ресурсов и проверок.
- `README.md` — добавлены сведения о Postman workspace и collection.
- `sessions/TOOLS.md` — записано использование внешнего сервиса.
- `sessions/STATE.md` — обновлено текущее состояние проекта.
- Postman workspace `Laravel Task API` — создан во внешнем аккаунте.
- Postman collection `Task API v1` — создана с пятью requests.

## Финальный вердикт

Пункт закрыт. Personal workspace и коллекция из пяти запросов созданы и повторно
прочитаны через Postman API. Секреты, публичные ресурсы и внешние вызовы localhost
не создавались.
