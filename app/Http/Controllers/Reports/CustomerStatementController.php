<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Support\Reports\ArReportQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerStatementController extends Controller
{
    public function index(Request $request, ArReportQuery $query): View
    {
        $this->authorizeStatement($request->user());

        /** @var User $user */
        $user = $request->user();

        return view('reports.statements.index', [
            'customers' => $query->customersVisibleTo($user)->paginate(30)->withQueryString(),
            'timezone' => (string) config('app.timezone'),
        ]);
    }

    public function show(Request $request, Customer $customer, ArReportQuery $query): View
    {
        $this->authorizeStatement($request->user());

        /** @var User $user */
        $user = $request->user();
        $statement = $query->statementFor($user, $customer);

        return view('reports.statements.show', [
            'customer' => $statement['customer'],
            'invoices' => $statement['invoices'],
            'unpaidTotal' => $statement['unpaid_total'],
            'timezone' => (string) config('app.timezone'),
        ]);
    }

    private function authorizeStatement(?User $user): void
    {
        abort_unless(
            $user
            && ($user->can('reports.ar') || $user->can('reports.financial') || $user->can('invoices.view')),
            403
        );
    }
}
