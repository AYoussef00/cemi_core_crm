<?php

namespace App\Http\Controllers\Quotations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quotations\ConvertQuotationToSalesOrderRequest;
use App\Http\Requests\Quotations\DecideQuotationRequest;
use App\Http\Requests\Quotations\UpdateQuotationDraftRequest;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\SalesRequest;
use App\Models\User;
use App\Support\Attachments\AttachmentService;
use App\Support\Procurement\SupplierPurchaseOrderWorkflow;
use App\Support\Quotations\QuotationPdfRenderer;
use App\Support\Quotations\QuotationStatus;
use App\Support\Quotations\QuotationWorkflow;
use App\Support\Sales\CustomerPurchaseOrderWorkflow;
use App\Support\Sales\SalesOrderWorkflow;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class QuotationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Quotation::class);

        /** @var User $user */
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $quotations = Quotation::query()
            ->visibleTo($user)
            ->search($q !== '' ? $q : null)
            ->status($status !== '' ? $status : null)
            ->with(['customer', 'createdBy', 'salesRequest', 'lines'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $convertId = (int) $request->query('convert', session('open_convert_quotation_id', 0));
        $convertQuotation = null;
        if ($convertId > 0) {
            $convertQuotation = Quotation::query()
                ->visibleTo($user)
                ->with('customer')
                ->find($convertId);
        }

        return view('quotations.index', [
            'quotations' => $quotations,
            'q' => $q,
            'status' => $status,
            'statuses' => QuotationStatus::cases(),
            'convertQuotation' => $convertQuotation,
        ]);
    }

    public function storeFromSalesRequest(
        Request $request,
        SalesRequest $salesRequest,
        QuotationWorkflow $workflow,
    ): RedirectResponse {
        $this->authorize('createFromSalesRequest', [Quotation::class, $salesRequest]);

        try {
            $quotation = $workflow->createFromSalesRequest(
                $salesRequest,
                $request->user(),
                $request->input('notes_ar'),
                $request->input('expires_at'),
            );
        } catch (DomainException $e) {
            return back()->withErrors(['quotation' => $e->getMessage()]);
        }

        $user = $request->user();
        $canPrice = $user->can('update', $quotation);

        return redirect()
            ->route('quotations.show', $quotation)
            ->with(
                'status_ar',
                $canPrice
                    ? 'تم إنشاء المسودة — ضع هامش الربح في هذه الصفحة ثم اعتمد ليصل للمندوب.'
                    : 'تم إنشاء مسودة عرض السعر وإشعار مدير المبيعات لوضع الهامش.',
            );
    }

    public function show(Request $request, Quotation $quotation): View
    {
        $this->authorize('view', $quotation);

        $quotation->load([
            'customer.contacts',
            'lines.product',
            'createdBy',
            'submittedBy',
            'decidedBy',
            'salesRequest',
            'sendLogs',
            'supersedes',
        ]);

        /** @var User $user */
        $user = $request->user();

        return view('quotations.show', [
            'quotation' => $quotation,
            'company' => Company::current(),
            'canUpdate' => $user->can('update', $quotation),
            'canSubmit' => $user->can('submitPrice', $quotation),
            'canApprove' => $user->can('approve', $quotation),
            'canReject' => $user->can('reject', $quotation),
            'canSend' => $user->can('send', $quotation),
            'canCancel' => $user->can('cancel', $quotation),
            'canSupersede' => $user->can('supersede', $quotation),
            'canPrint' => $user->can('print', $quotation),
            'canConvert' => $user->can('convertToSalesOrder', $quotation),
            'recipientPreview' => app(QuotationWorkflow::class)->resolveRecipientEmail($quotation->customer),
        ]);
    }

    public function edit(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('update', $quotation);

        return redirect()
            ->route('quotations.show', $quotation)
            ->withFragment('quote-pricing');
    }

    public function update(
        UpdateQuotationDraftRequest $request,
        Quotation $quotation,
        QuotationWorkflow $workflow,
    ): RedirectResponse {
        $payload = $request->payload();

        try {
            $workflow->updateDraftMargins(
                $quotation,
                $request->user(),
                $payload['lines'],
                $payload['notes_ar'],
                $payload['expires_at'],
            );
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['lines' => $e->getMessage()]);
        }

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('status_ar', 'تم حفظ هامش المدير والأسعار الشاملة للضريبة — اعتمد لإتاحة العرض للمندوب.');
    }

    public function submitPrice(Request $request, Quotation $quotation, QuotationWorkflow $workflow): RedirectResponse
    {
        $this->authorize('submitPrice', $quotation);

        try {
            $workflow->submitPrice($quotation, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('status_ar', 'أُرسل العرض لاعتماد السعر والهامش.');
    }

    public function decide(
        DecideQuotationRequest $request,
        Quotation $quotation,
        QuotationWorkflow $workflow,
    ): RedirectResponse {
        try {
            if ($request->validated('decision') === 'approve') {
                $workflow->approve($quotation, $request->user(), $request->validated('decision_notes'));
                $message = 'تم اعتماد التسعير وإتاحة العرض للمندوب (شامل ضريبة 15%).';
            } else {
                $workflow->reject($quotation, $request->user(), $request->validated('decision_notes'));
                $message = 'رُفض الاعتماد وأُعيد العرض إلى مسودة.';
            }
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['decision' => $e->getMessage()]);
        }

        return redirect()->route('quotations.show', $quotation)->with('status_ar', $message);
    }

    public function send(Request $request, Quotation $quotation, QuotationWorkflow $workflow): RedirectResponse
    {
        $this->authorize('send', $quotation);

        try {
            $workflow->send($quotation, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['send' => $e->getMessage()]);
        }

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('status_ar', 'تم إرسال عرض السعر بالبريد.');
    }

    public function cancel(Request $request, Quotation $quotation, QuotationWorkflow $workflow): RedirectResponse
    {
        $this->authorize('cancel', $quotation);

        try {
            $workflow->cancel($quotation, $request->user(), $request->input('decision_notes'));
        } catch (DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('quotations.show', $quotation)->with('status_ar', 'تم إلغاء عرض السعر.');
    }

    public function supersede(Request $request, Quotation $quotation, QuotationWorkflow $workflow): RedirectResponse
    {
        $this->authorize('supersede', $quotation);

        try {
            $new = $workflow->supersede($quotation, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()
            ->route('quotations.show', $new)
            ->withFragment('quote-pricing')
            ->with('status_ar', 'تم إنشاء نسخة جديدة واستبدال العرض السابق — ضع الهامش هنا ثم اعتمد.');
    }

    public function print(Request $request, Quotation $quotation, QuotationPdfRenderer $pdf): Response
    {
        $this->authorize('print', $quotation);

        return $pdf->stream($quotation, Company::current());
    }

    public function convertForm(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('convertToSalesOrder', $quotation);

        return redirect()
            ->route('quotations.index', ['convert' => $quotation->id])
            ->with('open_convert_quotation_id', $quotation->id);
    }

    public function convertStore(
        ConvertQuotationToSalesOrderRequest $request,
        Quotation $quotation,
        CustomerPurchaseOrderWorkflow $poWorkflow,
        SalesOrderWorkflow $soWorkflow,
        AttachmentService $attachments,
        SupplierPurchaseOrderWorkflow $supplierPos,
    ): RedirectResponse {
        $this->authorize('convertToSalesOrder', $quotation);
        $quotation->loadMissing(['lines', 'customer']);

        /** @var User $user */
        $user = $request->user();

        try {
            $order = DB::transaction(function () use ($request, $quotation, $user, $poWorkflow, $soWorkflow, $attachments) {
                $poLines = $quotation->lines->map(fn ($line) => [
                    'quotation_line_id' => $line->id,
                    'quantity' => number_format((float) $line->quantity, 3, '.', ''),
                ])->values()->all();

                $po = $poWorkflow->create($user, [
                    'customer_id' => $quotation->customer_id,
                    'quotation_id' => $quotation->id,
                    'reference_ar' => $request->validated('reference_ar'),
                    'notes_ar' => $request->validated('notes_ar'),
                    'lines' => $poLines,
                ]);

                $attachments->store($po, $request->file('customer_po_pdf'), $user);

                $po->load('lines');
                $soLines = $po->lines->map(fn ($line) => [
                    'customer_purchase_order_line_id' => $line->id,
                    'quantity' => number_format((float) $line->quantity, 3, '.', ''),
                ])->values()->all();

                return $soWorkflow->createDraft($user, [
                    'customer_purchase_order_id' => $po->id,
                    'notes_ar' => $request->validated('notes_ar'),
                    'lines' => $soLines,
                ]);
            });
        } catch (DomainException $e) {
            return redirect()
                ->route('quotations.index', ['convert' => $quotation->id])
                ->withInput()
                ->withErrors(['convert' => $e->getMessage()])
                ->with('open_convert_quotation_id', $quotation->id);
        }

        // PROC-BUY-ON-CPO: procurement SPO only after customer PO + SO draft exist.
        $spo = $supplierPos->createFromCustomerPoConversion($quotation->fresh(), $order->fresh(), $user);

        if ($spo !== null) {
            return redirect()
                ->route('sales-orders.show', $order)
                ->with(
                    'status_ar',
                    'تم رفع أمر شراء العميل وإنشاء أمر البيع #'.$order->id
                    .' وأُرسل أمر المورد #'.$spo->id.' للمشتريات مباشرة — بدون اعتماد مدير المبيعات الآن.',
                );
        }

        return redirect()
            ->route('sales-orders.show', $order)
            ->with('status_ar', 'تم رفع أمر شراء العميل وإنشاء مسودة أمر البيع #'.$order->id.'.');
    }
}
