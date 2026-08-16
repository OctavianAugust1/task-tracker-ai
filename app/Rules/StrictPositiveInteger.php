<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

final class StrictPositiveInteger implements ValidationRule
{
    /** @param Closure(string, string|null=): PotentiallyTranslatedString $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_int($value) || $value < 1) {
            $fail("The {$attribute} field must be a positive integer.");
        }
    }
}
