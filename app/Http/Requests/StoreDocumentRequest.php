<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use App\Models\DocumentType;

class StoreDocumentRequest extends FormRequest
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
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9\s\-_.,()]+$/' // Allow alphanumeric, spaces, and common punctuation
            ],
            'description' => [
                'nullable',
                'string',
                'max:2000'
            ],
            'type' => [
                'required',
                'string',
                Rule::in(['bid', 'award', 'contract', 'other', 'PR', 'PO', 'DV'])
            ],
            'priority' => [
                'required',
                'string',
                Rule::in(['low', 'medium', 'high', 'urgent'])
            ],
            'security_level' => [
                'nullable',
                'string',
                Rule::in(['public', 'internal', 'confidential', 'secret'])
            ],
            'deadline' => [
                'nullable',
                'date',
                'after:today'
            ],
            'department_id' => [
                'required',
                'integer',
                'exists:departments,id'
            ],
            'document_type_id' => [
                'required',
                'integer',
                'exists:document_types,id'
            ],
            'assigned_to' => [
                'nullable',
                'integer',
                'exists:users,id'
            ],
            'tags' => [
                'nullable',
                'array',
                'max:10'
            ],
            'tags.*' => [
                'string',
                'max:50',
                'regex:/^[a-zA-Z0-9\-_]+$/' // Only alphanumeric, hyphens, and underscores
            ],
            'file' => [
                'nullable',
                'file',
                'mimes:pdf,doc,docx,xls,xlsx,txt,jpg,jpeg,png',
                'max:10240' // 10MB
            ],
            'metadata' => [
                'nullable',
                'array'
            ],
            'document_number' => [
                'nullable',
                'string',
                'unique:documents,document_number'
            ],
            'qr_code_path' => [
                'nullable',
                'string'
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Document title is required.',
            'title.regex' => 'Document title contains invalid characters.',
            'type.in' => 'Invalid document type selected.',
            'priority.in' => 'Invalid priority level selected.',
            'security_level.in' => 'Invalid security level selected.',
            'deadline.after' => 'Deadline must be a future date.',
            'department_id.exists' => 'Selected department does not exist.',
            'document_type_id.exists' => 'Selected document type does not exist.',
            'assigned_to.exists' => 'Selected user does not exist.',
            'tags.max' => 'Maximum 10 tags allowed.',
            'tags.*.regex' => 'Tags can only contain letters, numbers, hyphens, and underscores.',
            'file.mimes' => 'File must be a PDF, Word document, Excel file, text file, or image.',
            'file.max' => 'File size cannot exceed 10MB.'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $docTypeId = $this->input('document_type_id');
            $metadata = $this->input('metadata');

            if ($docTypeId && is_array($metadata)) {
                $documentType = DocumentType::find($docTypeId);
                if ($documentType && is_array($documentType->schema)) {
                    $errors = $this->validateMetadataAgainstSchema($metadata, $documentType->schema);
                    foreach ($errors as $error) {
                        $validator->errors()->add('metadata', $error);
                    }
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize input data
        $this->merge([
            'title' => $this->sanitizeString($this->title),
            'description' => $this->sanitizeString($this->description),
            'type' => $this->sanitizeString($this->type),
            'priority' => $this->sanitizeString($this->priority),
            'security_level' => $this->sanitizeString($this->security_level),
        ]);

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
     * Get the validated data with additional processing.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Set default values
        if (!isset($validated['security_level'])) {
            $validated['security_level'] = 'internal';
        }

        // Add creator information
        $validated['created_by'] = Auth::id();

        return $validated;
    }

    /**
     * Validate metadata array against a simple JSON schema stored on DocumentType.
     * Supported field attributes: key, type (string|number|integer|boolean|date), required,
     * maxLength, min, max, enum, pattern.
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

            // Required check
            if (!empty($field['required']) && !$exists) {
                $errors[] = "Missing required metadata field: {$key}";
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