<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\CategoryRepository;
use App\Contracts\TaskRepository;
use App\Exceptions\CategoryInUse;
use App\Exceptions\CorruptTaskStorage;
use App\Exceptions\DuplicateCategoryName;
use App\Exceptions\InvalidTaskCategory;
use App\Exceptions\TaskStorageException;
use DateTimeImmutable;
use JsonException;
use stdClass;

/**
 * @phpstan-import-type Task from TaskRepository
 * @phpstan-import-type NewTask from TaskRepository
 * @phpstan-import-type Category from CategoryRepository
 * @phpstan-import-type NewCategory from CategoryRepository
 *
 * @phpstan-type State array{next_task_id: positive-int, next_category_id: positive-int, tasks: list<Task>, categories: list<Category>}
 */
final class JsonTaskRepository implements CategoryRepository, TaskRepository
{
    private const TASK_FIELDS = [
        'id',
        'title',
        'description',
        'status',
        'due_date',
        'category_id',
        'created_at',
        'updated_at',
    ];

    public function __construct(private readonly string $path) {}

    /** @return list<Task> */
    public function all(): array
    {
        return $this->withLock(LOCK_SH, fn (): array => $this->readState()['tasks']);
    }

    /** @return Task|null */
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

    /**
     * @param  NewTask  $task
     * @return Task
     */
    public function create(array $task): array
    {
        return $this->mutate(function (array &$state) use ($task): array {
            $this->assertCategoryExists($state, $task['category_id']);
            $created = ['id' => $state['next_task_id'], ...$task];
            $state['next_task_id']++;
            $state['tasks'][] = $created;

            return [$created, true];
        });
    }

    /**
     * @param  callable(Task): Task  $update
     * @return Task|null
     */
    public function update(int $id, callable $update): ?array
    {
        return $this->mutate(function (array &$state) use ($id, $update): array {
            foreach ($state['tasks'] as $index => $storedTask) {
                if ($storedTask['id'] !== $id) {
                    continue;
                }

                $updatedTask = $update($storedTask);
                $this->assertCategoryExists($state, $updatedTask['category_id']);

                if ($storedTask === $updatedTask) {
                    return [$storedTask, false];
                }

                $state['tasks'][$index] = $updatedTask;

                return [$updatedTask, true];
            }

            return [null, false];
        });
    }

    public function delete(int $id): bool
    {
        return $this->mutate(function (array &$state) use ($id): array {
            foreach ($state['tasks'] as $index => $task) {
                if ($task['id'] !== $id) {
                    continue;
                }

                array_splice($state['tasks'], $index, 1);

                return [true, true];
            }

            return [false, false];
        });
    }

    /** @return list<Category> */
    public function allCategories(): array
    {
        return $this->withLock(LOCK_SH, fn (): array => $this->readState()['categories']);
    }

    /** @return Category|null */
    public function findCategory(int $id): ?array
    {
        return $this->withLock(LOCK_SH, function () use ($id): ?array {
            foreach ($this->readState()['categories'] as $category) {
                if ($category['id'] === $id) {
                    return $category;
                }
            }

            return null;
        });
    }

    /** @param NewCategory $category
     * @return Category
     */
    public function createCategory(array $category): array
    {
        return $this->mutate(function (array &$state) use ($category): array {
            $this->assertUniqueCategoryName($state, $category['name']);
            $created = ['id' => $state['next_category_id'], ...$category];
            $state['next_category_id']++;
            $state['categories'][] = $created;

            return [$created, true];
        });
    }

    /** @param callable(Category): Category $update
     * @return Category|null
     */
    public function updateCategory(int $id, callable $update): ?array
    {
        return $this->mutate(function (array &$state) use ($id, $update): array {
            foreach ($state['categories'] as $index => $stored) {
                if ($stored['id'] !== $id) {
                    continue;
                }
                $updated = $update($stored);
                $this->assertUniqueCategoryName($state, $updated['name'], $id);
                if ($updated === $stored) {
                    return [$stored, false];
                }
                $state['categories'][$index] = $updated;

                return [$updated, true];
            }

            return [null, false];
        });
    }

    public function deleteCategory(int $id): bool
    {
        return $this->mutate(function (array &$state) use ($id): array {
            foreach ($state['categories'] as $index => $category) {
                if ($category['id'] !== $id) {
                    continue;
                }
                foreach ($state['tasks'] as $task) {
                    if ($task['category_id'] === $id) {
                        throw new CategoryInUse('Category is assigned to a task.');
                    }
                }
                array_splice($state['categories'], $index, 1);

                return [true, true];
            }

            return [false, false];
        });
    }

    /**
     * @template TResult
     *
     * @param  callable(State&): array{TResult, bool}  $operation
     * @return TResult
     */
    private function mutate(callable $operation): mixed
    {
        return $this->withLock(LOCK_EX, function () use ($operation): mixed {
            $state = $this->readState();
            [$result, $changed] = $operation($state);

            if ($changed) {
                $this->writeState($state);
            }

            return $result;
        });
    }

    /**
     * @template TResult
     *
     * @param  int<0, 7>  $operation
     * @param  callable(): TResult  $callback
     * @return TResult
     */
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

    /** @return State */
    private function readState(): array
    {
        if (! is_file($this->path)) {
            return $this->emptyState();
        }

        $contents = @file_get_contents($this->path);

        if ($contents === false) {
            throw new TaskStorageException('Unable to read task storage.');
        }

        if ($contents === '') {
            return $this->emptyState();
        }

        try {
            $state = $this->normalizeDecodedState(
                json_decode($contents, false, 512, JSON_THROW_ON_ERROR),
            );
        } catch (JsonException $exception) {
            throw new CorruptTaskStorage('Task storage contains invalid JSON.', previous: $exception);
        }

        $this->assertValidState($state);

        return $state;
    }

    /** @param State $state */
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
            $writtenBytes = @file_put_contents($temporaryPath, $contents);

            if ($writtenBytes === false || $writtenBytes !== strlen($contents)) {
                throw new TaskStorageException('Unable to write task storage.');
            }

            if (! @rename($temporaryPath, $this->path)) {
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
        if (! is_array($state) || ! $this->hasExactKeys(
            $state,
            ['next_task_id', 'next_category_id', 'tasks', 'categories'],
        )) {
            throw new CorruptTaskStorage('Task storage has an invalid root structure.');
        }

        if (! is_int($state['next_task_id']) || $state['next_task_id'] < 1
            || ! is_int($state['next_category_id']) || $state['next_category_id'] < 1) {
            throw new CorruptTaskStorage('Task storage has an invalid next ID.');
        }

        if (! is_array($state['tasks']) || ! array_is_list($state['tasks'])
            || ! is_array($state['categories']) || ! array_is_list($state['categories'])) {
            throw new CorruptTaskStorage('Task storage collections must be lists.');
        }

        $ids = [];
        $categoryIds = [];

        foreach ($state['categories'] as $category) {
            $this->assertValidCategory($category);
            if (isset($categoryIds[$category['id']])) {
                throw new CorruptTaskStorage('Task storage contains duplicate category IDs.');
            }
            foreach ($state['categories'] as $other) {
                if ($other !== $category && is_array($other) && isset($other['name'])
                    && is_string($other['name']) && mb_strtolower($other['name']) === mb_strtolower($category['name'])) {
                    throw new CorruptTaskStorage('Task storage contains duplicate category names.');
                }
            }
            $categoryIds[$category['id']] = true;
        }

        foreach ($state['tasks'] as $task) {
            $this->assertValidTask($task);

            if (isset($ids[$task['id']])) {
                throw new CorruptTaskStorage('Task storage contains duplicate IDs.');
            }

            $ids[$task['id']] = true;

            if ($task['category_id'] !== null && ! isset($categoryIds[$task['category_id']])) {
                throw new CorruptTaskStorage('Task references a missing category.');
            }
        }

        if ($ids !== [] && $state['next_task_id'] <= max(array_keys($ids))) {
            throw new CorruptTaskStorage('Task storage next task ID is not monotonic.');
        }
        if ($categoryIds !== [] && $state['next_category_id'] <= max(array_keys($categoryIds))) {
            throw new CorruptTaskStorage('Task storage next category ID is not monotonic.');
        }
    }

    private function normalizeDecodedState(mixed $state): mixed
    {
        if (! $state instanceof stdClass) {
            return $state;
        }

        $normalized = get_object_vars($state);

        $legacy = $this->hasExactKeys($normalized, ['next_id', 'tasks']);

        if (isset($normalized['tasks']) && is_array($normalized['tasks'])) {
            $normalized['tasks'] = array_map(
                function (mixed $task) use ($legacy): mixed {
                    $normalizedTask = $task instanceof stdClass ? get_object_vars($task) : $task;
                    if ($legacy && is_array($normalizedTask)) {
                        $normalizedTask['category_id'] = null;
                    }

                    return $normalizedTask;
                },
                $normalized['tasks'],
            );
        }

        if ($legacy) {
            return [
                'next_task_id' => $normalized['next_id'],
                'next_category_id' => 1,
                'tasks' => $normalized['tasks'],
                'categories' => [],
            ];
        }

        if (isset($normalized['categories']) && is_array($normalized['categories'])) {
            $normalized['categories'] = array_map(
                fn (mixed $category): mixed => $category instanceof stdClass ? get_object_vars($category) : $category,
                $normalized['categories'],
            );
        }

        return $normalized;
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

        if ($task['category_id'] !== null && (! is_int($task['category_id']) || $task['category_id'] < 1)) {
            throw new CorruptTaskStorage('Task storage contains an invalid category ID.');
        }

        if (! $this->isExactDate($task['created_at'], 'Y-m-d\TH:i:s\Z')
            || ! $this->isExactDate($task['updated_at'], 'Y-m-d\TH:i:s\Z')) {
            throw new CorruptTaskStorage('Task storage contains an invalid timestamp.');
        }
    }

    private function assertValidCategory(mixed $category): void
    {
        if (! is_array($category) || ! $this->hasExactKeys(
            $category,
            ['id', 'name', 'created_at', 'updated_at'],
        )) {
            throw new CorruptTaskStorage('Task storage contains an invalid category structure.');
        }
        if (! is_int($category['id']) || $category['id'] < 1
            || ! is_string($category['name']) || trim($category['name']) !== $category['name']
            || mb_strlen($category['name']) < 1 || mb_strlen($category['name']) > 100
            || ! $this->isExactDate($category['created_at'], 'Y-m-d\TH:i:s\Z')
            || ! $this->isExactDate($category['updated_at'], 'Y-m-d\TH:i:s\Z')) {
            throw new CorruptTaskStorage('Task storage contains an invalid category.');
        }
    }

    /** @param State $state */
    private function assertCategoryExists(array $state, ?int $categoryId): void
    {
        if ($categoryId === null) {
            return;
        }
        foreach ($state['categories'] as $category) {
            if ($category['id'] === $categoryId) {
                return;
            }
        }
        throw new InvalidTaskCategory('Category does not exist.');
    }

    /** @param State $state */
    private function assertUniqueCategoryName(array $state, string $name, ?int $exceptId = null): void
    {
        foreach ($state['categories'] as $category) {
            if ($category['id'] !== $exceptId && mb_strtolower($category['name']) === mb_strtolower($name)) {
                throw new DuplicateCategoryName('Category name already exists.');
            }
        }
    }

    private function isExactDate(mixed $value, string $format): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

        return $date !== false && $date->format($format) === $value;
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @param  list<string>  $keys
     */
    private function hasExactKeys(array $value, array $keys): bool
    {
        $actual = array_keys($value);
        sort($actual);
        sort($keys);

        return $actual === $keys;
    }

    private function ensureDirectoryExists(): void
    {
        $directory = $this->directory();

        if (is_dir($directory)) {
            return;
        }

        if (! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new TaskStorageException('Unable to create task storage directory.');
        }
    }

    private function directory(): string
    {
        return dirname($this->path);
    }

    private function lockPath(): string
    {
        return $this->path.'.lock';
    }

    /** @return State */
    private function emptyState(): array
    {
        return [
            'next_task_id' => 1,
            'next_category_id' => 1,
            'tasks' => [],
            'categories' => [],
        ];
    }
}
