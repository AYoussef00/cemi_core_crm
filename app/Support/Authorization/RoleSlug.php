<?php

namespace App\Support\Authorization;

enum RoleSlug: string
{
    case SalesRepresentative = 'sales_representative';
    case SalesManager = 'sales_manager';
    case Procurement = 'procurement';
    case Warehouse = 'warehouse';
    case Accounts = 'accounts';
    case Executive = 'executive';
    case Administrator = 'administrator';

    public function labelAr(): string
    {
        return (string) (config('authorization.roles.'.$this->value.'.name_ar') ?? $this->value);
    }
}
