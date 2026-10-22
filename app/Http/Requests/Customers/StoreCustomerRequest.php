<?php

namespace App\Http\Requests\Customers;

use App\Models\Customer;
use App\Models\User;
use App\Support\Authorization\RoleSlug;
use App\Support\Customers\CustomerContactRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Customer::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $repIds = User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', RoleSlug::SalesRepresentative->value))
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        return [
            'name_ar' => ['required', 'string', 'max:255'],
            'assigned_sales_rep_id' => [
                $this->user()?->hasRole(RoleSlug::SalesManager) ? 'nullable' : 'prohibited',
                'integer',
                Rule::in($repIds),
            ],
            'contacts' => ['nullable', 'array', 'max:20'],
            'contacts.*.role' => ['required_with:contacts.*.name_ar', Rule::in(CustomerContactRole::values())],
            'contacts.*.name_ar' => ['nullable', 'string', 'max:255'],
            'contacts.*.phone' => ['nullable', 'string', 'max:64'],
            'contacts.*.email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name_ar' => 'اسم المنشأة',
            'assigned_sales_rep_id' => 'مندوب المبيعات المُعيَّن',
            'contacts.*.role' => 'نوع جهة الاتصال',
            'contacts.*.name_ar' => 'اسم جهة الاتصال',
            'contacts.*.phone' => 'هاتف جهة الاتصال',
            'contacts.*.email' => 'بريد جهة الاتصال',
        ];
    }

    /**
     * @return array{name_ar: string, assigned_sales_rep_id: int|null, contacts: list<array<string, mixed>>}
     */
    public function customerPayload(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        $assignedId = null;

        if ($user->hasRole(RoleSlug::SalesRepresentative)) {
            $assignedId = $user->id;
        } elseif ($user->hasRole(RoleSlug::SalesManager)) {
            $assignedId = $this->validated('assigned_sales_rep_id');
            $assignedId = $assignedId !== null ? (int) $assignedId : null;
        }

        return [
            'name_ar' => (string) $this->validated('name_ar'),
            'assigned_sales_rep_id' => $assignedId,
            'contacts' => $this->validated('contacts') ?? [],
        ];
    }
}
