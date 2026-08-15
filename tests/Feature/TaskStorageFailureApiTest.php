<?php

namespace Tests\Feature;

use Tests\TestCase;

final class TaskStorageFailureApiTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir().'/task-storage-failure-'.bin2hex(random_bytes(8));
        mkdir($this->temporaryDirectory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporaryDirectory.'/*') ?: [] as $path) {
            if (is_dir($path)) {
                rmdir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_lock_path_failure_returns_a_safe_500(): void
    {
        $blockingFile = $this->temporaryDirectory.'/blocked';
        file_put_contents($blockingFile, 'keep me');
        config()->set('tasks.file', $blockingFile.'/tasks.json');

        $this->getJson('/api/tasks')
            ->assertStatus(500)
            ->assertExactJson([
                'error' => [
                    'code' => 'storage_error',
                    'message' => 'Task storage is unavailable',
                    'details' => [],
                ],
            ]);

        $this->assertSame('keep me', file_get_contents($blockingFile));
    }

    public function test_rename_failure_returns_500_without_replacing_the_target(): void
    {
        $target = $this->temporaryDirectory.'/tasks.json';
        mkdir($target);
        config()->set('tasks.file', $target);

        $this->postJson('/api/tasks', ['title' => 'Must not be persisted'])
            ->assertStatus(500)
            ->assertJsonPath('error.code', 'storage_error')
            ->assertJsonPath('error.details', []);

        $this->assertDirectoryExists($target);
        $this->assertSame([], glob($this->temporaryDirectory.'/.tasks-*') ?: []);
    }

    public function test_corruption_takes_precedence_over_a_missing_task_404(): void
    {
        $target = $this->temporaryDirectory.'/tasks.json';
        file_put_contents($target, '{broken');
        config()->set('tasks.file', $target);

        $this->getJson('/api/tasks/999')
            ->assertStatus(500)
            ->assertJsonPath('error.code', 'storage_corrupted');

        $this->assertSame('{broken', file_get_contents($target));
    }
}
