<?php

namespace App\Models;

use App\Support\Customers\CustomerContactRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'role', 'name_ar', 'phone', 'email'])]
class CustomerContact extends Model
{
    protected function casts(): array
    {
        return [
            'role' => CustomerContactRole::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function roleLabelAr(): string
    {
        $role = $this->role;

        return $role instanceof CustomerContactRole
            ? $role->labelAr()
            : (string) $role;
    }
}
