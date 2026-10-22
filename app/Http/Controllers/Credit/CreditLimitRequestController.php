<?php

namespace App\Http\Controllers\Credit;

use App\Http\Controllers\Controller;
use App\Http\Requests\Credit\DecideCreditLimitRequestRequest;
use App\Http\Requests\Credit\ReviewCreditLimitRequestRequest;
use App\Models\CreditLimitRequest;
use App\Models\User;
use App\Support\Credit\CreditLimitRequestStatus;
use App\Support\Credit\CreditLimitWorkflow;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditLimitRequestController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', CreditLimitRequest::class);

        /** @var User $user */
        $user = $request->user();

        $query = CreditLimitRequest::query()
            ->with(['customer.assignedSalesRep', 'requestedBy'])
            ->latest('id');

        // Sales Rep / invoice viewers: only requests for customers in their view scope.
        // Accounts / Sales Manager: unscoped credit permissions — full company queue.
        if (! $user->can('credit_limit.review') && ! $user->can('credit_limit.approve')) {
            $query->whereHas('customer', fn ($q) => $q->visibleTo($user));
        }

        $filter = (string) $request->query('status', '');
        if ($filter !== '' && in_array($filter, CreditLimitRequestStatus::values(), true)) {
            $query->where('status', $filter);
        } elseif ($user->can('credit_limit.review') && ! $user->can('credit_limit.approve')) {
            $query->where('status', CreditLimitRequestStatus::PendingReview);
        } elseif ($user->can('credit_limit.approve') && ! $user->can('credit_limit.request')) {
            $query->where('status', CreditLimitRequestStatus::PendingFinal);
        }

        $requests = $query->paginate(20)->withQueryString();

        return view('credit.requests-index', [
            'requests' => $requests,
            'statusFilter' => $filter,
            'statuses' => CreditLimitRequestStatus::cases(),
        ]);
    }

    public function show(CreditLimitRequest $creditRequest): View
    {
        $this->authorize('view', $creditRequest);

        $creditRequest->load(['customer.assignedSalesRep', 'requestedBy', 'reviewedBy', 'decidedBy']);

        return view('credit.request-show', [
            'creditRequest' => $creditRequest,
            'canReview' => request()->user()?->can('review', $creditRequest) ?? false,
            'canDecide' => request()->user()?->can('decideFinal', $creditRequest) ?? false,
        ]);
    }

    public function review(
        ReviewCreditLimitRequestRequest $request,
        CreditLimitRequest $creditRequest,
        CreditLimitWorkflow $workflow,
    ): RedirectResponse {
        try {
            if ($request->validated('decision') === 'forward') {
                $workflow->forwardFromAccounts($creditRequest, $request->user(), $request->validated('notes'));
                $message = 'تمت إحالة الطلب للاعتماد النهائي لمدير المبيعات.';
            } else {
                $workflow->rejectAtReview($creditRequest, $request->user(), $request->validated('notes'));
                $message = 'تم رفض الطلب في مرحلة المراجعة.';
            }
        } catch (DomainException $e) {
            return back()->withErrors(['decision' => $e->getMessage()]);
        }

        return redirect()
            ->route('credit-requests.show', $creditRequest)
            ->with('status_ar', $message);
    }

    public function decide(
        DecideCreditLimitRequestRequest $request,
        CreditLimitRequest $creditRequest,
        CreditLimitWorkflow $workflow,
    ): RedirectResponse {
        try {
            if ($request->validated('decision') === 'approve') {
                $workflow->approveFinal($creditRequest, $request->user(), $request->validated('notes'));
                $message = 'تم الاعتماد النهائي وتفعيل حد الائتمان وشروط الدفع المعتمدة.';
            } else {
                $workflow->rejectFinal($creditRequest, $request->user(), $request->validated('notes'));
                $message = 'تم الرفض النهائي. القيم المعتمدة السابقة لم تتغير.';
            }
        } catch (DomainException $e) {
            return back()->withErrors(['decision' => $e->getMessage()]);
        }

        return redirect()
            ->route('credit-requests.show', $creditRequest)
            ->with('status_ar', $message);
    }
}
