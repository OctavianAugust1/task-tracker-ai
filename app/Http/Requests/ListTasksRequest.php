<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\TaskFilters;
use UnexpectedValueException;

final class ListTasksRequest extends ApiRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            /**
             * Статусы задач; значения объединяются через OR.
             *
             * @example ["todo", "done"]
             */
            'statuses' => ['sometimes', 'array', 'list', 'min:1', 'max:3'],
            /** Значение статуса; несколько значений объединяются через OR. */
            'statuses.*' => ['string', 'distinct:strict', 'in:todo,in_progress,done'],
            /**
             * Точная дата окончания; объединяется с другими фильтрами через AND.
             *
             * @example "2026-08-20"
             */
            'due_date' => ['sometimes', 'string', 'date_format:Y-m-d'],
            /**
             * ID категорий; значения объединяются через OR.
             *
             * @example [1, 2]
             */
            'category_ids' => ['sometimes', 'array', 'list', 'min:1'],
            /** Положительный ID категории; несколько значений объединяются через OR. */
            'category_ids.*' => ['integer', 'min:1', 'distinct:strict'],
        ];
    }

    /** @return array<string, mixed> */
    public function validationData(): array
    {
        return $this->query->all();
    }

    protected function prepareForValidation(): void
    {
        // Query values are validated as received; no body normalization applies.
    }

    /** @return list<string> */
    protected function inputFieldNames(): array
    {
        return array_keys($this->query->all());
    }

    public function validatedFilters(): TaskFilters
    {
        $rawStatuses = $this->validated('statuses');
        $statuses = [];

        if ($rawStatuses !== null) {
            if (! is_array($rawStatuses) || ! array_is_list($rawStatuses)) {
                throw new UnexpectedValueException('Validated statuses have an invalid type.');
            }

            foreach ($rawStatuses as $status) {
                if (! is_string($status) || ! in_array($status, ['todo', 'in_progress', 'done'], true)) {
                    throw new UnexpectedValueException('Validated status has an invalid type.');
                }

                $statuses[] = $status;
            }
        }

        $dueDate = $this->validated('due_date');

        if ($dueDate !== null && ! is_string($dueDate)) {
            throw new UnexpectedValueException('Validated due date has an invalid type.');
        }

        $rawCategoryIds = $this->validated('category_ids');
        $categoryIds = [];
        if ($rawCategoryIds !== null) {
            if (! is_array($rawCategoryIds) || ! array_is_list($rawCategoryIds)) {
                throw new UnexpectedValueException('Validated category IDs have an invalid type.');
            }
            foreach ($rawCategoryIds as $categoryId) {
                if (is_string($categoryId) && ctype_digit($categoryId)) {
                    $categoryId = (int) $categoryId;
                }
                if (! is_int($categoryId) || $categoryId < 1) {
                    throw new UnexpectedValueException('Validated category ID has an invalid type.');
                }
                $categoryIds[] = $categoryId;
            }
        }

        return new TaskFilters($statuses, $dueDate, $categoryIds);
    }
}
