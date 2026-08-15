<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListTasksRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Responses\ApiErrorResponse;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class TaskController extends Controller
{
    public function __construct(private readonly TaskService $service) {}

    public function index(ListTasksRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->list($request->validatedStatus()),
        ]);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->create($request->validatedTaskAttributes()),
        ], Response::HTTP_CREATED);
    }

    public function show(int $id): JsonResponse
    {
        $task = $this->service->find($id);

        return $task === null
            ? $this->notFound()
            : response()->json(['data' => $task]);
    }

    public function update(UpdateTaskRequest $request, int $id): JsonResponse
    {
        $task = $this->service->update($id, $request->validatedTaskAttributes());

        return $task === null
            ? $this->notFound()
            : response()->json(['data' => $task]);
    }

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
