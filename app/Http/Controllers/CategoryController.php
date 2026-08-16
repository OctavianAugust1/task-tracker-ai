<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Responses\ApiErrorResponse;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $service) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->service->list()]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->service->create($request->validatedName())], 201);
    }

    public function show(int $id): JsonResponse
    {
        $category = $this->service->find($id);

        return $category === null ? $this->notFound() : response()->json(['data' => $category]);
    }

    public function update(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        $category = $this->service->update($id, $request->validatedName());

        return $category === null ? $this->notFound() : response()->json(['data' => $category]);
    }

    public function destroy(int $id): Response
    {
        return $this->service->delete($id) ? response()->noContent() : $this->notFound();
    }

    private function notFound(): JsonResponse
    {
        return ApiErrorResponse::make('category_not_found', 'Category not found', [], 404);
    }
}
