<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreDepartmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->hasRole(['admin', 'super-admin']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:departments,code',
                'regex:/^[A-Z0-9\-_]+$/' // Only uppercase letters, numbers, hyphens, and underscores
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:departments,name',
                'regex:/^[a-zA-Z0-9\s\-_.,()&]+$/' // Allow alphanumeric, spaces, and common punctuation
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000'
            ],
            'is_active' => [
                'sometimes',
                'boolean'
            ],
            'head_user_id' => [
                'nullable',
                'integer',
                'exists:users,id'
            ],
            'parent_department_id' => [
                'nullable',
                'integer',
                'exists:departments,id'
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Department code is required.',
            'code.unique' => 'This department code is already in use.',
            'code.regex' => 'Department code can only contain uppercase letters, numbers, hyphens, and underscores.',
            'name.required' => 'Department name is required.',
            'name.unique' => 'This department name is already in use.',
            'name.regex' => 'Department name contains invalid characters.',
            'description.max' => 'Description cannot exceed 1000 characters.',
            'head_user_id.exists' => 'Selected department head does not exist.',
            'parent_department_id.exists' => 'Selected parent department does not exist.'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize and format code
        if ($this->has('code')) {
            $code = strtoupper(trim($this->code));
            $code = preg_replace('/[^A-Z0-9\-_]/', '', $code);
            $this->merge(['code' => $code]);
        }

        // Sanitize name
        if ($this->has('name')) {
            $this->merge(['name' => $this->sanitizeString($this->name)]);
        }

        // Sanitize description
        if ($this->has('description')) {
            $this->merge(['description' => $this->sanitizeString($this->description)]);
        }

        // Ensure is_active is boolean
        if ($this->has('is_active')) {
            $this->merge(['is_active' => filter_var($this->is_active, FILTER_VALIDATE_BOOLEAN)]);
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

        // Set default values
        $validated['is_active'] = $validated['is_active'] ?? true;

        // Add audit information
        $validated['created_by'] = Auth::id();

        return $validated;
    }
}