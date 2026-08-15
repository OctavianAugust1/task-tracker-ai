<?php

namespace App\Services;

use App\Exceptions\CorruptTaskStorage;
use App\Exceptions\TaskStorageException;
use DateTimeImmutable;
use JsonException;

final class JsonTaskStore
{
    private const TASK_FIELDS = [
        'id',
        'title',
        'description',
        'status',
        'due_date',
        'created_at',
        'updated_at',
    ];

    public function __construct(private readonly ?string $path = null) {}

    public function all(?string $status = null): array
    {
        return $this->withLock(LOCK_SH, function () use ($status): array {
            $tasks = $this->readState()['tasks'];

            if ($status !== null) {
                $tasks = array_values(array_filter(
                    $tasks,
                    fn (array $task): bool => $task['status'] === $status,
                ));
            }

            usort($tasks, fn (array $left, array $right): int => $left['id'] <=> $right['id']);

            return $tasks;
        });
    }

    public function find(int $id): ?array
    {
        return $this->withLock(LOCK_SH, function () use ($id): ?array {
            foreach ($this->readState()['tasks'] as $task) {
                if ($task['id'] === $id) {
                    return $task;
                }
            }

            return null;
        });
    }

    public function create(array $attributes): array
    {
        return $this->mutate(function (array &$state) use ($attributes): array {
            $timestamp = $this->timestamp();
            $task = [
                'id' => $state['next_id'],
                'title' => $attributes['title'],
                'description' => $attributes['description'] ?? null,
                'status' => $attributes['status'] ?? 'todo',
                'due_date' => $attributes['due_date'] ?? null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];

            $state['next_id']++;
            $state['tasks'][] = $task;

            return $task;
        });
    }

    public function update(int $id, array $attributes): ?array
    {
        return $this->mutate(function (array &$state, bool &$changed) use ($id, $attributes): ?array {
            foreach ($state['tasks'] as $index => $task) {
                if ($task['id'] !== $id) {
                    continue;
                }

                $updated = array_replace($task, $attributes);

                if ($updated === $task) {
                    $changed = false;

                    return $task;
                }

                $updated['updated_at'] = $this->timestamp();
                $state['tasks'][$index] = $updated;

                return $updated;
            }

            $changed = false;

            return null;
        });
    }

    public function delete(int $id): bool
    {
        return $this->mutate(function (array &$state, bool &$changed) use ($id): bool {
            foreach ($state['tasks'] as $index => $task) {
                if ($task['id'] !== $id) {
                    continue;
                }

                array_splice($state['tasks'], $index, 1);

                return true;
            }

            $changed = false;

            return false;
        });
    }

    private function mutate(callable $operation): mixed
    {
        return $this->withLock(LOCK_EX, function () use ($operation): mixed {
            $state = $this->readState();
            $changed = true;
            $result = $operation($state, $changed);

            if ($changed) {
                $this->writeState($state);
            }

            return $result;
        });
    }

    private function withLock(int $operation, callable $callback): mixed
    {
        $this->ensureDirectoryExists();

        $lock = @fopen($this->lockPath(), 'c+');

        if ($lock === false) {
            throw new TaskStorageException('Unable to open task storage lock.');
        }

        try {
            if (! flock($lock, $operation)) {
                throw new TaskStorageException('Unable to lock task storage.');
            }

            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function readState(): array
    {
        if (! is_file($this->filePath())) {
            return $this->emptyState();
        }

        $contents = @file_get_contents($this->filePath());

        if ($contents === false) {
            throw new TaskStorageException('Unable to read task storage.');
        }

        if ($contents === '') {
            return $this->emptyState();
        }

        try {
            $state = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new CorruptTaskStorage('Task storage contains invalid JSON.', previous: $exception);
        }

        $this->assertValidState($state);

        return $state;
    }

    private function writeState(array $state): void
    {
        try {
            $contents = json_encode(
                $state,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ).PHP_EOL;
        } catch (JsonException $exception) {
            throw new TaskStorageException('Unable to encode task storage.', previous: $exception);
        }

        $temporaryPath = @tempnam($this->directory(), '.tasks-');

        if ($temporaryPath === false) {
            throw new TaskStorageException('Unable to create a temporary storage file.');
        }

        try {
            if (@file_put_contents($temporaryPath, $contents) === false) {
                throw new TaskStorageException('Unable to write task storage.');
            }

            if (! @rename($temporaryPath, $this->filePath())) {
                throw new TaskStorageException('Unable to replace task storage.');
            }
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private function assertValidState(mixed $state): void
    {
        if (! is_array($state) || ! $this->hasExactKeys($state, ['next_id', 'tasks'])) {
            throw new CorruptTaskStorage('Task storage has an invalid root structure.');
        }

        if (! is_int($state['next_id']) || $state['next_id'] < 1) {
            throw new CorruptTaskStorage('Task storage has an invalid next_id.');
        }

        if (! is_array($state['tasks']) || ! array_is_list($state['tasks'])) {
            throw new CorruptTaskStorage('Task storage tasks must be a list.');
        }

        $ids = [];

        foreach ($state['tasks'] as $task) {
            $this->assertValidTask($task);

            if (isset($ids[$task['id']])) {
                throw new CorruptTaskStorage('Task storage contains duplicate IDs.');
            }

            $ids[$task['id']] = true;
        }

        if ($ids !== [] && $state['next_id'] <= max(array_keys($ids))) {
            throw new CorruptTaskStorage('Task storage next_id is not monotonic.');
        }
    }

    private function assertValidTask(mixed $task): void
    {
        if (! is_array($task) || ! $this->hasExactKeys($task, self::TASK_FIELDS)) {
            throw new CorruptTaskStorage('Task storage contains an invalid task structure.');
        }

        if (! is_int($task['id']) || $task['id'] < 1) {
            throw new CorruptTaskStorage('Task storage contains an invalid task ID.');
        }

        if (! is_string($task['title']) || trim($task['title']) !== $task['title']
            || mb_strlen($task['title']) < 1 || mb_strlen($task['title']) > 200) {
            throw new CorruptTaskStorage('Task storage contains an invalid title.');
        }

        if ($task['description'] !== null
            && (! is_string($task['description']) || mb_strlen($task['description']) > 2000)) {
            throw new CorruptTaskStorage('Task storage contains an invalid description.');
        }

        if (! is_string($task['status'])
            || ! in_array($task['status'], ['todo', 'in_progress', 'done'], true)) {
            throw new CorruptTaskStorage('Task storage contains an invalid status.');
        }

        if ($task['due_date'] !== null && ! $this->isExactDate($task['due_date'], 'Y-m-d')) {
            throw new CorruptTaskStorage('Task storage contains an invalid due date.');
        }

        if (! $this->isExactDate($task['created_at'], 'Y-m-d\TH:i:s\Z')
            || ! $this->isExactDate($task['updated_at'], 'Y-m-d\TH:i:s\Z')) {
            throw new CorruptTaskStorage('Task storage contains an invalid timestamp.');
        }
    }

    private function isExactDate(mixed $value, string $format): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

        if ($date === false) {
            return false;
        }

        $errors = DateTimeImmutable::getLastErrors();

        return ($errors === false
            || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format($format) === $value;
    }

    private function hasExactKeys(array $value, array $expectedKeys): bool
    {
        $actualKeys = array_keys($value);
        sort($actualKeys);
        sort($expectedKeys);

        return $actualKeys === $expectedKeys;
    }

    private function emptyState(): array
    {
        return ['next_id' => 1, 'tasks' => []];
    }

    private function timestamp(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }

    private function ensureDirectoryExists(): void
    {
        if (! is_dir($this->directory())
            && ! @mkdir($this->directory(), 0775, true)
            && ! is_dir($this->directory())) {
            throw new TaskStorageException('Unable to create task storage directory.');
        }
    }

    private function filePath(): string
    {
        return $this->path ?? config('tasks.file');
    }

    private function lockPath(): string
    {
        return $this->filePath().'.lock';
    }

    private function directory(): string
    {
        return dirname($this->filePath());
    }
}
