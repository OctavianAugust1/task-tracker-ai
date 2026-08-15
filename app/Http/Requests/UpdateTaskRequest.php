<?php

namespace App\Http\Requests;

final class UpdateTaskRequest extends ApiRequest
{
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
}
