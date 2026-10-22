<?php

namespace App\Http\Requests\Customers;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreCustomerPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Customer $customer */
        $customer = $this->route('customer');

        return $this->user()?->can('update', $customer) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $extensions = config('attachments.customer_photo_extensions', ['jpg', 'jpeg', 'png', 'webp', 'gif']);
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
        $types = implode(', ', config('attachments.customer_photo_extensions', []));

        return [
            'file.required' => 'اختر صورة للرفع.',
            'file.file' => 'الملف المرفوع غير صالح.',
            'file.max' => "حجم الصورة يتجاوز الحد المسموح ({$maxMb} ميجابايت).",
            'file.extensions' => "نوع الصورة غير مسموح. الأنواع: {$types}.",
        ];
    }
}
