<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\TaskRepository;
use App\Http\Controllers\TaskController;
use App\Repositories\JsonTaskRepository;
use App\Services\TaskService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/** @phpstan-import-type Task from TaskRepository */
#[CoversClass(TaskController::class)]
#[CoversClass(TaskService::class)]
#[CoversClass(JsonTaskRepository::class)]
final class TaskApiContractTest extends TestCase
{
    private string $temporaryDirectory;

    private string $tasksFile;

    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->files = new Filesystem;

        $this->temporaryDirectory = sys_get_temp_dir().'/task-api-tests-'.bin2hex(random_bytes(8));
        mkdir($this->temporaryDirectory, 0700, true);
        $this->tasksFile = $this->temporaryDirectory.'/tasks.json';

        config()->set('tasks.file', $this->tasksFile);
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
     * Проверяет сортировку списка, фильтр статуса и единую оболочку data.
     */
    public function test_list_is_sorted_filterable_and_uses_the_data_envelope(): void
    {
        $this->writeStorage([
            $this->task(id: 3, title: 'Third', status: 'done'),
            $this->task(id: 1, title: 'First', status: 'todo'),
            $this->task(id: 2, title: 'Second', status: 'todo'),
        ], nextId: 4);

        $this->getJson('/api/v1/tasks')
            ->assertOk()
            ->assertExactJson(['data' => [
                $this->task(id: 1, title: 'First', status: 'todo'),
                $this->task(id: 2, title: 'Second', status: 'todo'),
                $this->task(id: 3, title: 'Third', status: 'done'),
            ]]);

        $this->getJson('/api/v1/tasks?status=todo')
            ->assertOk()
            ->assertExactJson(['data' => [
                $this->task(id: 1, title: 'First', status: 'todo'),
                $this->task(id: 2, title: 'Second', status: 'todo'),
            ]]);

        $this->getJson('/api/v1/tasks?status=in_progress')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    /**
     * Проверяет ошибки неизвестного query-параметра и недопустимого статуса.
     */
    public function test_list_rejects_unknown_query_parameters_and_invalid_status(): void
    {
        $unknownParameter = $this->getJson('/api/v1/tasks?sort=title');
        $this->assertValidationFields($unknownParameter, ['sort']);

        $invalidStatus = $this->getJson('/api/v1/tasks?status=archived');
        $this->assertValidationFields($invalidStatus, ['status']);
    }

    /**
     * Проверяет получение существующей задачи и ошибку отсутствующей задачи.
     */
    public function test_show_returns_one_task_and_missing_task_returns_404(): void
    {
        $task = $this->task(id: 1, title: 'Read specification');
        $this->writeStorage([$task], nextId: 2);

        $this->getJson('/api/v1/tasks/1')
            ->assertOk()
            ->assertExactJson(['data' => $task]);

        $this->assertErrorResponse($this->getJson('/api/v1/tasks/999'), 404);
    }

    /**
     * Проверяет создание задачи, нормализацию title и значения по умолчанию.
     */
    public function test_create_applies_defaults_trims_title_and_returns_all_fields(): void
    {
        $response = $this->postJson('/api/v1/tasks', [
            'title' => '  Write tests  ',
            'due_date' => '2020-01-01',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.id', 1)
            ->assertJsonPath('data.title', 'Write tests')
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.status', 'todo')
            ->assertJsonPath('data.due_date', '2020-01-01');

        $this->assertIso8601Utc($response->json('data.created_at'));
        $this->assertSame($response->json('data.created_at'), $response->json('data.updated_at'));
    }

    /**
     * Проверяет возврат всех ошибок невалидного тела одним ответом.
     */
    public function test_create_returns_every_validation_error_with_text(): void
    {
        $response = $this->postJson('/api/v1/tasks', [
            'title' => '   ',
            'description' => str_repeat('x', 2001),
            'status' => 'archived',
            'due_date' => '2026-02-30',
            'priority' => 'high',
            'id' => 10,
        ]);

        $this->assertValidationFields(
            $response,
            ['title', 'description', 'status', 'due_date', 'priority', 'id'],
        );
    }

    /**
     * Проверяет отклонение неверных типов и слишком длинного заголовка.
     */
    public function test_create_rejects_wrong_types_and_an_overlong_title(): void
    {
        $wrongTypes = $this->postJson('/api/v1/tasks', [
            'title' => ['not', 'a', 'string'],
            'description' => ['not', 'a', 'string'],
            'status' => 1,
            'due_date' => ['not', 'a', 'date'],
        ]);

        $this->assertValidationFields(
            $wrongTypes,
            ['title', 'description', 'status', 'due_date'],
        );

        $overlongTitle = $this->postJson('/api/v1/tasks', [
            'title' => str_repeat('x', 201),
        ]);

        $this->assertValidationFields($overlongTitle, ['title']);
    }

    /**
     * Проверяет безопасную контрактную ошибку для синтаксически неверного JSON.
     */
    public function test_malformed_json_returns_400_in_the_common_error_format(): void
    {
        $response = $this->call(
            'POST',
            '/api/v1/tasks',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"title":',
        );

        $this->assertErrorResponse($response, 400);
    }

    /**
     * Проверяет частичное и идемпотентное изменение существующей задачи.
     */
    public function test_patch_is_partial_and_repeating_the_same_body_is_idempotent(): void
    {
        $task = $this->task(id: 1, title: 'Original');
        $this->writeStorage([$task], nextId: 2);

        $updated = $this->patchJson('/api/v1/tasks/1', [
            'title' => 'Updated',
            'description' => 'Details',
            'status' => 'in_progress',
            'due_date' => '2026-08-16',
        ]);

        $updated
            ->assertOk()
            ->assertJsonPath('data.id', 1)
            ->assertJsonPath('data.title', 'Updated')
            ->assertJsonPath('data.description', 'Details')
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.due_date', '2026-08-16')
            ->assertJsonPath('data.created_at', $task['created_at']);

        $this->assertIso8601Utc($updated->json('data.updated_at'));

        $repeated = $this->patchJson('/api/v1/tasks/1', [
            'title' => 'Updated',
            'description' => 'Details',
            'status' => 'in_progress',
            'due_date' => '2026-08-16',
        ]);

        $repeated
            ->assertOk()
            ->assertExactJson($updated->json());
    }

    /**
     * Проверяет отклонение пустого PATCH и управляемых сервером полей.
     */
    public function test_patch_rejects_an_empty_body_and_server_managed_fields(): void
    {
        $this->writeStorage([$this->task()], nextId: 2);

        $empty = $this->patchJson('/api/v1/tasks/1', []);
        $empty
            ->assertStatus(422)
            ->assertExactJson([
                'error' => [
                    'code' => 'validation_failed',
                    'message' => 'At least one field must be provided.',
                    'details' => [],
                ],
            ]);

        $serverFields = $this->patchJson('/api/v1/tasks/1', [
            'id' => 7,
            'created_at' => '2026-01-01T00:00:00Z',
            'updated_at' => '2026-01-01T00:00:00Z',
        ]);
        $this->assertValidationFields($serverFields, ['id', 'created_at', 'updated_at']);
    }

    /**
     * Проверяет пустой ответ удаления и ошибку повторного удаления.
     */
    public function test_delete_returns_an_empty_204_and_missing_task_returns_404(): void
    {
        $this->writeStorage([$this->task()], nextId: 2);

        $this->deleteJson('/api/v1/tasks/1')
            ->assertNoContent();

        $this->assertErrorResponse($this->deleteJson('/api/v1/tasks/1'), 404);
    }

    /**
     * Проверяет отсутствующий, пустой и повреждённый файл без потери данных.
     */
    public function test_missing_and_empty_storage_are_empty_but_corruption_is_preserved(): void
    {
        $this->getJson('/api/v1/tasks')
            ->assertOk()
            ->assertExactJson(['data' => []]);

        $this->files->put($this->tasksFile, '');

        $this->getJson('/api/v1/tasks')
            ->assertOk()
            ->assertExactJson(['data' => []]);

        $this->files->put($this->tasksFile, '{broken');

        $corrupted = $this->getJson('/api/v1/tasks');
        $this->assertErrorResponse($corrupted, 500, 'storage_corrupted');
        $this->assertSame('{broken', $this->files->get($this->tasksFile));
    }

    /**
     * Проверяет JSON-валидацию без клиентского заголовка Accept.
     */
    public function test_api_errors_are_json_even_without_an_accept_header(): void
    {
        $response = $this->post('/api/v1/tasks', []);

        $this->assertValidationFields($response, ['title']);
        $response->assertHeader('content-type', 'application/json');
    }

    /**
     * Проверяет безопасные 404/405 и отсутствие старого неверсионированного API.
     */
    public function test_framework_404_and_405_are_safe_contract_errors(): void
    {
        config()->set('app.debug', true);

        $this->getJson('/api/v1/tasks/not-a-number')
            ->assertStatus(404)
            ->assertExactJson([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'Resource not found',
                    'details' => [],
                ],
            ]);

        $this->putJson('/api/v1/tasks')
            ->assertStatus(405)
            ->assertExactJson([
                'error' => [
                    'code' => 'method_not_allowed',
                    'message' => 'Method not allowed',
                    'details' => [],
                ],
            ]);

        $this->getJson('/api/tasks')
            ->assertStatus(404)
            ->assertExactJson([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'Resource not found',
                    'details' => [],
                ],
            ]);
    }

    /**
     * @param  TestResponse<Response>  $response
     * @param  list<string>  $expectedFields
     */
    private function assertValidationFields(TestResponse $response, array $expectedFields): void
    {
        $this->assertValidationError($response);

        $details = $response->json('error.details');

        $actualFields = array_column($details, 'field');

        foreach ($expectedFields as $expectedField) {
            $this->assertContains($expectedField, $actualFields);
        }
    }

    /** @param TestResponse<Response> $response */
    private function assertValidationError(TestResponse $response): void
    {
        $this->assertErrorResponse($response, 422, 'validation_failed');

        $details = $response->json('error.details');
        $this->assertIsArray($details);
        $this->assertNotEmpty($details);

        foreach ($details as $detail) {
            $this->assertIsString($detail['message'] ?? null);
            $this->assertNotSame('', trim($detail['message']));
        }
    }

    /** @param TestResponse<Response> $response */
    private function assertErrorResponse(
        TestResponse $response,
        int $status,
        ?string $expectedCode = null,
    ): void {
        $response
            ->assertStatus($status)
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details',
                ],
            ]);

        $this->assertIsString($response->json('error.code'));
        $this->assertNotSame('', trim($response->json('error.code')));
        $this->assertIsString($response->json('error.message'));
        $this->assertNotSame('', trim($response->json('error.message')));
        $this->assertIsArray($response->json('error.details'));

        if ($expectedCode !== null) {
            $response->assertJsonPath('error.code', $expectedCode);
        }
    }

    private function assertIso8601Utc(mixed $value): void
    {
        $this->assertIsString($value);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $value,
        );
    }

    /** @param list<Task> $tasks */
    private function writeStorage(array $tasks, int $nextId): void
    {
        $this->files->put($this->tasksFile, json_encode([
            'next_id' => $nextId,
            'tasks' => $tasks,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  positive-int  $id
     * @param  non-empty-string  $title
     * @param  'todo'|'in_progress'|'done'  $status
     * @return Task
     */
    private function task(
        int $id = 1,
        string $title = 'Task',
        ?string $description = null,
        string $status = 'todo',
        ?string $dueDate = null,
    ): array {
        return [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'due_date' => $dueDate,
            'created_at' => '2026-08-15T10:00:00Z',
            'updated_at' => '2026-08-15T10:00:00Z',
        ];
    }
}
