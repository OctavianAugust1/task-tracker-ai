<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Dedoc\Scramble\Attributes\BodyParameter;

#[BodyParameter('name', 'Новое уникальное имя категории.', required: true, type: 'string', infer: true, example: 'Личное')]
final class UpdateCategoryRequest extends StoreCategoryRequest {}
