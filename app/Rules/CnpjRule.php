<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CnpjRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        try {
            new \App\Modules\Core\Domain\ValueObjects\Cnpj($value);
        } catch (\InvalidArgumentException $e) {
            $fail('O CNPJ informado não é válido.');
        }
    }
}
