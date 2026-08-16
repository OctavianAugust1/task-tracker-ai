<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\StrictPositiveInteger;
use Dedoc\Scramble\Attributes\BodyParameter;
use UnexpectedValueException;

#[BodyParameter('title', 'Краткий заголовок задачи.', required: true, type: 'string', infer: true, example: 'Подготовить отчёт')]
#[BodyParameter('description', 'Подробное описание или null.', required: false, type: 'string|null', infer: true, example: 'Собрать результаты за неделю')]
#[BodyParameter('status', 'Текущий статус.', required: false, type: "'todo'|'in_progress'|'done'", infer: true, default: 'todo', example: 'todo')]
#[BodyParameter('due_date', 'Дата окончания или null.', required: false, type: 'string|null', format: 'date', infer: true, example: '2026-08-20')]
#[BodyParameter('category_id', 'ID существующей категории или null.', required: false, type: 'int|null', infer: false, example: 1)]
final class StoreTaskRequest extends ApiRequest
{
    /** @return array<string, list<string|StrictPositiveInteger>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:1', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'string', 'in:todo,in_progress,done'],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'category_id' => ['sometimes', 'nullable', new StrictPositiveInteger],
        ];
    }

    /** @return array{title: non-empty-string, description?: string|null, status?: 'todo'|'in_progress'|'done', due_date?: string|null, category_id?: positive-int|null} */
    public function validatedTaskAttributes(): array
    {
        $validated = $this->validated();
        $title = $validated['title'] ?? null;

        if (! is_string($title) || $title === '') {
            throw new UnexpectedValueException('Validated title has an invalid type.');
        }

        $attributes = ['title' => $title];

        foreach (['description', 'due_date'] as $field) {
            if (array_key_exists($field, $validated)) {
                $value = $validated[$field];

                if ($value !== null && ! is_string($value)) {
                    throw new UnexpectedValueException("Validated {$field} has an invalid type.");
                }

                $attributes[$field] = $value;
            }
        }

        if (array_key_exists('status', $validated)) {
            $status = $validated['status'];

            if (! is_string($status) || ! in_array($status, ['todo', 'in_progress', 'done'], true)) {
                throw new UnexpectedValueException('Validated status has an invalid type.');
            }

            $attributes['status'] = $status;
        }

        if (array_key_exists('category_id', $validated)) {
            $categoryId = $validated['category_id'];
            if ($categoryId !== null && (! is_int($categoryId) || $categoryId < 1)) {
                throw new UnexpectedValueException('Validated category ID has an invalid type.');
            }
            $attributes['category_id'] = $categoryId;
        }

        return $attributes;
    }
}
