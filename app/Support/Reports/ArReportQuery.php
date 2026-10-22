<?php

namespace App\Support\Reports;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Authorization\RecordScope;
use App\Support\Finance\InvoiceStatus;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * AR figures from issued invoices only; unpaid via CR7 (Invoice::unpaidBalance).
 */
final class ArReportQuery
{
    public function customersVisibleTo(User $user): Builder
    {
        $query = Customer::query()->orderBy('name_ar');

        return $this->scopeCustomers($query, $user);
    }

    /**
     * @return array{
     *     overdue_count: int,
     *     unpaid_count: int,
     *     unpaid_total: string,
     *     rows: list<array{invoice: Invoice, unpaid: string, overdue: bool}>
     * }
     */
    public function summaryFor(User $user, ?int $customerId = null): array
    {
        $invoices = Invoice::query()
            ->visibleTo($user)
            ->where('status', InvoiceStatus::Issued->value)
            ->when($customerId, fn (Builder $q) => $q->where('customer_id', $customerId))
            ->with(['customer'])
            ->latest('id')
            ->get();

        $rows = [];
        $overdueCount = 0;
        $unpaidCount = 0;
        $unpaidTotal = 0.0;

        foreach ($invoices as $invoice) {
            $unpaid = (float) $invoice->unpaidBalance();
            if (abs($unpaid) < 0.005) {
                continue;
            }

            $overdue = $invoice->isOverdue();
            if ($overdue) {
                $overdueCount++;
            }
            $unpaidCount++;
            $unpaidTotal += $unpaid;

            $rows[] = [
                'invoice' => $invoice,
                'unpaid' => Money::round2($unpaid),
                'overdue' => $overdue,
            ];
        }

        return [
            'overdue_count' => $overdueCount,
            'unpaid_count' => $unpaidCount,
            'unpaid_total' => Money::round2($unpaidTotal),
            'rows' => $rows,
        ];
    }

    /**
     * @return array{
     *     customer: Customer,
     *     invoices: Collection<int, array{invoice: Invoice, unpaid: string, overdue: bool}>,
     *     unpaid_total: string
     * }
     */
    public function statementFor(User $user, Customer $customer): array
    {
        abort_unless($this->customersVisibleTo($user)->whereKey($customer->id)->exists(), 403);

        $invoices = Invoice::query()
            ->visibleTo($user)
            ->where('customer_id', $customer->id)
            ->where('status', InvoiceStatus::Issued->value)
            ->with(['customer'])
            ->latest('issued_at')
            ->latest('id')
            ->get();

        $rows = [];
        $unpaidTotal = 0.0;

        foreach ($invoices as $invoice) {
            $unpaid = (float) $invoice->unpaidBalance();
            $unpaidTotal += $unpaid;
            $rows[] = [
                'invoice' => $invoice,
                'unpaid' => Money::round2($unpaid),
                'overdue' => $invoice->isOverdue(),
            ];
        }

        return [
            'customer' => $customer,
            'invoices' => collect($rows),
            'unpaid_total' => Money::round2($unpaidTotal),
        ];
    }

    private function scopeCustomers(Builder $query, User $user): Builder
    {
        $scope = $user->permissionScope('reports.ar')
            ?? $user->permissionScope('reports.financial')
            ?? $user->permissionScope('invoices.view');

        if ($scope === null && ! $user->can('reports.ar') && ! $user->can('reports.financial') && ! $user->can('invoices.view')) {
            return $query->whereRaw('0 = 1');
        }

        return match ($scope) {
            RecordScope::Own, RecordScope::Cust, RecordScope::Assigned => $query->where('assigned_sales_rep_id', $user->id),
            RecordScope::All, RecordScope::Admin => $query,
            default => $query->whereRaw('0 = 1'),
        };
    }
}
