<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Anyone can attempt to login
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email',
            ],
            'password' => [
                'required',
                'string',
            ],
            'remember' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    /**
     * Custom error messages for better API responses.
     */
    public function messages(): array
    {
        return [
            'email.required' => 'يرجى إدخال البريد الإلكتروني.',
            'email.email' => 'البريد الإلكتروني المدخل غير صالح.',
            'password.required' => 'يرجى إدخال كلمة المرور.',
            'remember.boolean' => 'قيمة حقل التذكر غير صالحة.',
        ];
    }
}
