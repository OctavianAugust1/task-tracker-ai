<?php

namespace App\Services;

use App\Contracts\TaskRepository;
use DateTimeImmutable;
use DateTimeZone;

final class TaskService
{
    public function __construct(private readonly TaskRepository $repository) {}

    public function list(?string $status = null): array
    {
        $tasks = $this->repository->all();

        if ($status !== null) {
            $tasks = array_values(array_filter(
                $tasks,
                fn (array $task): bool => $task['status'] === $status,
            ));
        }

        usort($tasks, fn (array $left, array $right): int => $left['id'] <=> $right['id']);

        return $tasks;
    }

    public function find(int $id): ?array
    {
        return $this->repository->find($id);
    }

    public function create(array $attributes): array
    {
        $timestamp = $this->timestamp();

        return $this->repository->create([
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'status' => $attributes['status'] ?? 'todo',
            'due_date' => $attributes['due_date'] ?? null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    public function update(int $id, array $attributes): ?array
    {
        return $this->repository->update($id, function (array $task) use ($attributes): array {
            $updated = array_replace($task, $attributes);

            if ($updated !== $task) {
                $updated['updated_at'] = $this->timestamp();
            }

            return $updated;
        });
    }

    public function delete(int $id): bool
    {
        return $this->repository->delete($id);
    }

    private function timestamp(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    }
}
