<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceRequestRequest extends FormRequest
{
    /**
     * Authorize via Policy - only providers can update request status.
     */
    public function authorize(): bool
    {
        return $this->user()->can('accept', $this->serviceRequest) || 
               $this->user()->can('complete', $this->serviceRequest);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
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
