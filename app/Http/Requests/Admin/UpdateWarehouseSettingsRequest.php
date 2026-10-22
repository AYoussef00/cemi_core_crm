<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:255'],
            'address_ar' => ['nullable', 'string', 'max:500'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name_ar.required' => 'اسم المستودع مطلوب.',
            'name_ar.max' => 'اسم المستودع طويل جدًا.',
            'address_ar.max' => 'العنوان طويل جدًا.',
            'contact_phone.max' => 'رقم الهاتف طويل جدًا.',
        ];
    }
}
