<?php

declare(strict_types=1);

namespace App\Http\Requests;

use UnexpectedValueException;

final class UpdateTaskRequest extends ApiRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'min:1', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'string', 'in:todo,in_progress,done'],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }

    protected function requiresAtLeastOneField(): bool
    {
        return true;
    }

    /** @return array{title?: non-empty-string, description?: string|null, status?: 'todo'|'in_progress'|'done', due_date?: string|null} */
    public function validatedTaskAttributes(): array
    {
        $validated = $this->validated();
        $attributes = [];

        if (array_key_exists('title', $validated)) {
            $title = $validated['title'];

            if (! is_string($title) || $title === '') {
                throw new UnexpectedValueException('Validated title has an invalid type.');
            }

            $attributes['title'] = $title;
        }

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

        return $attributes;
    }
}
