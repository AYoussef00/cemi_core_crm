<?php

namespace App\Support\Customers;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CustomerContactSync
{
    /**
     * Replace the customer's contacts with the submitted set (empty rows ignored).
     *
     * @param  list<array{role: string, name_ar: string, phone?: string|null, email?: string|null}>  $contacts
     * @return list<array{role: string, name_ar: string}>
     */
    public function sync(Customer $customer, array $contacts): array
    {
        $normalized = [];

        foreach ($contacts as $row) {
            $name = trim((string) ($row['name_ar'] ?? ''));
            $role = (string) ($row['role'] ?? '');

            if ($name === '' || $role === '') {
                continue;
            }

            $normalized[] = [
                'role' => $role,
                'name_ar' => $name,
                'phone' => $this->nullableString($row['phone'] ?? null),
                'email' => $this->nullableString($row['email'] ?? null),
            ];
        }

        DB::transaction(function () use ($customer, $normalized): void {
            $customer->contacts()->delete();

            foreach ($normalized as $row) {
                $customer->contacts()->create($row);
            }
        });

        return array_map(
            fn (array $row) => ['role' => $row['role'], 'name_ar' => $row['name_ar']],
            $normalized,
        );
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
