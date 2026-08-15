<?php

namespace Tests\Feature;

use Tests\TestCase;

class TaskApiTest extends TestCase
{
    private string $storagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storagePath = sys_get_temp_dir().'/naive-tasks-'.bin2hex(random_bytes(8)).'.json';
        config(['tasks.path' => $this->storagePath]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->storagePath)) {
            unlink($this->storagePath);
        }

        parent::tearDown();
    }

    public function test_crud_flow_uses_json_storage(): void
    {
        $created = $this->postJson('/api/tasks', ['title' => 'Write tests'])
            ->assertCreated()
            ->assertJsonPath('title', 'Write tests')
            ->assertJsonPath('completed', false)
            ->json();

        $this->getJson('/api/tasks')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/tasks/'.$created['id'])->assertOk();

        $this->patchJson('/api/tasks/'.$created['id'], ['completed' => true])
            ->assertOk()
            ->assertJsonPath('completed', true);

        $this->deleteJson('/api/tasks/'.$created['id'])->assertNoContent();
        $this->getJson('/api/tasks')->assertOk()->assertExactJson([]);
    }

    public function test_title_is_required(): void
    {
        $this->postJson('/api/tasks', [])->assertUnprocessable()->assertJsonValidationErrors('title');
    }

    public function test_missing_task_returns_not_found(): void
    {
        $this->getJson('/api/tasks/999')->assertNotFound();
    }

    public function test_missing_and_empty_storage_are_empty_lists(): void
    {
        $this->getJson('/api/tasks')->assertOk()->assertExactJson([]);

        file_put_contents($this->storagePath, '');

        $this->getJson('/api/tasks')->assertOk()->assertExactJson([]);
    }

    public function test_corrupted_storage_returns_server_error_without_overwriting_file(): void
    {
        file_put_contents($this->storagePath, '{broken');

        $this->getJson('/api/tasks')
            ->assertStatus(500)
            ->assertExactJson(['message' => 'Task storage is corrupted.']);

        $this->assertSame('{broken', file_get_contents($this->storagePath));
    }
}
