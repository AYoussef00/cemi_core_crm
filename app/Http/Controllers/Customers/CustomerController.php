<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\CustomerVisit;
use App\Models\SalesRequest;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Authorization\RoleSlug;
use App\Support\Customers\CustomerCommercialSnapshot;
use App\Support\Customers\CustomerContactRole;
use App\Support\Customers\CustomerContactSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        /** @var User $user */
        $user = $request->user();

        $q = trim((string) $request->query('q', ''));

        $customers = Customer::query()
            ->visibleTo($user)
            ->search($q !== '' ? $q : null)
            ->with(['assignedSalesRep', 'contacts'])
            ->withCount('contacts')
            ->orderBy('name_ar')
            ->paginate(20)
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'q' => $q,
            'canManage' => $user->can('create', Customer::class),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Customer::class);

        /** @var User $user */
        $user = $request->user();

        return view('customers.create', [
            'salesReps' => $this->salesRepOptions($user),
            'contactRoles' => CustomerContactRole::cases(),
            'canAssignRep' => $user->hasRole(RoleSlug::SalesManager),
        ]);
    }

    public function store(
        StoreCustomerRequest $request,
        AuditLogger $audit,
        CustomerContactSync $contacts,
    ): RedirectResponse {
        $payload = $request->customerPayload();

        $customer = DB::transaction(function () use ($payload, $audit, $contacts): Customer {
            $customer = Customer::query()->create([
                'name_ar' => $payload['name_ar'],
                'assigned_sales_rep_id' => $payload['assigned_sales_rep_id'],
            ]);

            $synced = $contacts->sync($customer, $payload['contacts']);

            $audit->record('customer.created', $customer, [
                'name_ar' => $customer->name_ar,
                'assigned_sales_rep_id' => $customer->assigned_sales_rep_id,
                'contacts_count' => count($synced),
                'contact_roles' => array_values(array_unique(array_column($synced, 'role'))),
            ]);

            return $customer;
        });

        return redirect()
            ->route('customers.show', $customer)
            ->with('status_ar', 'تم إنشاء سجل العميل بنجاح.');
    }

    public function show(Customer $customer, CustomerCommercialSnapshot $commercial): View
    {
        $this->authorize('view', $customer);

        $customer->load([
            'assignedSalesRep',
            'contacts',
            'visits' => fn ($q) => $q->with('assignedUser')->limit(20),
        ]);

        /** @var User $viewer */
        $viewer = request()->user();

        return view('customers.show', [
            'customer' => $customer,
            'commercial' => $commercial->for($customer, $viewer),
            'canUpdate' => $viewer->can('update', $customer),
            'canScheduleVisit' => $viewer->can('createForCustomer', [CustomerVisit::class, $customer]),
            'canCreateSalesRequest' => $viewer->can('createForCustomer', [SalesRequest::class, $customer]),
            'canViewVisits' => $viewer->can('visits.view'),
        ]);
    }

    public function edit(Request $request, Customer $customer): View
    {
        $this->authorize('update', $customer);

        /** @var User $user */
        $user = $request->user();

        $customer->load('contacts');

        return view('customers.edit', [
            'customer' => $customer,
            'salesReps' => $this->salesRepOptions($user),
            'contactRoles' => CustomerContactRole::cases(),
            'canAssignRep' => $user->hasRole(RoleSlug::SalesManager),
        ]);
    }

    public function update(
        UpdateCustomerRequest $request,
        Customer $customer,
        AuditLogger $audit,
        CustomerContactSync $contacts,
    ): RedirectResponse {
        $payload = $request->customerPayload();

        DB::transaction(function () use ($payload, $customer, $audit, $contacts): void {
            $before = $customer->only(['name_ar', 'assigned_sales_rep_id']);

            $attributes = ['name_ar' => $payload['name_ar']];
            if (array_key_exists('assigned_sales_rep_id', $payload)) {
                $attributes['assigned_sales_rep_id'] = $payload['assigned_sales_rep_id'];
            }

            $customer->fill($attributes)->save();

            $synced = $contacts->sync($customer, $payload['contacts']);

            $changes = $audit->changes(
                $before,
                $customer->only(['name_ar', 'assigned_sales_rep_id']),
                ['name_ar', 'assigned_sales_rep_id'],
            );

            $audit->record('customer.updated', $customer, [
                ...$changes,
                'contacts_count' => count($synced),
                'contact_roles' => array_values(array_unique(array_column($synced, 'role'))),
            ]);
        });

        return redirect()
            ->route('customers.show', $customer)
            ->with('status_ar', 'تم تحديث سجل العميل بنجاح.');
    }

    /**
     * @return Collection<int, User>
     */
    private function salesRepOptions(User $actor)
    {
        if (! $actor->hasRole(RoleSlug::SalesManager)) {
            return collect();
        }

        return Customer::salesRepresentativeOptions()->get();
    }
}
