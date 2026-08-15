<?php

namespace Tests\Unit;

use App\Contracts\TaskRepository;
use App\Services\TaskService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaskService::class)]
final class TaskServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private TaskRepository&MockInterface $repository;

    private TaskService $service;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var TaskRepository&MockInterface $repository */
        $repository = Mockery::mock(TaskRepository::class);
        $this->repository = $repository;
        $this->service = new TaskService($this->repository);
    }

    /**
     * Проверяет фильтрацию по статусу и сортировку задач внутри сервиса.
     */
    public function test_list_filters_and_sorts_repository_tasks(): void
    {
        $this->repository
            ->shouldReceive('all')
            ->once()
            ->andReturn([
                $this->task(id: 3, status: 'todo'),
                $this->task(id: 1, status: 'done'),
                $this->task(id: 2, status: 'todo'),
            ]);

        $tasks = $this->service->list('todo');

        $this->assertSame([2, 3], array_column($tasks, 'id'));
    }

    /**
     * Проверяет добавление значений по умолчанию и временных меток при создании.
     */
    public function test_create_adds_defaults_and_timestamps(): void
    {
        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $task): bool {
                $this->assertSame('Task', $task['title']);
                $this->assertNull($task['description']);
                $this->assertSame('todo', $task['status']);
                $this->assertNull($task['due_date']);
                $this->assertMatchesRegularExpression(
                    '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
                    $task['created_at'],
                );
                $this->assertSame($task['created_at'], $task['updated_at']);

                return true;
            }))
            ->andReturnUsing(fn (array $task): array => ['id' => 1, ...$task]);

        $task = $this->service->create(['title' => 'Task']);

        $this->assertSame(1, $task['id']);
    }

    /**
     * Проверяет делегирование поиска задачи репозиторию.
     */
    public function test_find_returns_repository_result(): void
    {
        $expected = $this->task();
        $this->repository->shouldReceive('find')->once()->with(1)->andReturn($expected);

        $actual = $this->service->find(1);

        $this->assertSame($expected, $actual);
    }

    /**
     * Проверяет, что обновление отсутствующей задачи не вызывает запись.
     */
    public function test_update_returns_null_when_task_is_missing(): void
    {
        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with(99, Mockery::type('callable'))
            ->andReturnNull();

        $result = $this->service->update(99, ['title' => 'Changed']);

        $this->assertNull($result);
    }

    /**
     * Проверяет возврат неизменённой задачи из атомарной операции репозитория.
     */
    public function test_unchanged_update_returns_the_same_task(): void
    {
        $task = $this->task();
        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with(1, Mockery::type('callable'))
            ->andReturnUsing(fn (int $id, callable $update): array => $update($task));

        $result = $this->service->update(1, ['title' => 'Task']);

        $this->assertSame($task, $result);
    }

    /**
     * Проверяет объединение изменённых полей и обновление временной метки.
     */
    public function test_changed_update_sends_complete_task_to_repository(): void
    {
        $task = $this->task();
        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with(1, Mockery::type('callable'))
            ->andReturnUsing(function (int $id, callable $update) use ($task): array {
                $updated = $update($task);
                $this->assertSame('Changed', $updated['title']);
                $this->assertSame($task['created_at'], $updated['created_at']);
                $this->assertNotSame($task['updated_at'], $updated['updated_at']);

                return $updated;
            });

        $result = $this->service->update(1, ['title' => 'Changed']);

        $this->assertSame('Changed', $result['title']);
    }

    /**
     * Проверяет делегирование удаления задачи репозиторию.
     */
    public function test_delete_returns_repository_result(): void
    {
        $this->repository->shouldReceive('delete')->once()->with(1)->andReturnTrue();

        $deleted = $this->service->delete(1);

        $this->assertTrue($deleted);
    }

    private function task(int $id = 1, string $status = 'todo'): array
    {
        return [
            'id' => $id,
            'title' => 'Task',
            'description' => null,
            'status' => $status,
            'due_date' => null,
            'created_at' => '2026-08-15T10:00:00Z',
            'updated_at' => '2026-08-15T10:00:00Z',
        ];
    }
}
