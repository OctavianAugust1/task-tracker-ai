<?php

namespace App\Http\Requests;

final class ListTasksRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', 'in:todo,in_progress,done'],
        ];
    }

    public function validationData(): array
    {
        return $this->query->all();
    }

    protected function prepareForValidation(): void
    {
        // Query values are validated as received; no body normalization applies.
    }

    protected function inputFieldNames(): array
    {
        return array_keys($this->query->all());
    }
}
