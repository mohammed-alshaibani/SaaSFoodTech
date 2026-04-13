<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceRequestRequest extends FormRequest
{
    /**
     * Authorize via Policy - only providers can update request status.
     */
    /**
     * Defer authorization to the Controller and Policies
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // Standard PUT update (for title, description, and Admin status override)
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            if (!$this->routeIs('*/accept') && !$this->routeIs('*/complete')) {
                return [
                    'title' => 'sometimes|string|max:255',
                    'description' => 'sometimes|string',
                    'status' => 'sometimes|string|in:pending,accepted,work_done,completed,cancelled',
                    'category' => 'sometimes|string',
                    'urgency' => 'sometimes|string|in:low,medium,high,critical',
                    'latitude' => 'sometimes|nullable|numeric|between:-90,90',
                    'longitude' => 'sometimes|nullable|numeric|between:-180,180',
                    'provider_id' => 'sometimes|nullable|exists:users,id',
                ];
            }
        }

        $rules = [];

        // For accept action
        if ($this->isMethod('PATCH') && $this->routeIs('*/accept')) {
            $rules = [
                'provider_notes' => [
                    'nullable',
                    'string',
                    'max:1000',
                    'regex:/^[a-zA-Z0-9\s\-\.\,\!\?\;\:\&\#\$\%\(\)\[\]\{\}]+$/',
                ],
                'estimated_completion' => [
                    'nullable',
                    'date',
                    'after:today',
                ],
            ];
        }

        // For complete action
        if ($this->isMethod('PATCH') && $this->routeIs('*/complete')) {
            $rules = [
                'completion_notes' => [
                    'nullable',
                    'string',
                    'max:1000',
                    'regex:/^[a-zA-Z0-9\s\-\.\,\!\?\;\:\&\#\$\%\(\)\[\]\{\}]+$/',
                ],
                'final_attachments' => [
                    'nullable',
                    'array',
                    'max:10',
                ],
                'final_attachments.*' => [
                    'string',
                    'url',
                    'max:2048',
                    'regex:/^https?:\/\/.+\.(jpg|jpeg|png|gif|pdf|doc|docx)$/i',
                ],
                'rating' => [
                    'nullable',
                    'integer',
                    'between:1,5',
                ],
            ];
        }

        return $rules;
    }

    /**
     * Custom error messages for better API responses.
     */
    public function messages(): array
    {
        return [
            'provider_notes.max' => 'Provider notes may not exceed 1000 characters.',
            'provider_notes.regex' => 'Provider notes contain invalid characters.',

            'estimated_completion.date' => 'Estimated completion must be a valid date.',
            'estimated_completion.after' => 'Estimated completion must be after today.',

            'completion_notes.max' => 'Completion notes may not exceed 1000 characters.',
            'completion_notes.regex' => 'Completion notes contain invalid characters.',

            'final_attachments.max' => 'You may upload up to 10 final attachments.',
            'final_attachments.*.url' => 'Each final attachment must be a valid URL.',
            'final_attachments.*.regex' => 'Only image files and documents are allowed for final attachments.',

            'rating.between' => 'Rating must be between 1 and 5.',
        ];
    }
}
