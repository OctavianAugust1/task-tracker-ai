<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Responses\ApiErrorResponse;
use App\OpenApi\ApiDocTypes;
use App\Services\CategoryService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

#[Group('Categories', 'Управление категориями, по которым распределяются задачи.', weight: 2)]
final class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $service) {}

    #[Endpoint('listCategories', 'Получить список категорий', 'Возвращает все категории по возрастанию ID.')]
    #[ApiResponse(200, 'Список категорий.', type: ApiDocTypes::CATEGORY_LIST_RESPONSE)]
    #[ApiResponse(500, 'Хранилище недоступно или повреждено.', type: ApiDocTypes::ERROR_RESPONSE)]
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->service->list()]);
    }

    #[Endpoint('createCategory', 'Создать категорию', 'Создаёт категорию с уникальным без учёта регистра именем.')]
    #[ApiResponse(201, 'Категория создана.', type: ApiDocTypes::CATEGORY_RESPONSE)]
    #[ApiResponse(400, 'Тело содержит синтаксически неверный JSON.', type: ApiDocTypes::ERROR_RESPONSE)]
    #[ApiResponse(422, 'Имя не прошло валидацию или уже занято.', type: ApiDocTypes::ERROR_RESPONSE)]
    #[ApiResponse(500, 'Хранилище недоступно или повреждено.', type: ApiDocTypes::ERROR_RESPONSE)]
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->service->create($request->validatedName())], 201);
    }

    #[Endpoint('getCategory', 'Получить категорию', 'Возвращает категорию по положительному идентификатору.')]
    #[PathParameter('id', 'ID категории.', type: 'int', infer: false, example: 1)]
    #[ApiResponse(200, 'Категория найдена.', type: ApiDocTypes::CATEGORY_RESPONSE)]
    #[ApiResponse(404, 'Категория не найдена.', type: ApiDocTypes::ERROR_RESPONSE)]
    #[ApiResponse(500, 'Хранилище недоступно или повреждено.', type: ApiDocTypes::ERROR_RESPONSE)]
    public function show(int $id): JsonResponse
    {
        $category = $this->service->find($id);

        return $category === null ? $this->notFound() : response()->json(['data' => $category]);
    }

    #[Endpoint('updateCategory', 'Переименовать категорию', 'Изменяет имя существующей категории.')]
    #[PathParameter('id', 'ID категории.', type: 'int', infer: false, example: 1)]
    #[ApiResponse(200, 'Категория изменена или уже имела это имя.', type: ApiDocTypes::CATEGORY_RESPONSE)]
    #[ApiResponse(400, 'Тело содержит синтаксически неверный JSON.', type: ApiDocTypes::ERROR_RESPONSE)]
    #[ApiResponse(404, 'Категория не найдена.', type: ApiDocTypes::ERROR_RESPONSE)]
    #[ApiResponse(422, 'Имя не прошло валидацию или уже занято.', type: ApiDocTypes::ERROR_RESPONSE)]
    #[ApiResponse(500, 'Хранилище недоступно или повреждено.', type: ApiDocTypes::ERROR_RESPONSE)]
    public function update(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        $category = $this->service->update($id, $request->validatedName());

        return $category === null ? $this->notFound() : response()->json(['data' => $category]);
    }

    #[Endpoint('deleteCategory', 'Удалить категорию', 'Удаляет только категорию, которая не назначена ни одной задаче.')]
    #[PathParameter('id', 'ID категории.', type: 'int', infer: false, example: 1)]
    #[ApiResponse(404, 'Категория не найдена.', type: ApiDocTypes::ERROR_RESPONSE)]
    #[ApiResponse(409, 'Категория назначена одной или нескольким задачам.', type: ApiDocTypes::ERROR_RESPONSE)]
    #[ApiResponse(500, 'Хранилище недоступно или повреждено.', type: ApiDocTypes::ERROR_RESPONSE)]
    public function destroy(int $id): Response
    {
        return $this->service->delete($id) ? response()->noContent() : $this->notFound();
    }

    private function notFound(): JsonResponse
    {
        return ApiErrorResponse::make('category_not_found', 'Category not found', [], 404);
    }
}
