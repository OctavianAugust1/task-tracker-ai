<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListTasksRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Responses\ApiErrorResponse;
use App\OpenApi\ApiDocTypes;
use App\Services\TaskService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

#[Group('Tasks', 'Создание, просмотр, фильтрация, изменение и удаление задач.', weight: 1)]
final class TaskController extends Controller
{
    public function __construct(private readonly TaskService $service) {}

    #[Endpoint('listTasks', 'Получить список задач', 'Возвращает задачи по ID. Фильтры внутри массивов объединяются через OR, группы фильтров — через AND.')]
    #[ApiResponse(200, 'Список задач.', type: ApiDocTypes::TASK_LIST_RESPONSE)]
    #[ApiResponse(422, 'Параметры фильтра не прошли валидацию.', type: ApiDocTypes::ERROR_RESPONSE)]
    #[ApiResponse(500, 'Хранилище недоступно или повреждено.', type: ApiDocTypes::ERROR_RESPONSE)]
    public function index(ListTasksRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->list($request->validatedFilters()),
        ]);
    }

    #[Endpoint('createTask', 'Создать задачу', 'Создаёт задачу. Статус по умолчанию todo, категория и дата могут быть null.')]
    #[ApiResponse(201, 'Задача создана.', type: ApiDocTypes::TASK_RESPONSE)]
    #[ApiResponse(400, 'Тело содержит синтаксически неверный JSON.', type: ApiDocTypes::ERROR_RESPONSE)]
    #[ApiResponse(422, 'Тело не прошло валидацию.', type: ApiDocTypes::ERROR_RESPONSE)]
    #[ApiResponse(500, 'Хранилище недоступно или повреждено.', type: ApiDocTypes::ERROR_RESPONSE)]
    public function store(StoreTaskRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->create($request->validatedTaskAttributes()),
        ], Response::HTTP_CREATED);
    }

    #[Endpoint('getTask', 'Получить задачу', 'Возвращает задачу по положительному идентификатору.')]
    #[PathParameter('id', 'ID задачи.', type: 'int', infer: false, example: 1)]
    #[ApiResponse(200, 'Задача найдена.', type: ApiDocTypes::TASK_RESPONSE)]
    #[ApiResponse(404, 'Задача не найдена.', type: ApiDocTypes::ERROR_RESPONSE)]
    #[ApiResponse(500, 'Хранилище недоступно или повреждено.', type: ApiDocTypes::ERROR_RESPONSE)]
    public function show(int $id): JsonResponse
    {
        $task = $this->service->find($id);

        return $task === null
            ? $this->notFound()
            : response()->json(['data' => $task]);
    }

    #[Endpoint('updateTask', 'Изменить задачу', 'Частично изменяет хотя бы одно клиентское поле задачи.')]
    #[PathParameter('id', 'ID задачи.', type: 'int', infer: false, example: 1)]
    #[ApiResponse(200, 'Задача изменена или уже содержала эти значения.', type: ApiDocTypes::TASK_RESPONSE)]
    #[ApiResponse(400, 'Тело содержит синтаксически неверный JSON.', type: ApiDocTypes::ERROR_RESPONSE)]
    #[ApiResponse(404, 'Задача не найдена.', type: ApiDocTypes::ERROR_RESPONSE)]
    #[ApiResponse(422, 'Тело не прошло валидацию.', type: ApiDocTypes::ERROR_RESPONSE)]
    #[ApiResponse(500, 'Хранилище недоступно или повреждено.', type: ApiDocTypes::ERROR_RESPONSE)]
    public function update(UpdateTaskRequest $request, int $id): JsonResponse
    {
        $task = $this->service->update($id, $request->validatedTaskAttributes());

        return $task === null
            ? $this->notFound()
            : response()->json(['data' => $task]);
    }

    #[Endpoint('deleteTask', 'Удалить задачу', 'Физически удаляет задачу без возможности восстановления.')]
    #[PathParameter('id', 'ID задачи.', type: 'int', infer: false, example: 1)]
    #[ApiResponse(404, 'Задача не найдена.', type: ApiDocTypes::ERROR_RESPONSE)]
    #[ApiResponse(500, 'Хранилище недоступно или повреждено.', type: ApiDocTypes::ERROR_RESPONSE)]
    public function destroy(int $id): Response
    {
        if (! $this->service->delete($id)) {
            return $this->notFound();
        }

        return response()->noContent();
    }

    private function notFound(): JsonResponse
    {
        return ApiErrorResponse::make(
            code: 'task_not_found',
            message: 'Task not found',
            details: [],
            status: Response::HTTP_NOT_FOUND,
        );
    }
}
