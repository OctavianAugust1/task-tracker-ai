<?php

namespace App\Http\Requests;

use App\Http\Responses\ApiErrorResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('title'))) {
            $this->merge(['title' => trim($this->input('title'))]);
        }
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff($this->inputFieldNames(), $this->allowedFields()) as $field) {
                $validator->errors()->add($field, "The {$field} field is not allowed.");
            }

            if ($this->requiresAtLeastOneField() && $this->hasNoAllowedFields()) {
                $validator->errors()->add('_request', 'At least one field must be provided.');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        $details = [];

        foreach ($validator->errors()->messages() as $field => $messages) {
            foreach ($messages as $message) {
                $details[] = [
                    'field' => $field === '_request' ? null : $field,
                    'message' => $message,
                ];
            }
        }

        throw new HttpResponseException(ApiErrorResponse::make(
            code: 'validation_failed',
            message: 'Request validation failed',
            details: $details,
            status: 422,
        ));
    }

    protected function allowedFields(): array
    {
        return array_keys($this->rules());
    }

    protected function inputFieldNames(): array
    {
        return array_keys($this->all());
    }

    protected function requiresAtLeastOneField(): bool
    {
        return false;
    }

    private function hasNoAllowedFields(): bool
    {
        return array_intersect($this->inputFieldNames(), $this->allowedFields()) === [];
    }
}
