<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateWarehouseSettingsRequest;
use App\Models\Warehouse;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WarehouseSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()?->can('settings.manage'), 403);

        $warehouse = Warehouse::current();
        if ($warehouse !== null) {
            $warehouse->load(['attachments.uploader']);
        }

        return view('admin.settings.warehouse', [
            'warehouse' => $warehouse,
            'isFirstTime' => $warehouse === null,
            'attachments' => $warehouse?->attachments ?? collect(),
            'attachmentLimits' => [
                'max_kilobytes' => (int) config('attachments.max_kilobytes', 5120),
                'extensions' => config('attachments.allowed_extensions', []),
            ],
        ]);
    }

    public function update(UpdateWarehouseSettingsRequest $request, AuditLogger $audit): RedirectResponse
    {
        $keys = ['name_ar', 'address_ar', 'contact_phone'];
        $wasFirstTime = ! Warehouse::isConfigured();
        $before = $wasFirstTime ? [] : Warehouse::current()?->only($keys) ?? [];

        DB::transaction(function () use ($request, $audit, $wasFirstTime, $before, $keys): void {
            $warehouse = Warehouse::saveSingleton($request->validated());
            $after = $warehouse->only($keys);
            $changes = $audit->changes($before, $after, $keys);

            $audit->record(
                $wasFirstTime ? 'warehouse.created' : 'warehouse.updated',
                $warehouse,
                $wasFirstTime
                    ? ['after' => $changes['after']]
                    : $changes,
            );
        });

        $message = $wasFirstTime
            ? 'تم حفظ المستودع بنجاح.'
            : 'تم تحديث المستودع بنجاح.';

        return redirect()
            ->route('admin.settings.warehouse.edit')
            ->with('status_ar', $message);
    }
}
