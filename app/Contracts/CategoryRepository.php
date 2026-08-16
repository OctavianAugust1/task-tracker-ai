<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * @phpstan-type Category array{id: positive-int, name: non-empty-string, created_at: string, updated_at: string}
 * @phpstan-type NewCategory array{name: non-empty-string, created_at: string, updated_at: string}
 */
interface CategoryRepository
{
    /** @return list<Category> */
    public function allCategories(): array;

    /** @return Category|null */
    public function findCategory(int $id): ?array;

    /** @param NewCategory $category
     * @return Category
     */
    public function createCategory(array $category): array;

    /** @param callable(Category): Category $update
     * @return Category|null
     */
    public function updateCategory(int $id, callable $update): ?array;

    public function deleteCategory(int $id): bool;
}
