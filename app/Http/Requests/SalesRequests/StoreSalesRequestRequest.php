<?php

namespace App\Http\Requests\SalesRequests;

use App\Models\Customer;
use App\Models\SalesRequest;
use App\Support\SalesRequests\LineAvailabilityResolver;
use App\Support\SalesRequests\OriginVisitResolver;
use DomainException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSalesRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()?->can('create', SalesRequest::class)) {
            return false;
        }

        $customerId = (int) $this->input('customer_id');
        if ($customerId <= 0) {
            return true;
        }

        $customer = Customer::query()->find($customerId);
        if ($customer === null) {
            return true;
        }

        return $this->user()->can('createForCustomer', [SalesRequest::class, $customer]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')],
            'origin_visit_id' => ['nullable', 'integer', Rule::exists('customer_visits', 'id')],
            'notes_ar' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            // Client availability is ignored — resolved from warehouse stock (SR-AUTO-STOCK).
            'lines.*.availability' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_id' => 'المنشأة',
            'origin_visit_id' => 'الزيارة',
            'notes_ar' => 'ملاحظات',
            'lines' => 'البنود',
            'lines.*.product_id' => 'المنتج',
            'lines.*.quantity' => 'الكمية',
            'lines.*.availability' => 'التوفر',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $visitId = $this->integer('origin_visit_id');
            if ($visitId <= 0) {
                return;
            }

            $user = $this->user();
            if ($user === null) {
                return;
            }

            try {
                app(OriginVisitResolver::class)->attachable(
                    $visitId,
                    (int) $this->input('customer_id'),
                    $user,
                );
            } catch (DomainException $e) {
                $validator->errors()->add('origin_visit_id', $e->getMessage());
            }
        });
    }

    /**
     * @return array{
     *     customer_id: int,
     *     origin_visit_id: int|null,
     *     notes_ar: string|null,
     *     lines: list<array{product_id: int, quantity: string, availability: string}>
     * }
     */
    public function payload(): array
    {
        $resolver = app(LineAvailabilityResolver::class);
        $lines = [];
        foreach ($this->validated('lines') as $line) {
            $quantity = number_format((float) $line['quantity'], 3, '.', '');
            $productId = (int) $line['product_id'];
            $lines[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'availability' => $resolver->resolve($productId, $quantity)->value,
            ];
        }

        $originVisitId = $this->validated('origin_visit_id');

        return [
            'customer_id' => (int) $this->validated('customer_id'),
            'origin_visit_id' => $originVisitId ? (int) $originVisitId : null,
            'notes_ar' => $this->validated('notes_ar'),
            'lines' => $lines,
        ];
    }
}
