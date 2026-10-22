<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Reports\ArReportQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ArReportController extends Controller
{
    public function index(Request $request, ArReportQuery $query): View
    {
        $this->authorizeAr($request->user());

        /** @var User $user */
        $user = $request->user();
        $filter = (string) $request->query('filter', '');
        $customerId = $request->integer('customer_id') ?: null;

        $summary = $query->summaryFor($user, $customerId);

        $rows = $summary['rows'];
        if ($filter === 'overdue') {
            $rows = array_values(array_filter($rows, fn (array $row): bool => $row['overdue']));
        }

        return view('reports.ar.index', [
            'overdueCount' => $summary['overdue_count'],
            'unpaidCount' => $summary['unpaid_count'],
            'unpaidTotal' => $summary['unpaid_total'],
            'rows' => $rows,
            'filter' => $filter,
            'customerId' => $customerId,
            'customers' => $query->customersVisibleTo($user)->limit(200)->get(),
            'timezone' => (string) config('app.timezone'),
        ]);
    }

    private function authorizeAr(?User $user): void
    {
        abort_unless(
            $user
            && ($user->can('reports.ar') || $user->can('reports.financial') || $user->can('invoices.view')),
            403
        );
    }
}
