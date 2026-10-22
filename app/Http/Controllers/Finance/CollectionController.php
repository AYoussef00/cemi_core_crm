<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\CollectionReversal;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Finance\CollectionWorkflow;
use App\Support\Finance\InvoiceStatus;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CollectionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Collection::class);

        /** @var User $user */
        $user = $request->user();

        $collections = Collection::query()
            ->visibleTo($user)
            ->with(['customer', 'createdBy', 'allocations'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('collections.index', [
            'collections' => $collections,
            'canCreate' => $user->can('create', Collection::class),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Collection::class);

        $customers = Customer::query()->orderBy('name_ar')->get(['id', 'name_ar']);
        $customerId = (int) $request->query('customer_id', 0);

        $invoices = $customerId > 0
            ? Invoice::query()
                ->where('customer_id', $customerId)
                ->where('status', InvoiceStatus::Issued->value)
                ->with('customer')
                ->latest('id')
                ->get()
                ->filter(fn (Invoice $i) => ! $i->isFullyPaid())
            : collect();

        return view('collections.create', [
            'customers' => $customers,
            'selectedCustomerId' => $customerId > 0 ? $customerId : null,
            'invoices' => $invoices,
        ]);
    }

    public function store(Request $request, CollectionWorkflow $workflow): RedirectResponse
    {
        $this->authorize('create', Collection::class);

        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'notes_ar' => ['nullable', 'string', 'max:2000'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
        ]);

        $allocations = [];
        foreach ($validated['allocations'] as $row) {
            if ((float) $row['amount'] <= 0) {
                continue;
            }
            $allocations[] = [
                'invoice_id' => (int) $row['invoice_id'],
                'amount' => number_format((float) $row['amount'], 2, '.', ''),
            ];
        }

        try {
            $collection = $workflow->createDraft($request->user(), [
                'customer_id' => (int) $validated['customer_id'],
                'notes_ar' => $validated['notes_ar'] ?? null,
                'allocations' => $allocations,
            ]);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['collection' => $e->getMessage()]);
        }

        return redirect()
            ->route('collections.show', $collection)
            ->with('status_ar', 'تم حفظ مسودة التحصيل — التأكيد يخفض المتبقي (CR7).');
    }

    public function show(Request $request, Collection $collection): View
    {
        $this->authorize('view', $collection);

        $collection->load([
            'customer',
            'allocations.invoice',
            'createdBy',
            'confirmedBy',
            'reversedBy',
            'reversal.requestedBy',
            'reversal.decidedBy',
        ]);

        /** @var User $user */
        $user = $request->user();

        return view('collections.show', [
            'collection' => $collection,
            'canConfirm' => $user->can('confirm', $collection),
            'canRequestReversal' => $user->can('requestReversal', $collection),
            'canDecideReversal' => $user->can('decideReversal', Collection::class)
                && $collection->reversal?->status->value === 'pending',
        ]);
    }

    public function confirm(
        Request $request,
        Collection $collection,
        CollectionWorkflow $workflow,
    ): RedirectResponse {
        $this->authorize('confirm', $collection);

        try {
            $workflow->confirm($collection, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['collection' => $e->getMessage()]);
        }

        return redirect()
            ->route('collections.show', $collection)
            ->with('status_ar', 'تم تأكيد التحصيل — خُفّض المتبقي غير المسدد.');
    }

    public function requestReversal(
        Request $request,
        Collection $collection,
        CollectionWorkflow $workflow,
    ): RedirectResponse {
        $this->authorize('requestReversal', $collection);

        try {
            $workflow->requestReversal($collection, $request->user(), $request->input('reason_ar'));
        } catch (DomainException $e) {
            return back()->withErrors(['collection' => $e->getMessage()]);
        }

        return back()->with('status_ar', 'تم إرسال طلب عكس التحصيل لمدير المبيعات.');
    }

    public function decideReversal(
        Request $request,
        Collection $collection,
        CollectionWorkflow $workflow,
    ): RedirectResponse {
        $this->authorize('decideReversal', Collection::class);

        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'decision_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $reversal = CollectionReversal::query()
            ->where('collection_id', $collection->id)
            ->where('status', 'pending')
            ->latest('id')
            ->firstOrFail();

        try {
            $workflow->decideReversal(
                $reversal,
                $request->user(),
                $validated['decision'],
                $validated['decision_notes'] ?? null,
            );
        } catch (DomainException $e) {
            return back()->withErrors(['collection' => $e->getMessage()]);
        }

        return back()->with('status_ar', 'تم تسجيل قرار عكس التحصيل.');
    }
}
