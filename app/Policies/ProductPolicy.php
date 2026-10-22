<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can('products.view');
    }

    public function create(User $user): bool
    {
        return $user->can('products.manage');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can('products.manage');
    }

    public function updatePurchaseCost(User $user, Product $product): bool
    {
        return $user->can('products.edit_purchase_cost');
    }

    public function viewPurchaseCost(User $user): bool
    {
        return $user->can('products.manage') || $user->can('products.edit_purchase_cost');
    }
}
