<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use App\Models\DocumentType;

class UpdateDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9\s\-_.,()]+$/'
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:2000'
            ],
            'type' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['bid', 'award', 'contract', 'other', 'PR', 'PO', 'DV'])
            ],
            'priority' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['low', 'medium', 'high', 'urgent'])
            ],
            'security_level' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(['public', 'internal', 'confidential', 'secret'])
            ],
            'status' => [
                'sometimes',
                'required',
                'string',
                Rule::in(\App\Enums\DocumentStatus::values())
            ],
            'deadline' => [
                'sometimes',
                'nullable',
                'date',
                'after:today'
            ],
            'department_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:departments,id'
            ],
            'current_department_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:departments,id'
            ],
            'document_type_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:document_types,id'
            ],
            'assigned_to' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:users,id'
            ],
            'tags' => [
                'sometimes',
                'nullable',
                'array',
                'max:10'
            ],
            'tags.*' => [
                'string',
                'max:50',
                'regex:/^[a-zA-Z0-9\-_]+$/'
            ],
            'metadata' => [
                'sometimes',
                'nullable',
                'array'
            ],
            'rejection_reason' => [
                'sometimes',
                'nullable',
                'string',
                'max:500'
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.regex' => 'Document title contains invalid characters.',
            'type.in' => 'Invalid document type selected.',
            'priority.in' => 'Invalid priority level selected.',
            'security_level.in' => 'Invalid security level selected.',
            'status.in' => 'Invalid status selected.',
            'deadline.after' => 'Deadline must be a future date.',
            'department_id.exists' => 'Selected department does not exist.',
            'current_department_id.exists' => 'Selected current department does not exist.',
            'document_type_id.exists' => 'Selected document type does not exist.',
            'assigned_to.exists' => 'Selected user does not exist.',
            'tags.max' => 'Maximum 10 tags allowed.',
            'tags.*.regex' => 'Tags can only contain letters, numbers, hyphens, and underscores.',
            'rejection_reason.max' => 'Rejection reason cannot exceed 500 characters.'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize input data
        if ($this->has('title')) {
            $this->merge(['title' => $this->sanitizeString($this->title)]);
        }
        
        if ($this->has('description')) {
            $this->merge(['description' => $this->sanitizeString($this->description)]);
        }
        
        if ($this->has('type')) {
            $this->merge(['type' => $this->sanitizeString($this->type)]);
        }
        
        if ($this->has('priority')) {
            $this->merge(['priority' => $this->sanitizeString($this->priority)]);
        }
        
        if ($this->has('security_level')) {
            $this->merge(['security_level' => $this->sanitizeString($this->security_level)]);
        }
        
        if ($this->has('status')) {
            $this->merge(['status' => $this->sanitizeString($this->status)]);
        }
        
        if ($this->has('rejection_reason')) {
            $this->merge(['rejection_reason' => $this->sanitizeString($this->rejection_reason)]);
        }

        // Sanitize tags array
        if ($this->has('tags') && is_array($this->tags)) {
            $sanitizedTags = array_map(function ($tag) {
                return $this->sanitizeString($tag);
            }, $this->tags);
            $this->merge(['tags' => array_filter($sanitizedTags)]);
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
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Only run if metadata provided
            if (!$this->has('metadata') || !is_array($this->metadata)) {
                return;
            }

            $docTypeId = $this->input('document_type_id');
            // If document_type_id is not provided in update, try existing document type via route model binding? Skip for simplicity.
            if ($docTypeId) {
                $documentType = DocumentType::find($docTypeId);
                if ($documentType && is_array($documentType->schema)) {
                    $errors = $this->validateMetadataAgainstSchema($this->metadata, $documentType->schema);
                    foreach ($errors as $error) {
                        $validator->errors()->add('metadata', $error);
                    }
                }
            }
        });
    }

    /**
     * Validate metadata against DocumentType schema (same helper as Store).
     */
    private function validateMetadataAgainstSchema(array $metadata, array $schema): array
    {
        $errors = [];

        $fields = $schema['fields'] ?? [];
        foreach ($fields as $field) {
            $key = $field['key'] ?? null;
            if (!$key) {
                continue;
            }

            $exists = array_key_exists($key, $metadata);
            $value = $exists ? $metadata[$key] : null;

            // Required check (for update, only validate if present; leave stricter checks to Store)
            if (!empty($field['required']) && !$exists) {
                // Don't force missing required on update unless the key is provided but empty
                continue;
            }

            if (!$exists) {
                continue;
            }

            $type = $field['type'] ?? 'string';
            switch ($type) {
                case 'string':
                    if (!is_string($value)) {
                        $errors[] = "Field {$key} must be a string.";
                    } elseif (isset($field['maxLength']) && mb_strlen($value) > (int)$field['maxLength']) {
                        $errors[] = "Field {$key} exceeds maximum length of {$field['maxLength']}.";
                    } elseif (isset($field['pattern']) && !@preg_match('/' . $field['pattern'] . '/u', $value)) {
                        $errors[] = "Field {$key} does not match required pattern.";
                    }
                    break;
                case 'number':
                    if (!is_numeric($value)) {
                        $errors[] = "Field {$key} must be a number.";
                    } else {
                        $num = (float) $value;
                        if (isset($field['min']) && $num < (float)$field['min']) {
                            $errors[] = "Field {$key} must be at least {$field['min']}.";
                        }
                        if (isset($field['max']) && $num > (float)$field['max']) {
                            $errors[] = "Field {$key} must be at most {$field['max']}.";
                        }
                    }
                    break;
                case 'integer':
                    if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
                        $errors[] = "Field {$key} must be an integer.";
                    }
                    break;
                case 'boolean':
                    if (!is_bool($value)) {
                        $errors[] = "Field {$key} must be a boolean.";
                    }
                    break;
                case 'date':
                    if (!(is_string($value) && strtotime($value) !== false)) {
                        $errors[] = "Field {$key} must be a valid date.";
                    }
                    break;
            }

            if (isset($field['enum']) && is_array($field['enum']) && !in_array($value, $field['enum'])) {
                $errors[] = "Field {$key} must be one of: " . implode(', ', $field['enum']) . '.';
            }
        }

        return $errors;
    }
}