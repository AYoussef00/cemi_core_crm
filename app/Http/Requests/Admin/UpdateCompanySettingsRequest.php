<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanySettingsRequest extends FormRequest
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
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name_ar.required' => 'اسم الشركة مطلوب.',
            'name_ar.max' => 'اسم الشركة طويل جدًا.',
            'address_ar.max' => 'العنوان طويل جدًا.',
            'contact_phone.max' => 'رقم الهاتف طويل جدًا.',
            'contact_email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
        ];
    }
}
