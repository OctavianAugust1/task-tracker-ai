<?php

namespace App\Http\Controllers;

use App\Exceptions\CorruptTaskStorage;
use App\Services\JsonTaskStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TaskController extends Controller
{
    public function __construct(private readonly JsonTaskStore $store) {}

    public function index(): JsonResponse
    {
        return $this->respond(fn (): array => $this->store->all());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'completed' => ['sometimes', 'boolean'],
        ]);

        return $this->respond(fn (): array => $this->store->create($data), 201);
    }

    public function show(int $task): JsonResponse
    {
        return $this->respond(function () use ($task): array {
            return $this->store->find($task) ?? abort(404, 'Task not found.');
        });
    }

    public function update(Request $request, int $task): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'completed' => ['sometimes', 'boolean'],
        ]);

        return $this->respond(function () use ($task, $data): array {
            return $this->store->update($task, $data) ?? abort(404, 'Task not found.');
        });
    }

    public function destroy(int $task): Response|JsonResponse
    {
        try {
            abort_unless($this->store->delete($task), 404, 'Task not found.');

            return response()->noContent();
        } catch (CorruptTaskStorage) {
            return response()->json(['message' => 'Task storage is corrupted.'], 500);
        }
    }

    private function respond(callable $operation, int $status = 200): JsonResponse
    {
        try {
            return response()->json($operation(), $status);
        } catch (CorruptTaskStorage) {
            return response()->json(['message' => 'Task storage is corrupted.'], 500);
        }
    }
}
