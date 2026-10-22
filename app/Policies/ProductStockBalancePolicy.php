<?php

namespace App\Policies;

use App\Models\ProductStockBalance;
use App\Models\User;

class ProductStockBalancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stock.view');
    }

    public function view(User $user, ProductStockBalance $balance): bool
    {
        return $user->can('stock.view');
    }
}
