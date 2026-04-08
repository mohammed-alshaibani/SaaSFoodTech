<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadFileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:10240', // 10MB max
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,txt',
            ],
            'service_request_id' => [
                'required',
                'integer',
                'exists:service_requests,id',
            ],
        ];
    }

    /**
     * Custom error messages for better API responses.
     */
    public function messages(): array
    {
        return [
            'file.required' => 'A file is required for upload.',
            'file.file' => 'The uploaded content must be a file.',
            'file.max' => 'File size may not exceed 10MB.',
            'file.mimes' => 'Only image files (JPG, PNG, GIF, WebP) and documents (PDF, DOC, DOCX, TXT) are allowed.',
            
            'service_request_id.required' => 'Service request ID is required.',
            'service_request_id.integer' => 'Service request ID must be an integer.',
            'service_request_id.exists' => 'The specified service request does not exist.',
        ];
    }
}
