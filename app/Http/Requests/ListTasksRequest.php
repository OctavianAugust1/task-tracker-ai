<?php

declare(strict_types=1);

namespace App\Http\Requests;

use UnexpectedValueException;

final class ListTasksRequest extends ApiRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', 'in:todo,in_progress,done'],
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

    /** @return 'todo'|'in_progress'|'done'|null */
    public function validatedStatus(): ?string
    {
        $status = $this->validated('status');

        if ($status === null) {
            return null;
        }

        if (! is_string($status) || ! in_array($status, ['todo', 'in_progress', 'done'], true)) {
            throw new UnexpectedValueException('Validated status has an invalid type.');
        }

        return $status;
    }
}
