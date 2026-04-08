<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteFileRequest extends FormRequest
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
            // No additional validation needed - the attachment ID is in the route parameter
        ];
    }

    /**
     * Custom error messages for better API responses.
     */
    public function messages(): array
    {
        return [
            // No validation rules, no custom messages needed
        ];
    }
}
