<?php

namespace App\Http\Requests\SalesRequests;

use App\Models\Customer;
use App\Models\SalesRequest;
use App\Support\SalesRequests\LineAvailabilityResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SalesRequest $salesRequest */
        $salesRequest = $this->route('sales_request');

        if (! $this->user()?->can('update', $salesRequest)) {
            return false;
        }

        $customerId = (int) $this->input('customer_id');
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
            'notes_ar' => 'ملاحظات',
            'lines' => 'البنود',
            'lines.*.product_id' => 'المنتج',
            'lines.*.quantity' => 'الكمية',
            'lines.*.availability' => 'التوفر',
        ];
    }

    /**
     * @return array{
     *     customer_id: int,
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

        return [
            'customer_id' => (int) $this->validated('customer_id'),
            'notes_ar' => $this->validated('notes_ar'),
            'lines' => $lines,
        ];
    }
}
