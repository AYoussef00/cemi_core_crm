<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreAttachmentRequest extends FormRequest
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
        $extensions = config('attachments.allowed_extensions', []);
        $maxKb = (int) config('attachments.max_kilobytes', 5120);

        return [
            'file' => [
                'required',
                'file',
                File::types($extensions)->max($maxKb),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxMb = round(((int) config('attachments.max_kilobytes', 5120)) / 1024, 1);
        $types = implode(', ', config('attachments.allowed_extensions', []));

        return [
            'file.required' => 'اختر ملفًا للرفع.',
            'file.file' => 'الملف المرفوع غير صالح.',
            'file.max' => "حجم الملف يتجاوز الحد المسموح ({$maxMb} ميجابايت).",
            'file.mimes' => "نوع الملف غير مسموح. الأنواع المسموحة: {$types}.",
            'file.extensions' => "نوع الملف غير مسموح. الأنواع المسموحة: {$types}.",
        ];
    }
}
