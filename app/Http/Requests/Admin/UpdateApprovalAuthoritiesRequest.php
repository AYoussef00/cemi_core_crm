<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApprovalAuthoritiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('approval_authority.configure') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'authorities' => ['required', 'array'],
            'authorities.*.assigned_role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'authorities.required' => 'بيانات جهات الاعتماد مطلوبة.',
            'authorities.*.assigned_role_id.required' => 'يجب اختيار دور لكل مسار اعتماد.',
            'authorities.*.assigned_role_id.exists' => 'الدور المحدد غير صالح.',
        ];
    }
}
