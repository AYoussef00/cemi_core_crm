<?php

namespace App\Http\Requests\Warehouse;

use App\Models\Delivery;
use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryPodRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Delivery $delivery */
        $delivery = $this->route('delivery');

        return $this->user()?->can('uploadPod', $delivery) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = (int) config('attachments.max_kilobytes', 5120);
        $ext = implode(',', config('attachments.allowed_extensions', ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif']));

        return [
            'file' => ['required', 'file', 'max:'.$maxKb, 'extensions:'.$ext],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'file' => 'إثبات التسليم',
        ];
    }
}
