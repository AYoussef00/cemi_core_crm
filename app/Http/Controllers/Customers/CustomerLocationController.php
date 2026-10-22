<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\UpdateCustomerLocationRequest;
use App\Models\Customer;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomerLocationController extends Controller
{
    public function edit(Customer $customer): View
    {
        $this->authorize('view', $customer);

        $canUpdate = request()->user()?->can('update', $customer) ?? false;

        return view('customers.location', [
            'customer' => $customer,
            'canUpdate' => $canUpdate,
            'mapConfig' => [
                'enabled' => (bool) config('maps.enabled', true),
                'tileUrl' => (string) config('maps.tile_url'),
                'attribution' => (string) config('maps.attribution'),
                'defaultCenter' => config('maps.default_center'),
                'defaultZoom' => (int) config('maps.default_zoom', 11),
                'maxZoom' => (int) config('maps.max_zoom', 19),
                'lat' => $customer->latitude,
                'lng' => $customer->longitude,
                'interactive' => $canUpdate,
            ],
        ]);
    }

    public function update(
        UpdateCustomerLocationRequest $request,
        Customer $customer,
        AuditLogger $audit,
    ): RedirectResponse {
        $payload = $request->locationPayload();
        $before = $customer->only(['address_ar', 'latitude', 'longitude']);

        $customer->fill($payload)->save();

        $changes = $audit->changes(
            $before,
            $customer->only(['address_ar', 'latitude', 'longitude']),
            ['address_ar', 'latitude', 'longitude'],
        );

        if ($changes['before'] !== [] || $changes['after'] !== []) {
            $audit->record('customer.location_updated', $customer, $changes);
        }

        return redirect()
            ->route('customers.location.edit', $customer)
            ->with('status_ar', 'تم حفظ موقع العميل.');
    }
}
