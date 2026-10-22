<?php

namespace App\Http\Requests\Visits;

use App\Models\CustomerVisit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CustomerVisit $visit */
        $visit = $this->route('visit');

        return $this->user()?->can('update', $visit) ?? false;
    }

    /**
     * Client-supplied assignee is never trusted — strip if present.
     */
    protected function prepareForValidation(): void
    {
        $this->request->remove('assigned_user_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var CustomerVisit $visit */
            $visit = $this->route('visit');
            $visit->loadMissing('customer');

            if (! $visit->customer?->assigned_sales_rep_id) {
                $validator->errors()->add(
                    'scheduled_at',
                    'لا يمكن تحديث الزيارة لأن العميل بلا مندوب مبيعات مُعيَّن. عيّن مندوبًا على سجل العميل أولاً.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'scheduled_at' => 'موعد الزيارة',
            'notes' => 'ملاحظات',
        ];
    }

    /**
     * @return array{assigned_user_id: int, scheduled_at: string, notes: string|null}
     */
    public function visitPayload(): array
    {
        /** @var CustomerVisit $visit */
        $visit = $this->route('visit');
        $visit->loadMissing('customer');

        return [
            'assigned_user_id' => (int) $visit->customer->assigned_sales_rep_id,
            'scheduled_at' => (string) $this->validated('scheduled_at'),
            'notes' => $this->validated('notes') ?? null,
        ];
    }
}
