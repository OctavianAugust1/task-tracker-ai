<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
final class ScrambleDocumentationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->app->detectEnvironment(static fn (): string => 'local');
    }

    /**
     * Проверяет доступность локального UI документации без сетевых запросов.
     */
    public function test_documentation_ui_is_available(): void
    {
        $this->get('/docs/api')
            ->assertOk()
            ->assertSee('Task API');
    }

    /**
     * Проверяет безопасный запрет документации вне local без настроенного auth guard.
     */
    public function test_documentation_is_forbidden_outside_local_environment(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');

        $this->get('/docs/api')->assertForbidden();
        $this->get('/docs/api.json')->assertForbidden();
    }

    /**
     * Проверяет полный OpenAPI-контракт десяти операций, фильтров и схем.
     */
    public function test_openapi_document_describes_every_api_operation(): void
    {
        $response = $this->getJson('/docs/api.json')->assertOk();

        $response
            ->assertJsonPath('openapi', '3.1.0')
            ->assertJsonPath('info.title', 'Task API')
            ->assertJsonPath('info.version', '1.0.0')
            ->assertJsonPath('servers.0.url', 'http://localhost/api/v1');

        $document = $response->json();
        $this->assertIsArray($document);
        $this->assertSame(
            ['/tasks', '/tasks/{id}', '/categories', '/categories/{id}'],
            array_keys($document['paths']),
        );

        $operations = [];
        foreach ($document['paths'] as $path => $pathItem) {
            foreach (['get', 'post', 'patch', 'delete'] as $method) {
                if (isset($pathItem[$method])) {
                    $operations["{$method} {$path}"] = $pathItem[$method];
                }
            }
        }

        $this->assertCount(10, $operations);
        $this->assertCount(10, array_unique(array_column($operations, 'operationId')));
        $this->assertSame(
            ['listTasks', 'createTask', 'getTask', 'updateTask', 'deleteTask', 'listCategories', 'createCategory', 'getCategory', 'updateCategory', 'deleteCategory'],
            array_column($operations, 'operationId'),
        );

        $listParameters = $operations['get /tasks']['parameters'];
        $parametersByName = array_column($listParameters, null, 'name');
        $this->assertSame(['statuses[]', 'due_date', 'category_ids[]'], array_keys($parametersByName));
        $this->assertFalse($parametersByName['statuses[]']['required'] ?? false);
        $this->assertSame('array', $parametersByName['statuses[]']['schema']['type']);
        $this->assertSame('integer', $parametersByName['category_ids[]']['schema']['items']['type']);

        $storeTask = $document['components']['schemas']['StoreTaskRequest'];
        $this->assertSame(['integer', 'null'], $storeTask['properties']['category_id']['type']);
        $this->assertSame(['title'], $storeTask['required']);
        $this->assertSame(1, $storeTask['properties']['title']['minLength']);
        $this->assertSame(200, $storeTask['properties']['title']['maxLength']);
        $this->assertSame(2000, $storeTask['properties']['description']['maxLength']);
        $this->assertSame(1, $storeTask['properties']['category_id']['minimum']);
        $this->assertSame(1, $parametersByName['statuses[]']['schema']['minItems']);
        $this->assertSame(3, $parametersByName['statuses[]']['schema']['maxItems']);
        $this->assertTrue($parametersByName['statuses[]']['schema']['uniqueItems']);
        $this->assertArrayNotHasKey('enum', $parametersByName['statuses[]']['schema']);
        $this->assertSame(1, $parametersByName['category_ids[]']['schema']['minItems']);
        $this->assertTrue($parametersByName['category_ids[]']['schema']['uniqueItems']);
        $this->assertSame(1, $parametersByName['category_ids[]']['schema']['items']['minimum']);
        $this->assertSame(1, $operations['get /tasks/{id}']['parameters'][0]['schema']['minimum']);
        $this->assertSame(
            [201, 422, 400, 500],
            array_keys($operations['post /tasks']['responses']),
        );
        $this->assertSame(
            [204, 404, 409, 500],
            array_keys($operations['delete /categories/{id}']['responses']),
        );
        $this->assertArrayNotHasKey('content', $operations['delete /tasks/{id}']['responses']['204']);
        $this->assertArrayNotHasKey('content', $operations['delete /categories/{id}']['responses']['204']);
    }
}
