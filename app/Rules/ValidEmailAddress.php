<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidEmailAddress implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $fail('The :attribute must be a valid email address.');
            return;
        }

        $domain = substr(strrchr($value, "@"), 1);
        
        if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
            $fail('The :attribute domain does not exist or cannot receive emails.');
            return;
        }
    }
}
