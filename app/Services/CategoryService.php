<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CategoryRepository;
use DateTimeImmutable;
use DateTimeZone;

/** @phpstan-import-type Category from CategoryRepository */
final class CategoryService
{
    public function __construct(private readonly CategoryRepository $repository) {}

    /** @return list<Category> */
    public function list(): array
    {
        $categories = $this->repository->allCategories();
        usort($categories, fn (array $left, array $right): int => $left['id'] <=> $right['id']);

        return $categories;
    }

    /** @return Category|null */
    public function find(int $id): ?array
    {
        return $this->repository->findCategory($id);
    }

    /**
     * @param  non-empty-string  $name
     * @return Category
     */
    public function create(string $name): array
    {
        $timestamp = $this->timestamp();

        return $this->repository->createCategory([
            'name' => $name,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    /**
     * @param  non-empty-string  $name
     * @return Category|null
     */
    public function update(int $id, string $name): ?array
    {
        return $this->repository->updateCategory($id, function (array $category) use ($name): array {
            if ($category['name'] === $name) {
                return $category;
            }

            $category['name'] = $name;
            $category['updated_at'] = $this->timestamp();

            return $category;
        });
    }

    public function delete(int $id): bool
    {
        return $this->repository->deleteCategory($id);
    }

    private function timestamp(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    }
}
