<?php

namespace App\Support\Dashboard;

use App\Models\CustomerPurchaseOrder;
use App\Models\CustomerVisit;
use App\Models\Deal;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\SalesRequest;
use App\Models\User;
use App\Support\Authorization\RecordScope;
use App\Support\Authorization\RoleSlug;
use App\Support\Quotations\QuotationStatus;
use App\Support\Sales\CustomerPurchaseOrderStatus;
use App\Support\Sales\DealStatus;
use App\Support\Sales\SalesOrderStatus;
use App\Support\SalesRequests\SalesRequestStatus;
use App\Support\Visits\VisitStatus;
use App\Support\Warehouse\FulfillmentStatus;
use App\Support\Warehouse\PreparationStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Home dashboard figures from authoritative, permission-scoped records only.
 * A module appears only when its table exists and the user may view it.
 * Zero is a real count and is shown; nothing is invented.
 */
final class HomeDashboardBuilder
{
    /**
     * @return array{
     *     greeting_name: string,
     *     role_label: string|null,
     *     scope_mode: 'own'|'all',
     *     scope_label: string,
     *     today: string,
     *     timezone: string,
     *     kpis: list<array<string, mixed>>,
     *     charts: list<array<string, mixed>>,
     *     is_empty: bool
     * }
     */
    public function for(User $user): array
    {
        $kpis = [];
        $charts = [];
        [$scopeMode, $scopeLabel] = $this->scopePresentation($user);

        if ($this->ready('sales_requests', 'sales_requests.view', $user)) {
            $base = SalesRequest::query()->visibleTo($user);
            $pendingStatuses = [
                SalesRequestStatus::AwaitingProcurement->value,
                SalesRequestStatus::ReadyForPricing->value,
            ];
            $pending = (clone $base)->whereIn('status', $pendingStatuses)->count();
            $readyPricing = (clone $base)
                ->where('status', SalesRequestStatus::ReadyForPricing->value)
                ->count();
            $total = (clone $base)->count();

            $kpis[] = [
                'key' => 'sales_requests',
                'label' => 'طلبات المبيعات',
                'value' => $total,
                'chips' => $this->chips([
                    [$pending, 'معلّقة', 'warning'],
                    [$readyPricing, 'جاهزة للتسعير', 'process'],
                ]),
                'href' => route('sales-requests.index'),
                'tone' => 'warm',
            ];
        }

        if ($this->ready('quotations', 'quotations.view', $user)) {
            $base = Quotation::query()->visibleTo($user);
            $counts = $this->statusCounts($base);
            $total = array_sum($counts);

            $pending = $counts[QuotationStatus::PendingPriceApproval->value] ?? 0;
            $awaiting = $counts[QuotationStatus::Sent->value] ?? 0;

            $kpis[] = [
                'key' => 'quotations',
                'label' => 'عروض الأسعار',
                'value' => $total,
                'chips' => $this->chips([
                    [$awaiting, 'بانتظار رد العميل', 'process'],
                    [$pending, 'بانتظار الاعتماد', 'warning'],
                ]),
                'href' => route('quotations.index'),
                'tone' => 'primary',
            ];

            $charts[] = $this->donut('عروض الأسعار حسب الحالة', 'عرض', $counts, [
                [QuotationStatus::Draft, 'neutral'],
                [QuotationStatus::PendingPriceApproval, 'warning'],
                [QuotationStatus::Approved, 'primary'],
                [QuotationStatus::Sent, 'process'],
                [QuotationStatus::Expired, 'danger'],
                [QuotationStatus::Cancelled, 'danger'],
                [QuotationStatus::Superseded, 'neutral'],
            ]);
        }

        if ($this->ready('customer_purchase_orders', 'customer_pos.view', $user)) {
            $base = CustomerPurchaseOrder::query()->visibleTo($user);
            $counts = $this->statusCounts($base);
            $open = $counts[CustomerPurchaseOrderStatus::Open->value] ?? 0;

            $kpis[] = [
                'key' => 'customer_pos',
                'label' => 'أوامر شراء العملاء',
                'value' => array_sum($counts),
                'chips' => $this->chips([
                    [$open, 'مفتوح', 'process'],
                ]),
                'href' => route('customer-pos.index'),
                'tone' => 'process',
            ];
        }

        if ($this->ready('sales_orders', 'sales_orders.view', $user)) {
            $base = SalesOrder::query()->visibleTo($user);
            $counts = $this->statusCounts($base);
            $pending = $counts[SalesOrderStatus::PendingApproval->value] ?? 0;
            $approved = $counts[SalesOrderStatus::Approved->value] ?? 0;
            $awaitingDelivery = (clone $base)
                ->where('status', SalesOrderStatus::Approved->value)
                ->where('fulfillment_status', '!=', FulfillmentStatus::FullyDelivered->value)
                ->count();

            $kpis[] = [
                'key' => 'sales_orders',
                'label' => 'أوامر البيع',
                'value' => array_sum($counts),
                'chips' => $this->chips([
                    [$approved, 'معتمد', 'success'],
                    [$pending, 'بانتظار الاعتماد', 'warning'],
                    [$awaitingDelivery, 'بانتظار التسليم', 'process'],
                ]),
                'href' => route('sales-orders.index'),
                'tone' => 'success',
            ];

            foreach ([
                [SalesOrderStatus::Draft, 'neutral'],
                [SalesOrderStatus::PendingApproval, 'warning'],
                [SalesOrderStatus::Approved, 'success'],
                [SalesOrderStatus::Rejected, 'danger'],
                [SalesOrderStatus::Cancelled, 'danger'],
            ] as [$status, $tone]) {
                $n = (int) ($counts[$status->value] ?? 0);
                if ($n <= 0) {
                    continue;
                }

                $kpis[] = [
                    'key' => 'sales_orders',
                    'label' => $status->labelAr(),
                    'value' => $n,
                    'chips' => [],
                    'href' => route('sales-orders.index', ['status' => $status->value]),
                    'tone' => $tone,
                    'subvalue' => 'أمر بيع',
                ];
            }
        }

        if ($this->ready('sales_orders', 'delivery.view', $user) && Schema::hasColumn('sales_orders', 'preparation_status')) {
            $prepQueue = SalesOrder::query()
                ->visibleTo($user)
                ->where('status', SalesOrderStatus::Approved->value)
                ->where('fulfillment_status', '!=', FulfillmentStatus::FullyDelivered->value)
                ->where('preparation_status', PreparationStatus::Pending->value)
                ->count();

            $kpis[] = [
                'key' => 'preparation',
                'label' => 'طابور التجهيز',
                'value' => $prepQueue,
                'chips' => $this->chips([
                    [$prepQueue, 'بانتظار التجهيز', 'warning'],
                ]),
                'href' => route('warehouse.preparation.index'),
                'tone' => 'warning',
            ];
        }

        if ($this->ready('deals', 'invoices.view', $user) || ($this->tableReady('deals') && $user->can('deals.close'))) {
            $dealsQuery = Deal::query()->where('status', DealStatus::Open->value);
            $this->scopeDealsToUser($dealsQuery, $user);
            $openDeals = $dealsQuery->count();

            $kpis[] = [
                'key' => 'deals',
                'label' => 'صفقات مفتوحة',
                'value' => $openDeals,
                'chips' => $this->chips([
                    [$openDeals, 'مفتوحة', 'process'],
                ]),
                'href' => route('deals.index', ['status' => 'open']),
                'tone' => 'process',
            ];
        }

        if ($this->ready('customer_visits', 'visits.view', $user)) {
            $base = CustomerVisit::query()->visibleTo($user);
            $total = (clone $base)->count();

            $today = (clone $base)
                ->where('status', VisitStatus::Scheduled->value)
                ->whereBetween('scheduled_at', [now()->startOfDay(), now()->endOfDay()])
                ->count();
            $upcoming = (clone $base)
                ->where('status', VisitStatus::Scheduled->value)
                ->where('scheduled_at', '>', now()->endOfDay())
                ->count();

            $kpis[] = [
                'key' => 'visits',
                'label' => 'الزيارات',
                'value' => $total,
                'chips' => $this->chips([
                    [$today, 'اليوم', 'warning'],
                    [$upcoming, 'قادمة', 'process'],
                ]),
                'href' => route('visits.index'),
                'tone' => 'warm',
            ];

            $charts[] = $this->visitsByWeek(clone $base);
        }

        $tz = (string) config('app.timezone');

        return [
            'greeting_name' => $user->name,
            'role_label' => $user->role?->name_ar,
            'scope_mode' => $scopeMode,
            'scope_label' => $scopeLabel,
            'today' => Carbon::now($tz)->locale('ar')->translatedFormat('l، j F Y'),
            'timezone' => $tz,
            'kpis' => $kpis,
            'charts' => array_values(array_filter($charts)),
            'is_empty' => $kpis === [],
        ];
    }

    /**
     * @return array{0: 'own'|'all', 1: string}
     */
    private function scopePresentation(User $user): array
    {
        if ($user->hasRole(RoleSlug::SalesRepresentative)) {
            return ['own', 'بياناتك فقط — عملاؤك المسندون'];
        }

        if ($user->hasRole(RoleSlug::SalesManager) || $user->hasRole(RoleSlug::Administrator)) {
            return ['all', 'كل بيانات المنصة'];
        }

        $sampleScope = $user->permissionScope('sales_orders.view')
            ?? $user->permissionScope('quotations.view')
            ?? $user->permissionScope('customers.view');

        if (in_array($sampleScope, [RecordScope::All, RecordScope::Admin], true)) {
            return ['all', 'كل البيانات ضمن صلاحيتك'];
        }

        return ['own', 'بياناتك ضمن نطاق صلاحيتك'];
    }

    private function ready(string $table, string $permission, User $user): bool
    {
        return $this->tableReady($table) && $user->can($permission);
    }

    private function tableReady(string $table): bool
    {
        return Schema::hasTable($table);
    }

    private function scopeDealsToUser(Builder $query, User $user): void
    {
        if ($user->can('deals.close') || $user->permissionScope('invoices.view') === \App\Support\Authorization\RecordScope::All) {
            return;
        }

        $scope = $user->permissionScope('invoices.view')
            ?? $user->permissionScope('customer_pos.view')
            ?? $user->permissionScope('sales_orders.view');

        if (in_array($scope, [
            \App\Support\Authorization\RecordScope::Cust,
            \App\Support\Authorization\RecordScope::Assigned,
            \App\Support\Authorization\RecordScope::Own,
        ], true)) {
            $query->whereHas('customer', fn (Builder $q) => $q->where('assigned_sales_rep_id', $user->id));

            return;
        }

        if ($scope === \App\Support\Authorization\RecordScope::All
            || $scope === \App\Support\Authorization\RecordScope::Admin) {
            return;
        }

        $query->whereRaw('0 = 1');
    }

    /**
     * @param  Builder<*>  $query
     * @return array<string, int>
     */
    private function statusCounts(Builder $query): array
    {
        return (clone $query)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * @param  list<array{0: int, 1: string, 2: string}>  $rows
     * @return list<array{text: string, tone: string}>
     */
    private function chips(array $rows): array
    {
        $out = [];
        foreach ($rows as [$count, $label, $tone]) {
            if ($count > 0) {
                $out[] = ['text' => number_format($count).' '.$label, 'tone' => $tone];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, int>  $counts
     * @param  list<array{0: \BackedEnum, 1: string}>  $map
     * @return list<array{label: string, value: int, tone: string}>
     */
    private function segments(array $counts, array $map): array
    {
        $segments = [];
        foreach ($map as [$case, $tone]) {
            $n = (int) ($counts[$case->value] ?? 0);
            if ($n > 0) {
                $segments[] = [
                    'label' => method_exists($case, 'labelAr') ? $case->labelAr() : (string) $case->value,
                    'value' => $n,
                    'tone' => $tone,
                ];
            }
        }

        return $segments;
    }

    /**
     * @param  array<string, int>  $counts
     * @param  list<array{0: \BackedEnum, 1: string}>  $map
     * @return array<string, mixed>|null
     */
    private function donut(string $title, string $unit, array $counts, array $map): ?array
    {
        $segments = $this->segments($counts, $map);

        return $segments === [] ? null : [
            'type' => 'donut',
            'title' => $title,
            'unit' => $unit,
            'segments' => $segments,
            'total' => array_sum(array_column($segments, 'value')),
        ];
    }

    /**
     * @param  array<string, int>  $counts
     * @param  list<array{0: \BackedEnum, 1: string}>  $map
     * @return array<string, mixed>|null
     */
    private function bars(string $title, string $unit, array $counts, array $map): ?array
    {
        $segments = $this->segments($counts, $map);

        return $segments === [] ? null : [
            'type' => 'bars',
            'title' => $title,
            'unit' => $unit,
            'segments' => $segments,
            'max' => max(array_column($segments, 'value')),
        ];
    }

    /**
     * @param  Builder<CustomerVisit>  $query
     * @return array<string, mixed>|null
     */
    private function visitsByWeek(Builder $query): ?array
    {
        $start = now()->subWeeks(5)->startOfWeek(Carbon::SATURDAY);

        $dates = $query
            ->where('status', '!=', VisitStatus::Cancelled->value)
            ->where('scheduled_at', '>=', $start)
            ->pluck('scheduled_at');

        if ($dates->isEmpty()) {
            return null;
        }

        $columns = [];
        for ($i = 0; $i < 6; $i++) {
            $weekStart = $start->copy()->addWeeks($i);
            $columns[$weekStart->format('Y-m-d')] = [
                'label' => $weekStart->copy()->locale('ar')->translatedFormat('j M'),
                'value' => 0,
                'current' => $weekStart->isSameWeek(now(), Carbon::SATURDAY),
            ];
        }

        foreach ($dates as $date) {
            $key = Carbon::parse($date)->startOfWeek(Carbon::SATURDAY)->format('Y-m-d');
            if (isset($columns[$key])) {
                $columns[$key]['value']++;
            }
        }

        $columns = array_values($columns);

        return [
            'type' => 'columns',
            'title' => 'الزيارات أسبوعيًا',
            'unit' => 'زيارة',
            'columns' => $columns,
            'max' => max(1, max(array_column($columns, 'value'))),
        ];
    }
}
