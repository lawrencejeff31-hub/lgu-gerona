<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreDocumentTrackingRequest extends FormRequest
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
            'document_id' => [
                'required',
                'integer',
                'exists:documents,id'
            ],
            'action' => [
                'required',
                'string',
                'in:created,updated,submitted,reviewed,approved,rejected,assigned,completed,forwarded,received,archived'
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000'
            ],
            'changes' => [
                'nullable',
                'array'
            ],
            'changes.*' => [
                'string',
                'max:255'
            ],
            'from_office_id' => [
                'nullable',
                'integer',
                'exists:departments,id'
            ],
            'to_office_id' => [
                'nullable',
                'integer',
                'exists:departments,id'
            ]
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'document_id.required' => 'Document ID is required.',
            'document_id.exists' => 'The selected document does not exist.',
            'action.required' => 'Action is required.',
            'action.in' => 'Invalid action type selected.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
            'changes.array' => 'Changes must be an array.',
            'from_office_id.exists' => 'The selected from office does not exist.',
            'to_office_id.exists' => 'The selected to office does not exist.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize notes
        if ($this->has('notes')) {
            $this->merge(['notes' => $this->sanitizeString($this->notes)]);
        }

        // Sanitize action
        if ($this->has('action')) {
            $this->merge(['action' => strtolower(trim($this->action))]);
        }

        // Sanitize changes array
        if ($this->has('changes') && is_array($this->changes)) {
            $sanitizedChanges = array_map(function ($change) {
                return $this->sanitizeString($change);
            }, $this->changes);
            $this->merge(['changes' => array_filter($sanitizedChanges)]);
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

        // Add user and timestamp information
        $validated['user_id'] = Auth::id();
        $validated['action_date'] = now();

        return $validated;
    }
}