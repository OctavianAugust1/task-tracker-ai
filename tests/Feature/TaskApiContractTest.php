<?php

namespace Tests\Feature;

use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class TaskApiContractTest extends TestCase
{
    private string $temporaryDirectory;

    private string $tasksFile;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_list_is_sorted_filterable_and_uses_the_data_envelope(): void
    {
        $this->writeStorage([
            $this->task(id: 3, title: 'Third', status: 'done'),
            $this->task(id: 1, title: 'First', status: 'todo'),
            $this->task(id: 2, title: 'Second', status: 'todo'),
        ], nextId: 4);

        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertExactJson(['data' => [
                $this->task(id: 1, title: 'First', status: 'todo'),
                $this->task(id: 2, title: 'Second', status: 'todo'),
                $this->task(id: 3, title: 'Third', status: 'done'),
            ]]);

        $this->getJson('/api/tasks?status=todo')
            ->assertOk()
            ->assertExactJson(['data' => [
                $this->task(id: 1, title: 'First', status: 'todo'),
                $this->task(id: 2, title: 'Second', status: 'todo'),
            ]]);

        $this->getJson('/api/tasks?status=in_progress')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_list_rejects_unknown_query_parameters_and_invalid_status(): void
    {
        $unknownParameter = $this->getJson('/api/tasks?sort=title');
        $this->assertValidationFields($unknownParameter, ['sort']);

        $invalidStatus = $this->getJson('/api/tasks?status=archived');
        $this->assertValidationFields($invalidStatus, ['status']);
    }

    public function test_show_returns_one_task_and_missing_task_returns_404(): void
    {
        $task = $this->task(id: 1, title: 'Read specification');
        $this->writeStorage([$task], nextId: 2);

        $this->getJson('/api/tasks/1')
            ->assertOk()
            ->assertExactJson(['data' => $task]);

        $this->assertErrorResponse($this->getJson('/api/tasks/999'), 404);
    }

    public function test_create_applies_defaults_trims_title_and_returns_all_fields(): void
    {
        $response = $this->postJson('/api/tasks', [
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

    public function test_create_returns_every_validation_error_with_text(): void
    {
        $response = $this->postJson('/api/tasks', [
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

    public function test_create_rejects_wrong_types_and_an_overlong_title(): void
    {
        $wrongTypes = $this->postJson('/api/tasks', [
            'title' => ['not', 'a', 'string'],
            'description' => ['not', 'a', 'string'],
            'status' => 1,
            'due_date' => ['not', 'a', 'date'],
        ]);

        $this->assertValidationFields(
            $wrongTypes,
            ['title', 'description', 'status', 'due_date'],
        );

        $overlongTitle = $this->postJson('/api/tasks', [
            'title' => str_repeat('x', 201),
        ]);

        $this->assertValidationFields($overlongTitle, ['title']);
    }

    public function test_malformed_json_returns_400_in_the_common_error_format(): void
    {
        $response = $this->call(
            'POST',
            '/api/tasks',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"title":',
        );

        $this->assertErrorResponse($response, 400);
    }

    public function test_patch_is_partial_and_repeating_the_same_body_is_idempotent(): void
    {
        $task = $this->task(id: 1, title: 'Original');
        $this->writeStorage([$task], nextId: 2);

        $updated = $this->patchJson('/api/tasks/1', [
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

        $repeated = $this->patchJson('/api/tasks/1', [
            'title' => 'Updated',
            'description' => 'Details',
            'status' => 'in_progress',
            'due_date' => '2026-08-16',
        ]);

        $repeated
            ->assertOk()
            ->assertExactJson($updated->json());
    }

    public function test_patch_rejects_an_empty_body_and_server_managed_fields(): void
    {
        $this->writeStorage([$this->task()], nextId: 2);

        $empty = $this->patchJson('/api/tasks/1', []);
        $empty
            ->assertStatus(422)
            ->assertExactJson([
                'error' => [
                    'code' => 'validation_failed',
                    'message' => 'At least one field must be provided.',
                    'details' => [],
                ],
            ]);

        $serverFields = $this->patchJson('/api/tasks/1', [
            'id' => 7,
            'created_at' => '2026-01-01T00:00:00Z',
            'updated_at' => '2026-01-01T00:00:00Z',
        ]);
        $this->assertValidationFields($serverFields, ['id', 'created_at', 'updated_at']);
    }

    public function test_delete_returns_an_empty_204_and_missing_task_returns_404(): void
    {
        $this->writeStorage([$this->task()], nextId: 2);

        $this->deleteJson('/api/tasks/1')
            ->assertNoContent();

        $this->assertErrorResponse($this->deleteJson('/api/tasks/1'), 404);
    }

    public function test_missing_and_empty_storage_are_empty_but_corruption_is_preserved(): void
    {
        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertExactJson(['data' => []]);

        file_put_contents($this->tasksFile, '');

        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertExactJson(['data' => []]);

        file_put_contents($this->tasksFile, '{broken');

        $corrupted = $this->getJson('/api/tasks');
        $this->assertErrorResponse($corrupted, 500, 'storage_corrupted');
        $this->assertSame('{broken', file_get_contents($this->tasksFile));
    }

    public function test_api_errors_are_json_even_without_an_accept_header(): void
    {
        $response = $this->post('/api/tasks', []);

        $this->assertValidationFields($response, ['title']);
        $response->assertHeader('content-type', 'application/json');
    }

    public function test_framework_404_and_405_are_safe_contract_errors(): void
    {
        config()->set('app.debug', true);

        $this->getJson('/api/tasks/not-a-number')
            ->assertStatus(404)
            ->assertExactJson([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'Resource not found',
                    'details' => [],
                ],
            ]);

        $this->putJson('/api/tasks')
            ->assertStatus(405)
            ->assertExactJson([
                'error' => [
                    'code' => 'method_not_allowed',
                    'message' => 'Method not allowed',
                    'details' => [],
                ],
            ]);
    }

    private function assertValidationFields(TestResponse $response, array $expectedFields): void
    {
        $this->assertValidationError($response);

        $details = $response->json('error.details');

        $actualFields = array_column($details, 'field');

        foreach ($expectedFields as $expectedField) {
            $this->assertContains($expectedField, $actualFields);
        }
    }

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

    private function writeStorage(array $tasks, int $nextId): void
    {
        file_put_contents($this->tasksFile, json_encode([
            'next_id' => $nextId,
            'tasks' => $tasks,
        ], JSON_THROW_ON_ERROR));
    }

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
