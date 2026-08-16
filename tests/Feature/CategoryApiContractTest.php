<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\CategoryController;
use App\Repositories\JsonTaskRepository;
use App\Services\CategoryService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CategoryController::class)]
#[CoversClass(CategoryService::class)]
#[CoversClass(JsonTaskRepository::class)]
final class CategoryApiContractTest extends TestCase
{
    private string $directory;

    private string $tasksFile;

    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->files = new Filesystem;
        $this->directory = sys_get_temp_dir().'/category-api-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
        $this->tasksFile = $this->directory.'/tasks.json';
        config()->set('tasks.file', $this->tasksFile);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    /**
     * Проверяет полный CRUD категорий, trim имени и монотонность идентификаторов.
     */
    public function test_category_crud_uses_the_common_response_contract(): void
    {
        $created = $this->postJson('/api/v1/categories', ['name' => '  Work  '])
            ->assertCreated()
            ->assertJsonPath('data.id', 1)
            ->assertJsonPath('data.name', 'Work');

        $category = $created->json('data');
        $this->getJson('/api/v1/categories')->assertOk()->assertExactJson(['data' => [$category]]);
        $this->getJson('/api/v1/categories/1')->assertOk()->assertExactJson(['data' => $category]);

        $updated = $this->patchJson('/api/v1/categories/1', ['name' => 'Personal'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Personal');
        $this->assertSame($category['created_at'], $updated->json('data.created_at'));

        $this->deleteJson('/api/v1/categories/1')->assertNoContent();
        $this->getJson('/api/v1/categories/1')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'category_not_found');

        $this->postJson('/api/v1/categories', ['name' => 'Next'])
            ->assertCreated()
            ->assertJsonPath('data.id', 2);
    }

    /**
     * Проверяет валидацию имени, регистронезависимую уникальность и неизвестную категорию задачи.
     */
    public function test_category_and_task_references_are_validated(): void
    {
        $this->postJson('/api/v1/categories', ['name' => 'Work'])->assertCreated();
        $this->postJson('/api/v1/categories', ['name' => 'work'])
            ->assertStatus(422)
            ->assertJsonPath('error.details.0.field', 'name');

        $this->postJson('/api/v1/categories', ['name' => '   '])
            ->assertStatus(422)
            ->assertJsonPath('error.details.0.field', 'name');

        $this->postJson('/api/v1/tasks', ['title' => 'Invalid reference', 'category_id' => 999])
            ->assertStatus(422)
            ->assertJsonPath('error.details.0.field', 'category_id');

        $this->postJson('/api/v1/tasks', ['title' => 'Wrong type', 'category_id' => '1'])
            ->assertStatus(422)
            ->assertJsonPath('error.details.0.field', 'category_id');

        $taskId = $this->postJson('/api/v1/tasks', ['title' => 'Valid', 'category_id' => 1])
            ->assertCreated()->json('data.id');
        $this->patchJson("/api/v1/tasks/{$taskId}", ['category_id' => '1'])
            ->assertStatus(422)
            ->assertJsonPath('error.details.0.field', 'category_id');
    }

    /**
     * Проверяет назначение категории, OR категорий, AND остальных фильтров и конфликт удаления.
     */
    public function test_tasks_can_be_assigned_filtered_and_protect_used_categories(): void
    {
        $workId = $this->postJson('/api/v1/categories', ['name' => 'Work'])->json('data.id');
        $homeId = $this->postJson('/api/v1/categories', ['name' => 'Home'])->json('data.id');

        $this->postJson('/api/v1/tasks', [
            'title' => 'Work todo', 'category_id' => $workId, 'status' => 'todo', 'due_date' => '2026-08-20',
        ])->assertCreated();
        $this->postJson('/api/v1/tasks', [
            'title' => 'Home done', 'category_id' => $homeId, 'status' => 'done', 'due_date' => '2026-08-20',
        ])->assertCreated();
        $this->postJson('/api/v1/tasks', [
            'title' => 'Uncategorized', 'status' => 'todo', 'due_date' => '2026-08-20',
        ])->assertCreated()->assertJsonPath('data.category_id', null);

        $this->getJson("/api/v1/tasks?category_ids[]={$workId}&category_ids[]={$homeId}")
            ->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/tasks?category_ids[]={$workId}&category_ids[]={$homeId}&statuses[]=done&due_date=2026-08-20")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Home done');

        $this->deleteJson("/api/v1/categories/{$workId}")
            ->assertStatus(409)
            ->assertExactJson(['error' => [
                'code' => 'category_in_use',
                'message' => 'Category is assigned to one or more tasks',
                'details' => [],
            ]]);

        $this->patchJson('/api/v1/tasks/1', ['category_id' => null])
            ->assertOk()->assertJsonPath('data.category_id', null);
        $this->deleteJson("/api/v1/categories/{$workId}")->assertNoContent();
    }

    /**
     * Проверяет чтение legacy-файла без записи и его атомарное обновление при mutation.
     */
    public function test_legacy_storage_is_migrated_only_on_successful_mutation(): void
    {
        $legacy = json_encode(['next_id' => 2, 'tasks' => [[
            'id' => 1,
            'title' => 'Legacy',
            'description' => null,
            'status' => 'todo',
            'due_date' => null,
            'created_at' => '2026-08-15T10:00:00Z',
            'updated_at' => '2026-08-15T10:00:00Z',
        ]]], JSON_THROW_ON_ERROR);
        $this->files->put($this->tasksFile, $legacy);

        $this->getJson('/api/v1/tasks')->assertOk()->assertJsonPath('data.0.category_id', null);
        $this->assertSame($legacy, $this->files->get($this->tasksFile));

        $this->postJson('/api/v1/categories', ['name' => 'Migrates state'])->assertCreated();
        $state = json_decode($this->files->get($this->tasksFile), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(2, $state['next_task_id']);
        $this->assertSame(2, $state['next_category_id']);
        $this->assertNull($state['tasks'][0]['category_id']);
        $this->assertSame('Migrates state', $state['categories'][0]['name']);
        $this->assertArrayNotHasKey('next_id', $state);
    }
}
