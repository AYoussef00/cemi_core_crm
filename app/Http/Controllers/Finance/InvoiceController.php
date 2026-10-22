<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Finance\InvoiceWorkflow;
use App\Support\Finance\OverdueInvoiceReminder;
use App\Support\Warehouse\DeliveryStatus;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Invoice::class);

        /** @var User $user */
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));

        $invoices = Invoice::query()
            ->visibleTo($user)
            ->search($q !== '' ? $q : null)
            ->with(['customer', 'delivery', 'deal', 'issuedBy'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('invoices.index', [
            'invoices' => $invoices,
            'q' => $q,
            'canIssue' => $user->can('issue', Invoice::class),
            'canViewPrices' => $user->can('prices.view'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('issue', Invoice::class);

        $deliveries = Delivery::query()
            ->where('status', DeliveryStatus::Confirmed)
            ->whereDoesntHave('invoice')
            ->with(['customer', 'salesOrder', 'lines'])
            ->latest('id')
            ->get();

        return view('invoices.create', [
            'deliveries' => $deliveries,
            'selectedId' => (int) $request->query('delivery_id', 0) ?: null,
        ]);
    }

    public function store(Request $request, InvoiceWorkflow $workflow): RedirectResponse
    {
        $this->authorize('issue', Invoice::class);

        $request->validate([
            'delivery_id' => ['required', 'integer', 'exists:deliveries,id'],
            'notes_ar' => ['nullable', 'string', 'max:2000'],
        ]);

        $delivery = Delivery::query()->findOrFail((int) $request->input('delivery_id'));
        $this->authorize('issueFromDelivery', [Invoice::class, $delivery]);

        try {
            $invoice = $workflow->issueFromDelivery(
                $delivery,
                $request->user(),
                $request->input('notes_ar'),
            );
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['invoice' => $e->getMessage()]);
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('status_ar', 'تم إصدار الفاتورة للتسليم المؤكد.');
    }

    public function show(Request $request, Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        $invoice->load([
            'customer',
            'delivery',
            'deal',
            'salesOrder',
            'lines.product',
            'issuedBy',
            'notes',
            'allocations.collection',
        ]);

        /** @var User $user */
        $user = $request->user();

        return view('invoices.show', [
            'invoice' => $invoice,
            'unpaid' => $invoice->unpaidBalance(),
            'canResendOverdue' => $user->can('overdue.resend') && $invoice->isOverdue(),
            'canViewPrices' => $user->can('prices.view'),
            'canCreateNote' => $user->can('credit_notes.create'),
            'canCloseDeal' => $user->can('close', $invoice->deal) && $invoice->deal?->allInvoicesFullyPaid(),
        ]);
    }

    public function print(Request $request, Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        $invoice->load(['customer', 'lines.product', 'delivery', 'deal']);

        return view('invoices.print', [
            'invoice' => $invoice,
            'company' => Company::current(),
            'unpaid' => $invoice->unpaidBalance(),
        ]);
    }

    public function resendOverdue(
        Request $request,
        Invoice $invoice,
        OverdueInvoiceReminder $reminder,
    ): RedirectResponse {
        $this->authorize('view', $invoice);
        abort_unless($request->user()->can('overdue.resend'), 403);

        if (! $reminder->sendOne($invoice, $request->user())) {
            return back()->withErrors(['overdue' => 'تعذر إرسال التذكير (تحقق من التأخر وبريد جهة الاتصال).']);
        }

        return back()->with('status_ar', 'تم إرسال تذكير التأخر (اختبار بـ Mail::fake؛ CM1b للإرسال الحي).');
    }
}
