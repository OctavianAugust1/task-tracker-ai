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
            'statuses' => ['sometimes', 'required', 'array', 'list', 'min:1', 'max:3'],
            'statuses.*' => ['required', 'string', 'distinct:strict', 'in:todo,in_progress,done'],
            'due_date' => ['sometimes', 'required', 'string', 'date_format:Y-m-d'],
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

        return new TaskFilters($statuses, $dueDate);
    }
}
