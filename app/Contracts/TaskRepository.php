<?php

namespace App\Contracts;

interface TaskRepository
{
    public function all(): array;

    public function find(int $id): ?array;

    public function create(array $task): array;

    public function update(int $id, callable $update): ?array;

    public function delete(int $id): bool;
}
