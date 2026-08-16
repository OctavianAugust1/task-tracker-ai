<?php

declare(strict_types=1);

namespace App\Http\Requests;

use UnexpectedValueException;

class StoreCategoryRequest extends ApiRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'min:1', 'max:100']];
    }

    /** @return non-empty-string */
    public function validatedName(): string
    {
        $name = $this->validated('name');
        if (! is_string($name) || $name === '') {
            throw new UnexpectedValueException('Validated category name has an invalid type.');
        }

        return $name;
    }
}
