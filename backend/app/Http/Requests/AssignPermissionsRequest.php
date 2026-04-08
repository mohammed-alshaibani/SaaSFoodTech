<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('permission.assign');
    }

    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }
}
