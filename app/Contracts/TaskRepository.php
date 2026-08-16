<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * @phpstan-type Task array{id: positive-int, title: non-empty-string, description: string|null, status: 'todo'|'in_progress'|'done', due_date: string|null, category_id: positive-int|null, created_at: string, updated_at: string}
 * @phpstan-type NewTask array{title: non-empty-string, description: string|null, status: 'todo'|'in_progress'|'done', due_date: string|null, category_id: positive-int|null, created_at: string, updated_at: string}
 */
interface TaskRepository
{
    /** @return list<Task> */
    public function all(): array;

    /** @return Task|null */
    public function find(int $id): ?array;

    /**
     * @param  NewTask  $task
     * @return Task
     */
    public function create(array $task): array;

    /**
     * @param  callable(Task): Task  $update
     * @return Task|null
     */
    public function update(int $id, callable $update): ?array;

    public function delete(int $id): bool;
}
