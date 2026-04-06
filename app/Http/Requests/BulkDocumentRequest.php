<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BulkDocumentRequest extends FormRequest
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
        $action = $this->route()->getActionMethod();
        
        switch ($action) {
            case 'bulkUpdateStatus':
                return $this->bulkUpdateStatusRules();
            case 'bulkAssign':
                return $this->bulkAssignRules();
            case 'bulkUpdateDepartment':
                return $this->bulkUpdateDepartmentRules();
            case 'bulkDelete':
                return $this->bulkDeleteRules();
            default:
                return [];
        }
    }

    /**
     * Rules for bulk status update.
     */
    private function bulkUpdateStatusRules(): array
    {
        return [
            'document_ids' => [
                'required',
                'array',
                'min:1',
                'max:100' // Limit bulk operations
            ],
            'document_ids.*' => [
                'integer',
                'exists:documents,id'
            ],
            'status' => [
                'required',
                'string',
                Rule::in(\App\Enums\DocumentStatus::values())
            ],
            'notes' => [
                'nullable',
                'string',
                'max:500'
            ]
        ];
    }

    /**
     * Rules for bulk assignment.
     */
    private function bulkAssignRules(): array
    {
        return [
            'document_ids' => [
                'required',
                'array',
                'min:1',
                'max:100'
            ],
            'document_ids.*' => [
                'integer',
                'exists:documents,id'
            ],
            'assigned_to' => [
                'required',
                'integer',
                'exists:users,id'
            ],
            'notes' => [
                'nullable',
                'string',
                'max:500'
            ]
        ];
    }

    /**
     * Rules for bulk department update.
     */
    private function bulkUpdateDepartmentRules(): array
    {
        return [
            'document_ids' => [
                'required',
                'array',
                'min:1',
                'max:100'
            ],
            'document_ids.*' => [
                'integer',
                'exists:documents,id'
            ],
            'department_id' => [
                'required',
                'integer',
                'exists:departments,id'
            ],
            'notes' => [
                'nullable',
                'string',
                'max:500'
            ]
        ];
    }

    /**
     * Rules for bulk delete.
     */
    private function bulkDeleteRules(): array
    {
        return [
            'document_ids' => [
                'required',
                'array',
                'min:1',
                'max:50' // Smaller limit for deletions
            ],
            'document_ids.*' => [
                'integer',
                'exists:documents,id'
            ],
            'confirm' => [
                'required',
                'boolean',
                'accepted'
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'document_ids.required' => 'At least one document must be selected.',
            'document_ids.array' => 'Document IDs must be provided as an array.',
            'document_ids.min' => 'At least one document must be selected.',
            'document_ids.max' => 'Too many documents selected. Maximum allowed is :max.',
            'document_ids.*.exists' => 'One or more selected documents do not exist.',
            'status.in' => 'Invalid status selected.',
            'assigned_to.exists' => 'Selected user does not exist.',
            'department_id.exists' => 'Selected department does not exist.',
            'notes.max' => 'Notes cannot exceed 500 characters.',
            'confirm.accepted' => 'You must confirm this bulk operation.'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize notes if present
        if ($this->has('notes')) {
            $this->merge(['notes' => $this->sanitizeString($this->notes)]);
        }

        // Ensure document_ids is an array of integers
        if ($this->has('document_ids')) {
            $documentIds = $this->document_ids;
            if (is_string($documentIds)) {
                $documentIds = explode(',', $documentIds);
            }
            
            $sanitizedIds = array_map('intval', array_filter($documentIds, 'is_numeric'));
            $this->merge(['document_ids' => array_unique($sanitizedIds)]);
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

        // Add user information for audit trail
        $validated['performed_by'] = Auth::id();
        $validated['performed_at'] = now();

        return $validated;
    }
}