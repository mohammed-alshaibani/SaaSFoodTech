<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequestRequest extends FormRequest
{
    /**
     * Authorize via the Policy — Gate::allows('create', ServiceRequest::class).
     * The FormRequest will automatically use AuthServiceProvider's policy map.
     */
    public function authorize(): bool
    {
        return $this->user()->can('request.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'title' => [
                'required',
                'string',
                'min:3',
                'max:255',
                'regex:/^[a-zA-Z0-9\s\-\.\,\!\?\;\:\&\#\$\%\(\)\[\]\{\}\p{L}]+$/u',
            ],
            'description' => [
                'required',
                'string',
                'min:5',
                'max:5000',
                'regex:/^[\s\S]*$/',
            ],
            'latitude' => [
                'required',
                'numeric',
                'between:-90,90',
                'regex:/^-?\d{1,3}(\.\d{1,8})?$/',
            ],
            'longitude' => [
                'required',
                'numeric',
                'between:-180,180',
                'regex:/^-?\d{1,3}(\.\d{1,8})?$/',
            ],
            // Geographic business area validation
            'business_area' => [
                'nullable',
                'string',
                'in:downtown,midtown,uptown,suburbs',
            ],
            'attachments' => [
                'nullable',
                'array',
                'max:5',
            ],
            'attachments.*' => [
                'string',
                'url',
                'max:2048',
                'regex:/^https?:\/\/.+\.(jpg|jpeg|png|gif|pdf|doc|docx)$/i',
            ],
            'category' => [
                'nullable',
                'string',
                'max:100',
                'in:plumbing,electrical,hvac,cleaning,landscaping,pest_control,other',
            ],
            'urgency' => [
                'nullable',
                'string',
                'in:low,medium,high,emergency',
            ],
            'provider_id' => [
                'nullable',
                'exists:users,id',
            ],
        ];
    }

    /**
     * Custom error messages for better API responses.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'A title is required for the service request.',
            'title.min' => 'The title must be at least 3 characters long.',
            'title.max' => 'The title may not be greater than 255 characters.',
            'title.regex' => 'The title contains invalid characters.',

            'description.required' => 'A description is required for the service request.',
            'description.min' => 'The description must be at least 5 characters long.',
            'description.max' => 'The description may not be greater than 5000 characters.',

            'latitude.required' => 'Latitude is required to determine service location.',
            'latitude.between' => 'Latitude must be between -90 and 90.',
            'latitude.regex' => 'Latitude format is invalid. Please provide a valid coordinate.',

            'longitude.required' => 'Longitude is required to determine service location.',
            'longitude.between' => 'Longitude must be between -180 and 180.',
            'longitude.regex' => 'Longitude format is invalid. Please provide a valid coordinate.',

            'attachments.max' => 'You may upload up to 5 attachments.',
            'attachments.*.url' => 'Each attachment must be a valid URL.',
            'attachments.*.max' => 'Attachment URLs may not be greater than 2048 characters.',
            'attachments.*.regex' => 'Only image files (JPG, PNG, GIF) and documents (PDF, DOC, DOCX) are allowed.',

            'category.in' => 'The selected category is invalid. Please choose from: plumbing, electrical, hvac, cleaning, landscaping, pest_control, other.',
            'urgency.in' => 'The urgency level is invalid. Please choose from: low, medium, high, emergency.',
            'business_area.in' => 'The business area is invalid. Please choose from: downtown, midtown, uptown, suburbs.',
        ];
    }
}
