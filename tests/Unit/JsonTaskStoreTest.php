<?php

namespace Tests\Unit;

use App\Exceptions\CorruptTaskStorage;
use App\Services\JsonTaskStore;
use PHPUnit\Framework\TestCase;

final class JsonTaskStoreTest extends TestCase
{
    private string $temporaryDirectory;

    private string $tasksFile;

    private JsonTaskStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir().'/json-task-store-'.bin2hex(random_bytes(8));
        mkdir($this->temporaryDirectory, 0700, true);
        $this->tasksFile = $this->temporaryDirectory.'/tasks.json';
        $this->store = new JsonTaskStore($this->tasksFile);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporaryDirectory.'/*') ?: [] as $temporaryFile) {
            unlink($temporaryFile);
        }

        rmdir($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_missing_and_empty_files_are_read_as_empty_without_creating_the_data_file(): void
    {
        $this->assertSame([], $this->store->all());
        $this->assertFileDoesNotExist($this->tasksFile);

        file_put_contents($this->tasksFile, '');

        $this->assertSame([], $this->store->all());
        $this->assertSame('', file_get_contents($this->tasksFile));
    }

    public function test_deleted_ids_are_not_reused(): void
    {
        $first = $this->store->create(['title' => 'First']);
        $second = $this->store->create(['title' => 'Second']);

        $this->assertSame(1, $first['id']);
        $this->assertSame(2, $second['id']);
        $this->assertTrue($this->store->delete(2));

        $third = $this->store->create(['title' => 'Third']);

        $this->assertSame(3, $third['id']);
        $this->assertSame(4, $this->readState()['next_id']);
    }

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
            file_put_contents($this->tasksFile, json_encode($invalidState, JSON_THROW_ON_ERROR));

            try {
                $this->store->all();
                $this->fail('Invalid storage was accepted.');
            } catch (CorruptTaskStorage) {
                $this->addToAssertionCount(1);
            }
        }

        file_put_contents($this->tasksFile, '{"next_id":1,"tasks":{}}');

        $this->expectException(CorruptTaskStorage::class);
        $this->store->all();
    }

    public function test_corrupted_storage_is_not_replaced_by_a_mutation(): void
    {
        file_put_contents($this->tasksFile, '{broken');

        try {
            $this->store->create(['title' => 'Must not be written']);
            $this->fail('Corrupted storage was accepted.');
        } catch (CorruptTaskStorage) {
            $this->assertSame('{broken', file_get_contents($this->tasksFile));
            $this->assertSame([], glob($this->temporaryDirectory.'/.tasks-*') ?: []);
        }
    }

    public function test_idempotent_update_does_not_rewrite_storage(): void
    {
        $task = $this->store->create(['title' => 'Task']);
        $before = file_get_contents($this->tasksFile);

        $updated = $this->store->update($task['id'], ['title' => 'Task']);

        $this->assertSame($task, $updated);
        $this->assertSame($before, file_get_contents($this->tasksFile));
    }

    private function readState(): array
    {
        return json_decode(
            file_get_contents($this->tasksFile),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    private function task(): array
    {
        return [
            'id' => 1,
            'title' => 'Task',
            'description' => null,
            'status' => 'todo',
            'due_date' => null,
            'created_at' => '2026-08-15T10:00:00Z',
            'updated_at' => '2026-08-15T10:00:00Z',
        ];
    }
}
