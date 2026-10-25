<?php

namespace App\Support\SalesRequests;

use App\Models\CustomerVisit;
use App\Models\User;
use App\Support\Visits\VisitStatus;
use DomainException;
use Illuminate\Http\Request;

/**
 * Optional visit → sales-request handoff. Completing a visit never creates a request.
 */
class OriginVisitResolver
{
    public function fromCreateQuery(Request $request, User $user): ?CustomerVisit
    {
        $visitId = $request->integer('visit_id');
        if ($visitId <= 0) {
            return null;
        }

        $visit = CustomerVisit::query()->with('customer')->findOrFail($visitId);

        abort_unless($user->can('view', $visit), 403);

        if ($visit->status !== VisitStatus::Completed) {
            return null;
        }

        return $visit;
    }

    /**
     * @throws DomainException
     */
    public function attachable(?int $visitId, int $customerId, User $user): ?CustomerVisit
    {
        if ($visitId === null || $visitId <= 0) {
            return null;
        }

        $visit = CustomerVisit::query()->find($visitId);
        if ($visit === null) {
            throw new DomainException('الزيارة غير موجودة.');
        }

        if (! $user->can('view', $visit)) {
            throw new DomainException('لا يمكن ربط هذه الزيارة بطلب البيع.');
        }

        if ($visit->status !== VisitStatus::Completed) {
            throw new DomainException('يُربط طلب البيع بزيارة مكتملة فقط. الزيارة ليست شرطًا لإنشاء الطلب.');
        }

        if ((int) $visit->customer_id !== $customerId) {
            throw new DomainException('الزيارة لا تخص هذه المنشأة.');
        }

        return $visit;
    }
}
