<?php

namespace Tests\Feature;

use App\Http\Controllers\TaskController;
use App\Repositories\JsonTaskRepository;
use App\Services\TaskService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(TaskController::class)]
#[CoversClass(TaskService::class)]
#[CoversClass(JsonTaskRepository::class)]
final class TaskStorageFailureApiTest extends TestCase
{
    private string $temporaryDirectory;

    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->files = new Filesystem;

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

    /**
     * Проверяет безопасный API-ответ, когда lock-файл невозможно открыть.
     */
    public function test_lock_path_failure_returns_a_safe_500(): void
    {
        $blockingFile = $this->temporaryDirectory.'/blocked';
        $this->files->put($blockingFile, 'keep me');
        config()->set('tasks.file', $blockingFile.'/tasks.json');

        $this->getJson('/api/v1/tasks')
            ->assertStatus(500)
            ->assertExactJson([
                'error' => [
                    'code' => 'storage_error',
                    'message' => 'Task storage is unavailable',
                    'details' => [],
                ],
            ]);

        $this->assertSame('keep me', $this->files->get($blockingFile));
    }

    /**
     * Проверяет сохранность цели и очистку temp-файла при ошибке rename.
     */
    public function test_rename_failure_returns_500_without_replacing_the_target(): void
    {
        $target = $this->temporaryDirectory.'/tasks.json';
        mkdir($target);
        config()->set('tasks.file', $target);

        $this->postJson('/api/v1/tasks', ['title' => 'Must not be persisted'])
            ->assertStatus(500)
            ->assertJsonPath('error.code', 'storage_error')
            ->assertJsonPath('error.details', []);

        $this->assertDirectoryExists($target);
        $this->assertSame([], glob($this->temporaryDirectory.'/.tasks-*') ?: []);
    }

    /**
     * Проверяет приоритет ошибки повреждения файла над отсутствующим ID.
     */
    public function test_corruption_takes_precedence_over_a_missing_task_404(): void
    {
        $target = $this->temporaryDirectory.'/tasks.json';
        $this->files->put($target, '{broken');
        config()->set('tasks.file', $target);

        $this->getJson('/api/v1/tasks/999')
            ->assertStatus(500)
            ->assertJsonPath('error.code', 'storage_corrupted');

        $this->assertSame('{broken', $this->files->get($target));
    }
}
