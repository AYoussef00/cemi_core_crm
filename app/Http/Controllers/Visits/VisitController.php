<?php

namespace App\Http\Controllers\Visits;

use App\Http\Controllers\Controller;
use App\Http\Requests\Visits\CompleteVisitRequest;
use App\Http\Requests\Visits\StoreVisitRequest;
use App\Http\Requests\Visits\UpdateVisitRequest;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\SalesRequest;
use App\Models\User;
use App\Support\Visits\VisitStatus;
use App\Support\Visits\VisitWorkflow;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VisitController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', CustomerVisit::class);

        /** @var User $user */
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $visits = CustomerVisit::query()
            ->visibleTo($user)
            ->search($q !== '' ? $q : null)
            ->when(
                $status !== '' && in_array($status, VisitStatus::values(), true),
                fn ($query) => $query->where('status', $status)
            )
            ->with(['customer', 'assignedUser'])
            ->orderByDesc('scheduled_at')
            ->paginate(20)
            ->withQueryString();

        return view('visits.index', [
            'visits' => $visits,
            'q' => $q,
            'statusFilter' => $status,
            'statuses' => VisitStatus::cases(),
            'canCreate' => $user->can('create', CustomerVisit::class),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', CustomerVisit::class);

        /** @var User $user */
        $user = $request->user();
        $preselectCustomerId = $request->integer('customer_id') ?: null;

        return view('visits.create', [
            'customers' => Customer::query()
                ->visibleTo($user)
                ->with('assignedSalesRep')
                ->orderBy('name_ar')
                ->get(),
            'preselectCustomerId' => $preselectCustomerId,
        ]);
    }

    public function store(StoreVisitRequest $request, VisitWorkflow $workflow): RedirectResponse
    {
        $visit = $workflow->schedule($request->user(), $request->visitPayload());

        return redirect()
            ->route('visits.show', $visit)
            ->with('status_ar', 'تم جدولة الزيارة.');
    }

    public function show(CustomerVisit $visit): View
    {
        $this->authorize('view', $visit);

        $visit->load([
            'customer.assignedSalesRep',
            'assignedUser',
            'createdBy',
            'originatedSalesRequests' => fn ($q) => $q->latest('id'),
        ]);

        $user = request()->user();
        $customer = $visit->customer;

        return view('visits.show', [
            'visit' => $visit,
            'canUpdate' => $user?->can('update', $visit) ?? false,
            'canComplete' => $user?->can('complete', $visit) ?? false,
            'canCancel' => $user?->can('cancel', $visit) ?? false,
            'canCreateSalesRequest' => $customer !== null
                && ($user?->can('createForCustomer', [SalesRequest::class, $customer]) ?? false),
            'timezone' => config('app.timezone'),
        ]);
    }

    public function edit(CustomerVisit $visit): View
    {
        $this->authorize('update', $visit);

        return view('visits.edit', [
            'visit' => $visit->load(['customer.assignedSalesRep', 'assignedUser']),
            'timezone' => config('app.timezone'),
        ]);
    }

    public function update(
        UpdateVisitRequest $request,
        CustomerVisit $visit,
        VisitWorkflow $workflow,
    ): RedirectResponse {
        try {
            $workflow->reschedule($visit, $request->user(), $request->visitPayload());
        } catch (DomainException $e) {
            return back()->withErrors(['scheduled_at' => $e->getMessage()]);
        }

        return redirect()
            ->route('visits.show', $visit)
            ->with('status_ar', 'تم تحديث موعد الزيارة.');
    }

    public function complete(
        CompleteVisitRequest $request,
        CustomerVisit $visit,
        VisitWorkflow $workflow,
    ): RedirectResponse {
        try {
            $workflow->complete($visit, $request->user(), $request->validated());
        } catch (DomainException $e) {
            return back()->withErrors(['outcome' => $e->getMessage()]);
        }

        return redirect()
            ->route('visits.show', $visit)
            ->withFragment('visit-next')
            ->with('status_ar', 'تم تسجيل إكمال الزيارة. يمكنك إنشاء طلب بيع لهذه المنشأة إن لزم الأمر.');
    }

    public function cancel(Request $request, CustomerVisit $visit, VisitWorkflow $workflow): RedirectResponse
    {
        $this->authorize('cancel', $visit);

        try {
            $workflow->cancel($visit, $request->user(), $request->input('notes'));
        } catch (DomainException $e) {
            return back()->withErrors(['notes' => $e->getMessage()]);
        }

        return redirect()
            ->route('visits.show', $visit)
            ->with('status_ar', 'تم إلغاء الزيارة.');
    }
}
