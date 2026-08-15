<?php

namespace App\Services;

use App\Exceptions\CorruptTaskStorage;

class JsonTaskStore
{
    public function all(): array
    {
        return $this->read();
    }

    public function find(int $id): ?array
    {
        foreach ($this->read() as $task) {
            if ($task['id'] === $id) {
                return $task;
            }
        }

        return null;
    }

    public function create(array $attributes): array
    {
        $tasks = $this->read();
        $now = now()->utc()->toIso8601String();
        $task = [
            'id' => empty($tasks) ? 1 : max(array_column($tasks, 'id')) + 1,
            'title' => $attributes['title'],
            'completed' => $attributes['completed'] ?? false,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $tasks[] = $task;
        $this->write($tasks);

        return $task;
    }

    public function update(int $id, array $attributes): ?array
    {
        $tasks = $this->read();

        foreach ($tasks as $index => $task) {
            if ($task['id'] !== $id) {
                continue;
            }

            $tasks[$index] = array_merge($task, $attributes, [
                'id' => $id,
                'updated_at' => now()->utc()->toIso8601String(),
            ]);
            $this->write($tasks);

            return $tasks[$index];
        }

        return null;
    }

    public function delete(int $id): bool
    {
        $tasks = $this->read();
        $remaining = array_values(array_filter(
            $tasks,
            fn (array $task): bool => $task['id'] !== $id,
        ));

        if (count($tasks) === count($remaining)) {
            return false;
        }

        $this->write($remaining);

        return true;
    }

    private function read(): array
    {
        $path = config('tasks.path');

        if (! is_file($path) || filesize($path) === 0) {
            return [];
        }

        $tasks = json_decode(file_get_contents($path), true);

        if (! is_array($tasks) || ! array_is_list($tasks)) {
            throw new CorruptTaskStorage('Task storage contains invalid JSON.');
        }

        return $tasks;
    }

    private function write(array $tasks): void
    {
        $path = config('tasks.path');
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode($tasks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL,
            LOCK_EX,
        );
    }
}
