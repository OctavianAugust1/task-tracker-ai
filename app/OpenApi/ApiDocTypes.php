<?php

declare(strict_types=1);

namespace App\OpenApi;

final class ApiDocTypes
{
    public const TASK = "array{id: int, title: string, description: string|null, status: 'todo'|'in_progress'|'done', due_date: string|null, category_id: int|null, created_at: string, updated_at: string}";

    public const TASK_RESPONSE = 'array{data: '.self::TASK.'}';

    public const TASK_LIST_RESPONSE = 'array{data: list<'.self::TASK.'>}';

    public const CATEGORY = 'array{id: int, name: string, created_at: string, updated_at: string}';

    public const CATEGORY_RESPONSE = 'array{data: '.self::CATEGORY.'}';

    public const CATEGORY_LIST_RESPONSE = 'array{data: list<'.self::CATEGORY.'>}';

    public const ERROR_RESPONSE = 'array{error: array{code: string, message: string, details: list<array{field: string, message: string}>}}';
}
