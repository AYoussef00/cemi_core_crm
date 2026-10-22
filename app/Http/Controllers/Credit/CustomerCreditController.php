<?php

namespace App\Http\Controllers\Credit;

use App\Http\Controllers\Controller;
use App\Http\Requests\Credit\StoreCreditLimitRequestRequest;
use App\Models\CreditLimitRequest;
use App\Models\Customer;
use App\Support\Credit\CreditLimitWorkflow;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomerCreditController extends Controller
{
    public function show(Customer $customer): View
    {
        $this->authorize('viewProfile', [CreditLimitRequest::class, $customer]);

        $customer->load(['assignedSalesRep', 'creditLimitRequests' => fn ($q) => $q->with(['requestedBy', 'reviewedBy', 'decidedBy'])->limit(50)]);

        return view('credit.profile', [
            'customer' => $customer,
            'openRequest' => $customer->openCreditLimitRequest(),
            'canRequest' => request()->user()?->can('create', [CreditLimitRequest::class, $customer]) ?? false,
            // Exposure is not computed until invoices exist.
            'exposureDeferred' => true,
        ]);
    }

    public function store(
        StoreCreditLimitRequestRequest $request,
        Customer $customer,
        CreditLimitWorkflow $workflow,
    ): RedirectResponse {
        try {
            $creditRequest = $workflow->submitRequest($customer, $request->user(), $request->validated());
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['requested_credit_limit' => $e->getMessage()]);
        }

        return redirect()
            ->route('credit-requests.show', $creditRequest)
            ->with('status_ar', 'تم إرسال طلب حد الائتمان لمراجعة الحسابات.');
    }
}
