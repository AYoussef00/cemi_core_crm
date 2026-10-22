<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\RecordSupplierCostRequest;
use App\Models\Attachment;
use App\Models\ProcurementTask;
use App\Models\User;
use App\Support\Attachments\AttachmentService;
use App\Support\Procurement\ProcurementTaskStatus;
use App\Support\SalesRequests\SalesRequestWorkflow;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProcurementTaskController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ProcurementTask::class);

        /** @var User $user */
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $tasks = ProcurementTask::query()
            ->visibleTo($user)
            ->search($q !== '' ? $q : null)
            ->status($status !== '' ? $status : null)
            ->with(['product.unit', 'salesRequest.customer', 'line'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('procurement.tasks.index', [
            'tasks' => $tasks,
            'q' => $q,
            'status' => $status,
            'statuses' => ProcurementTaskStatus::cases(),
            'canViewCosts' => $user->can('prices.view')
                || $user->can('procurement.manage')
                || $user->can('procurement.view'),
        ]);
    }

    public function show(Request $request, ProcurementTask $task): View
    {
        $this->authorize('view', $task);

        $task->load([
            'product.unit',
            'salesRequest.customer',
            'salesRequest.quotations' => fn ($q) => $q->latest('id'),
            'line',
            'recordedBy',
            'cancelledBy',
            'attachments',
        ]);

        /** @var User $user */
        $user = $request->user();
        $linkedQuotation = $task->salesRequest?->quotations->first();

        return view('procurement.tasks.show', [
            'task' => $task,
            'linkedQuotation' => $linkedQuotation,
            'canViewQuotation' => $linkedQuotation !== null && $user->can('view', $linkedQuotation),
            'canRecordCost' => $user->can('recordCost', $task),
            'canCancel' => $user->can('cancel', $task),
            'canViewCosts' => $user->can('prices.view')
                || $user->can('procurement.manage')
                || $user->can('procurement.view'),
            'supplierQuote' => $task->attachments->first(),
        ]);
    }

    public function recordCost(
        RecordSupplierCostRequest $request,
        ProcurementTask $task,
        SalesRequestWorkflow $workflow,
    ): RedirectResponse {
        try {
            $workflow->recordSupplierCost($task, $request->user(), $request->payload());
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['supplier_cost' => $e->getMessage()]);
        }

        return redirect()
            ->route('procurement.tasks.show', $task)
            ->with('status_ar', 'تم تسجيل تكلفة المورد مع عرض السعر — فُتح عرض سعر لمدير المبيعات لوضع الهامش.');
    }

    public function quotePdf(
        Request $request,
        ProcurementTask $task,
        Attachment $attachment,
        AttachmentService $attachments,
    ): StreamedResponse {
        $this->authorize('view', $task);
        $attachments->assertBelongsTo($attachment, $task);

        return $attachments->stream($attachment);
    }

    public function cancel(Request $request, ProcurementTask $task, SalesRequestWorkflow $workflow): RedirectResponse
    {
        $this->authorize('cancel', $task);

        try {
            $workflow->cancelProcurementTask($task, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()
            ->route('procurement.tasks.show', $task)
            ->with('status_ar', 'تم إلغاء مهمة المشتريات.');
    }
}
