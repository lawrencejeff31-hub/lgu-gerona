<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Auth;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check() && (
            Auth::user()->hasRole(['admin', 'super-admin']) ||
            Auth::id() === (int) $this->route('user')->id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z\s\-\.\']+$/' // Only letters, spaces, hyphens, dots, and apostrophes
            ],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email:rfc,dns',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId)
            ],
            'password' => [
                'sometimes',
                'required',
                'string',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->uncompromised(),
            ],
            'role' => [
                'sometimes',
                'nullable',
                'string',
                'exists:roles,name'
            ],
            'department_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:departments,id'
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'regex:/^[\+]?[0-9\-\(\)\s]+$/',
                'max:20'
            ],
            'position' => [
                'sometimes',
                'nullable',
                'string',
                'max:100'
            ],
            'pnpki_certificate_serial' => [
                'sometimes',
                'nullable',
                'string',
                'max:128'
            ],
            'can_sign_digitally' => [
                'sometimes',
                'boolean'
            ],
            'is_active' => [
                'sometimes',
                'boolean'
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
            'email.unique' => 'This email address is already taken.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters long.',
            'role.exists' => 'The selected role does not exist.',
            'department_id.exists' => 'The selected department does not exist.',
            'phone.regex' => 'Please provide a valid phone number.',
            'position.max' => 'Position cannot exceed 100 characters.',
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

        // Sanitize email
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim($this->email))]);
        }

        // Sanitize position
        if ($this->has('position')) {
            $this->merge(['position' => $this->sanitizeString($this->position)]);
        }

        // Sanitize phone
        if ($this->has('phone')) {
            $phone = preg_replace('/[^\+0-9\-\(\)\s]/', '', $this->phone);
            $this->merge(['phone' => trim($phone)]);
        }

        // Sanitize PNPKI certificate serial
        if ($this->has('pnpki_certificate_serial')) {
            $this->merge(['pnpki_certificate_serial' => $this->sanitizeString($this->pnpki_certificate_serial)]);
        }

        // Ensure is_active is boolean
        if ($this->has('is_active')) {
            $this->merge(['is_active' => filter_var($this->is_active, FILTER_VALIDATE_BOOLEAN)]);
        }

        // Ensure can_sign_digitally is boolean
        if ($this->has('can_sign_digitally')) {
            $this->merge(['can_sign_digitally' => filter_var($this->can_sign_digitally, FILTER_VALIDATE_BOOLEAN)]);
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

    /**
     * Get the validated data with additional processing.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Add audit information
        $validated['updated_by'] = Auth::id();

        return $validated;
    }
}