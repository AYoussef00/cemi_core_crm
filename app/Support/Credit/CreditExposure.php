<?php

namespace App\Support\Credit;

use App\Models\Customer;
use App\Models\Invoice;
use App\Support\Finance\InvoiceStatus;
use App\Support\Money\Money;

/**
 * Credit exposure = sum of unpaid issued invoice balances (CR1 / CR7).
 */
class CreditExposure
{
    public function forCustomer(Customer $customer): string
    {
        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->where('status', InvoiceStatus::Issued->value)
            ->get();

        $sum = 0.0;
        foreach ($invoices as $invoice) {
            $unpaid = (float) $invoice->unpaidBalance();
            if ($unpaid > 0) {
                $sum += $unpaid;
            }
        }

        return Money::round2($sum);
    }

    /**
     * Block new SO approval when exposure >= approved credit limit.
     * No approved limit configured → allow (limit not yet set).
     */
    public function allowsNewOrder(Customer $customer): bool
    {
        $limit = $customer->approved_credit_limit;
        if ($limit === null || $limit === '') {
            return true;
        }

        return (float) $this->forCustomer($customer) < (float) $limit;
    }

    public function denyMessageAr(Customer $customer): string
    {
        return 'لا يمكن اعتماد أمر بيع جديد: التعرض الائتماني بلغ حد الائتمان المعتمد أو تجاوزه للمنشأة «'
            .($customer->name_ar ?? '#')
            .'» (التعرض '.$this->forCustomer($customer).' ر.س).';
    }
}
