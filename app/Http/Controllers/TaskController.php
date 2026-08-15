<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListTasksRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Responses\ApiErrorResponse;
use App\Services\JsonTaskStore;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class TaskController extends Controller
{
    public function __construct(private readonly JsonTaskStore $store) {}

    public function index(ListTasksRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->store->all($request->validated('status')),
        ]);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->store->create($request->validated()),
        ], Response::HTTP_CREATED);
    }

    public function show(int $id): JsonResponse
    {
        $task = $this->store->find($id);

        return $task === null
            ? $this->notFound()
            : response()->json(['data' => $task]);
    }

    public function update(UpdateTaskRequest $request, int $id): JsonResponse
    {
        $task = $this->store->update($id, $request->validated());

        return $task === null
            ? $this->notFound()
            : response()->json(['data' => $task]);
    }

    public function destroy(int $id): Response
    {
        if (! $this->store->delete($id)) {
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
