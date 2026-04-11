<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Anyone can register
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:128',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            ],
            'password_confirmation' => [
                'required',
                'string',
            ],
            'role' => [
                'required',
                'string',
                'in:customer,provider,admin',
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20',
            ],
            'company_name' => [
                'nullable',
                'string',
                'max:255',
                'required_if:role,provider',
            ],
        ];
    }

    /**
     * Custom error messages for better API responses (Arabic).
     */
    public function messages(): array
    {
        return [
            'name.required' => 'الاسم الكامل مطلوب.',
            'name.min' => 'يجب أن يكون الاسم حرفين على الأقل.',
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'البريد الإلكتروني غير صالح.',
            'email.unique' => 'هذا البريد الإلكتروني مسجل مسبقاً.',
            'password.required' => 'كلمة المرور مطلوبة.',
            'password.min' => 'يجب أن تكون كلمة المرور 8 أحرف على الأقل.',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
            'password.regex' => 'يجب أن تحتوي كلمة المرور على حرف كبير (A-Z)، وحرف صغير (a-z)، ورقم (0-9)، ورمز خاص (@$!%*?&).',
            'password_confirmation.required' => 'تأكيد كلمة المرور مطلوب.',
            'role.required' => 'يرجى اختيار نوع الحساب (عميل أو مزود خدمة).',
            'role.in' => 'نوع الحساب المختار غير صالح.',
            'company_name.required_if' => 'اسم الشركة مطلوب عند التسجيل كمزود خدمة.',
        ];
    }
}
