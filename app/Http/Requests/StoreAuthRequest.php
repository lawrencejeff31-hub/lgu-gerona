<?php

namespace App\Http\Requests;

use App\Rules\ValidEmailAddress;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreAuthRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authentication requests are public
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $method = $this->route()->getActionMethod();
        
        switch ($method) {
            case 'register':
                return $this->registerRules();
            case 'login':
                return $this->loginRules();
            default:
                return [];
        }
    }

    /**
     * Get validation rules for registration.
     */
    private function registerRules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z\s\-\.\']+$/' // Only letters, spaces, hyphens, dots, and apostrophes
            ],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                'unique:users,email',
                new ValidEmailAddress()
            ],
            'password' => [
                'required',
                'string',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->uncompromised(),
            ]
        ];
    }

    /**
     * Get validation rules for login.
     */
    private function loginRules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255'
            ],
            'password' => [
                'required',
                'string',
                'min:1'
            ]
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Full name is required.',
            'name.regex' => 'Name can only contain letters, spaces, hyphens, dots, and apostrophes.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already registered.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters long.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize name
        if ($this->has('name')) {
            $this->merge(['name' => $this->sanitizeString($this->name)]);
        }

        // Sanitize and normalize email
        if ($this->has('email')) {
            $email = strtolower(trim($this->email));
            $email = filter_var($email, FILTER_SANITIZE_EMAIL);
            $this->merge(['email' => $email]);
        }
    }

    /**
     * Sanitize string input by removing potentially harmful characters.
     */
    private function sanitizeString(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        // Remove HTML tags and encode special characters
        $sanitized = strip_tags($input);
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8');
        
        // Trim whitespace
        return trim($sanitized);
    }
}