<?php

namespace App\Http\Requests\Visits;

use App\Models\Customer;
use App\Models\CustomerVisit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()?->can('create', CustomerVisit::class)) {
            return false;
        }

        $customerId = (int) $this->input('customer_id');
        $customer = Customer::query()->find($customerId);

        return $customer !== null
            && ($this->user()?->can('createForCustomer', [CustomerVisit::class, $customer]) ?? false);
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
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
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

            $customer = Customer::query()->find((int) $this->input('customer_id'));
            if ($customer === null) {
                return;
            }

            if (! $customer->assigned_sales_rep_id) {
                $validator->errors()->add(
                    'customer_id',
                    'لا يمكن جدولة زيارة لهذا العميل لأنه بلا مندوب مبيعات مُعيَّن. عيّن مندوبًا على سجل العميل أولاً.'
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
            'customer_id' => 'العميل',
            'scheduled_at' => 'موعد الزيارة',
            'notes' => 'ملاحظات',
        ];
    }

    /**
     * @return array{customer_id: int, assigned_user_id: int, scheduled_at: string, notes: string|null}
     */
    public function visitPayload(): array
    {
        $customer = Customer::query()->findOrFail((int) $this->validated('customer_id'));

        return [
            'customer_id' => (int) $customer->id,
            'assigned_user_id' => (int) $customer->assigned_sales_rep_id,
            'scheduled_at' => (string) $this->validated('scheduled_at'),
            'notes' => $this->validated('notes') ?? null,
        ];
    }
}
