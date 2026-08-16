<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\CategoryRepository;
use App\Services\CategoryService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CategoryService::class)]
final class CategoryServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private CategoryRepository&MockInterface $repository;

    private CategoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var CategoryRepository&MockInterface $repository */
        $repository = Mockery::mock(CategoryRepository::class);
        $this->repository = $repository;
        $this->service = new CategoryService($repository);
    }

    /**
     * Проверяет сортировку категорий по ID независимо от порядка репозитория.
     */
    public function test_list_sorts_categories_by_id(): void
    {
        $this->repository->shouldReceive('allCategories')->once()->andReturn([
            $this->category(2, 'Home'),
            $this->category(1, 'Work'),
        ]);

        $this->assertSame([1, 2], array_column($this->service->list(), 'id'));
    }

    /**
     * Проверяет добавление временных меток при создании категории.
     */
    public function test_create_adds_timestamps(): void
    {
        $this->repository->shouldReceive('createCategory')->once()
            ->with(Mockery::on(function (array $category): bool {
                $this->assertSame('Work', $category['name']);
                $this->assertSame($category['created_at'], $category['updated_at']);

                return true;
            }))
            ->andReturnUsing(fn (array $category): array => ['id' => 1, ...$category]);

        $this->assertSame('Work', $this->service->create('Work')['name']);
    }

    /**
     * Проверяет идемпотентное переименование без изменения timestamp.
     */
    public function test_unchanged_update_returns_the_same_category(): void
    {
        $category = $this->category(1, 'Work');
        $this->repository->shouldReceive('updateCategory')->once()
            ->andReturnUsing(fn (int $id, callable $update): array => $update($category));

        $this->assertSame($category, $this->service->update(1, 'Work'));
    }

    /**
     * Проверяет делегирование удаления категории репозиторию.
     */
    public function test_delete_returns_repository_result(): void
    {
        $this->repository->shouldReceive('deleteCategory')->once()->with(1)->andReturnTrue();
        $this->assertTrue($this->service->delete(1));
    }

    /** @return array{id: positive-int, name: non-empty-string, created_at: string, updated_at: string} */
    private function category(int $id, string $name): array
    {
        if ($id < 1 || $name === '') {
            throw new \InvalidArgumentException('Invalid category fixture.');
        }

        return [
            'id' => $id,
            'name' => $name,
            'created_at' => '2026-08-15T10:00:00Z',
            'updated_at' => '2026-08-15T10:00:00Z',
        ];
    }
}
