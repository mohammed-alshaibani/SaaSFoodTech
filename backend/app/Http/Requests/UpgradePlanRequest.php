<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpgradePlanRequest extends FormRequest
{
    /**
     * Authorize via Policy - Only authenticated users can upgrade.
     */
    public function authorize(): bool
    {
        return \Illuminate\Support\Facades\Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'plan' => [
                'required',
                'string',
                'in:free,basic,premium,enterprise',
            ],
            'payment_method' => [
                'required',
                'string',
                'in:card,paypal,bank_transfer',
            ],
            'payment_token' => [
                'required_if:payment_method,card',
                'string',
                'min:10',
                'max:255',
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
            'plan.required' => 'Please select a plan to upgrade to.',
            'plan.in' => 'The selected plan is invalid. Available plans: free, basic, premium, enterprise.',
            'payment_method.required' => 'Please select a payment method.',
            'payment_method.in' => 'The payment method is invalid. Available methods: card, paypal, bank_transfer.',
            'payment_token.required_if' => 'Payment token is required for card payments.',
            'payment_token.min' => 'Payment token must be at least 10 characters.',
            'payment_token.max' => 'Payment token may not be greater than 255 characters.',
        ];
    }
}
