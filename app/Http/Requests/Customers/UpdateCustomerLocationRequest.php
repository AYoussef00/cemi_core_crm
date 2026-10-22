<?php

namespace App\Http\Requests\Customers;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCustomerLocationRequest extends FormRequest
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
        return [
            'address_ar' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'address_ar' => 'العنوان',
            'latitude' => 'خط العرض',
            'longitude' => 'خط الطول',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $lat = $this->input('latitude');
            $lng = $this->input('longitude');
            $latFilled = $lat !== null && $lat !== '';
            $lngFilled = $lng !== null && $lng !== '';

            if ($latFilled xor $lngFilled) {
                $validator->errors()->add(
                    'latitude',
                    'يجب إدخال خط العرض وخط الطول معًا، أو تركهما فارغين.'
                );
            }
        });
    }

    /**
     * @return array{address_ar: string|null, latitude: float|null, longitude: float|null}
     */
    public function locationPayload(): array
    {
        $lat = $this->validated('latitude');
        $lng = $this->validated('longitude');

        return [
            'address_ar' => $this->nullableString($this->validated('address_ar') ?? null),
            'latitude' => $lat !== null && $lat !== '' ? round((float) $lat, 7) : null,
            'longitude' => $lng !== null && $lng !== '' ? round((float) $lng, 7) : null,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
