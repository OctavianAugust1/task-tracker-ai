<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\TaskRepository;
use App\Exceptions\CorruptTaskStorage;
use App\Repositories\JsonTaskRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * @phpstan-import-type Task from TaskRepository
 * @phpstan-import-type NewTask from TaskRepository
 *
 * @phpstan-type State array{next_task_id: positive-int, next_category_id: positive-int, tasks: list<Task>, categories: list<mixed>}
 */
#[CoversClass(JsonTaskRepository::class)]
final class JsonTaskRepositoryTest extends TestCase
{
    private string $temporaryDirectory;

    private string $tasksFile;

    private JsonTaskRepository $store;

    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->temporaryDirectory = sys_get_temp_dir().'/json-task-repository-'.bin2hex(random_bytes(8));
        mkdir($this->temporaryDirectory, 0700, true);
        $this->tasksFile = $this->temporaryDirectory.'/tasks.json';
        $this->store = new JsonTaskRepository($this->tasksFile);
        $this->files = new Filesystem;
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporaryDirectory.'/*') ?: [] as $temporaryFile) {
            unlink($temporaryFile);
        }

        rmdir($this->temporaryDirectory);

        parent::tearDown();
    }

    /**
     * Проверяет чтение отсутствующего и пустого файла как пустого состояния.
     */
    public function test_missing_and_empty_files_are_read_as_empty_without_creating_the_data_file(): void
    {
        $this->assertSame([], $this->store->all());
        $this->assertFileDoesNotExist($this->tasksFile);

        $this->files->put($this->tasksFile, '');

        $this->assertSame([], $this->store->all());
        $this->assertSame('', $this->files->get($this->tasksFile));
    }

    /**
     * Проверяет монотонность идентификаторов после удаления задачи.
     */
    public function test_deleted_ids_are_not_reused(): void
    {
        $first = $this->store->create($this->taskWithoutId('First'));
        $second = $this->store->create($this->taskWithoutId('Second'));

        $this->assertSame(1, $first['id']);
        $this->assertSame(2, $second['id']);
        $this->assertTrue($this->store->delete(2));

        $third = $this->store->create($this->taskWithoutId('Third'));

        $this->assertSame(3, $third['id']);
        $this->assertSame(4, $this->readState()['next_task_id']);
    }

    /**
     * Проверяет отклонение неверной структуры корня и сохранённых задач.
     */
    public function test_invalid_root_and_task_structures_are_rejected(): void
    {
        $invalidStates = [
            [],
            ['next_id' => 1, 'tasks' => [], 'extra' => true],
            ['next_id' => 0, 'tasks' => []],
            ['next_id' => 1, 'tasks' => ['not-a-list' => []]],
            ['next_id' => 1, 'tasks' => [['id' => 1]]],
            ['next_id' => 2, 'tasks' => [$this->task(), $this->task()]],
            ['next_id' => 1, 'tasks' => [$this->task()]],
        ];

        foreach ($invalidStates as $invalidState) {
            $this->files->put($this->tasksFile, json_encode($invalidState, JSON_THROW_ON_ERROR));

            try {
                $this->store->all();
                $this->fail('Invalid storage was accepted.');
            } catch (CorruptTaskStorage) {
                $this->addToAssertionCount(1);
            }
        }

        $this->files->put($this->tasksFile, '{"next_id":1,"tasks":{}}');

        $this->expectException(CorruptTaskStorage::class);
        $this->store->all();
    }

    /**
     * Проверяет повреждение нового state: категории и ссылки задач на них.
     */
    public function test_invalid_category_state_is_rejected(): void
    {
        $category = [
            'id' => 1,
            'name' => 'Work',
            'created_at' => '2026-08-15T10:00:00Z',
            'updated_at' => '2026-08-15T10:00:00Z',
        ];
        $task = [...$this->task(), 'category_id' => 1];
        $base = [
            'next_task_id' => 2,
            'next_category_id' => 2,
            'tasks' => [$task],
            'categories' => [$category],
        ];
        $invalidStates = [
            [...$base, 'categories' => [$category, $category], 'next_category_id' => 3],
            [...$base, 'categories' => [$category, [...$category, 'id' => 2, 'name' => 'work']], 'next_category_id' => 3],
            [...$base, 'categories' => [[...$category, 'name' => ' ']]],
            [...$base, 'tasks' => [[...$task, 'category_id' => 999]]],
        ];

        foreach ($invalidStates as $invalidState) {
            $contents = json_encode($invalidState, JSON_THROW_ON_ERROR);
            $this->files->put($this->tasksFile, $contents);

            try {
                $this->store->allCategories();
                $this->fail('Invalid category storage was accepted.');
            } catch (CorruptTaskStorage) {
                $this->assertSame($contents, $this->files->get($this->tasksFile));
            }
        }
    }

    /**
     * Проверяет, что category mutation не заменяет файл с dangling-ссылкой.
     */
    public function test_category_mutation_preserves_corrupted_storage(): void
    {
        $contents = json_encode([
            'next_task_id' => 2,
            'next_category_id' => 1,
            'tasks' => [[...$this->task(), 'category_id' => 999]],
            'categories' => [],
        ], JSON_THROW_ON_ERROR);
        $this->files->put($this->tasksFile, $contents);

        try {
            $this->store->createCategory([
                'name' => 'Must not be written',
                'created_at' => '2026-08-15T10:00:00Z',
                'updated_at' => '2026-08-15T10:00:00Z',
            ]);
            $this->fail('Corrupted storage was replaced by category mutation.');
        } catch (CorruptTaskStorage) {
            $this->assertSame($contents, $this->files->get($this->tasksFile));
        }
    }

    /**
     * Проверяет, что изменение не перезаписывает повреждённый JSON-файл.
     */
    public function test_corrupted_storage_is_not_replaced_by_a_mutation(): void
    {
        $this->files->put($this->tasksFile, '{broken');

        try {
            $this->store->create($this->taskWithoutId('Must not be written'));
            $this->fail('Corrupted storage was accepted.');
        } catch (CorruptTaskStorage) {
            $this->assertSame('{broken', $this->files->get($this->tasksFile));
            $this->assertSame([], glob($this->temporaryDirectory.'/.tasks-*') ?: []);
        }
    }

    /**
     * Проверяет отсутствие физической записи при неизменившейся задаче.
     */
    public function test_idempotent_update_does_not_rewrite_storage(): void
    {
        $task = $this->store->create($this->taskWithoutId());
        $before = $this->files->get($this->tasksFile);

        $updated = $this->store->update($task['id'], fn (array $stored): array => $stored);

        $this->assertSame($task, $updated);
        $this->assertSame($before, $this->files->get($this->tasksFile));
    }

    /** @return State */
    private function readState(): array
    {
        return json_decode(
            $this->files->get($this->tasksFile),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    /** @return Task */
    private function task(): array
    {
        return [
            'id' => 1,
            'title' => 'Task',
            'description' => null,
            'status' => 'todo',
            'due_date' => null,
            'category_id' => null,
            'created_at' => '2026-08-15T10:00:00Z',
            'updated_at' => '2026-08-15T10:00:00Z',
        ];
    }

    /** @return NewTask */
    private function taskWithoutId(string $title = 'Task'): array
    {
        if ($title === '') {
            throw new \InvalidArgumentException('Test title must not be empty.');
        }

        $task = $this->task();
        unset($task['id']);
        $task['title'] = $title;

        return $task;
    }
}
