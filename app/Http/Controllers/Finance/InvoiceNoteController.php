<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceNote;
use App\Models\User;
use App\Support\Finance\InvoiceNoteType;
use App\Support\Finance\InvoiceNoteWorkflow;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InvoiceNoteController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', InvoiceNote::class);

        /** @var User $user */
        $user = $request->user();

        $notes = InvoiceNote::query()
            ->visibleTo($user)
            ->with(['invoice', 'customer', 'createdBy'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('invoice-notes.index', [
            'notes' => $notes,
            'canCreate' => $user->can('create', InvoiceNote::class),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', InvoiceNote::class);

        $invoiceId = (int) $request->query('invoice_id', 0);
        $invoices = Invoice::query()->with('customer')->latest('id')->limit(100)->get();

        return view('invoice-notes.create', [
            'invoices' => $invoices,
            'selectedInvoiceId' => $invoiceId > 0 ? $invoiceId : null,
            'types' => InvoiceNoteType::cases(),
        ]);
    }

    public function store(Request $request, InvoiceNoteWorkflow $workflow): RedirectResponse
    {
        $this->authorize('create', InvoiceNote::class);

        $validated = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'type' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'reason_ar' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $note = $workflow->createDraft($request->user(), [
                'invoice_id' => (int) $validated['invoice_id'],
                'type' => $validated['type'],
                'amount' => number_format((float) $validated['amount'], 2, '.', ''),
                'reason_ar' => $validated['reason_ar'] ?? null,
            ]);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['note' => $e->getMessage()]);
        }

        return redirect()
            ->route('invoice-notes.show', $note)
            ->with('status_ar', 'تم إنشاء مسودة الإشعار — أرسل للاعتماد.');
    }

    public function show(Request $request, InvoiceNote $invoice_note): View
    {
        $this->authorize('view', $invoice_note);

        $invoice_note->load(['invoice', 'customer', 'createdBy', 'decidedBy']);

        /** @var User $user */
        $user = $request->user();

        return view('invoice-notes.show', [
            'note' => $invoice_note,
            'canSubmit' => $user->can('submit', $invoice_note),
            'canDecide' => $user->can('decide', $invoice_note),
        ]);
    }

    public function submit(
        Request $request,
        InvoiceNote $invoice_note,
        InvoiceNoteWorkflow $workflow,
    ): RedirectResponse {
        $this->authorize('submit', $invoice_note);

        try {
            $workflow->submit($invoice_note, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['note' => $e->getMessage()]);
        }

        return back()->with('status_ar', 'تم إرسال الإشعار للاعتماد.');
    }

    public function decide(
        Request $request,
        InvoiceNote $invoice_note,
        InvoiceNoteWorkflow $workflow,
    ): RedirectResponse {
        $this->authorize('decide', $invoice_note);

        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'decision_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $workflow->decide(
                $invoice_note,
                $request->user(),
                $validated['decision'],
                $validated['decision_notes'] ?? null,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['note' => $e->getMessage()]);
        }

        return back()->with('status_ar', 'تم تسجيل قرار الإشعار.');
    }
}
