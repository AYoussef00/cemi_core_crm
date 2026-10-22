<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCompanySettingsRequest;
use App\Models\Company;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CompanySettingsController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()?->can('settings.manage'), 403);

        $company = Company::current();
        if ($company !== null) {
            $company->load(['attachments.uploader']);
        }

        return view('admin.settings.company', [
            'company' => $company,
            'isFirstTime' => $company === null,
            'attachments' => $company?->attachments ?? collect(),
            'attachmentLimits' => [
                'max_kilobytes' => (int) config('attachments.max_kilobytes', 5120),
                'extensions' => config('attachments.allowed_extensions', []),
            ],
        ]);
    }

    public function update(UpdateCompanySettingsRequest $request, AuditLogger $audit): RedirectResponse
    {
        $keys = ['name_ar', 'address_ar', 'contact_phone', 'contact_email'];
        $wasFirstTime = ! Company::isConfigured();
        $before = $wasFirstTime ? [] : Company::current()?->only($keys) ?? [];

        $company = DB::transaction(function () use ($request, $audit, $wasFirstTime, $before, $keys) {
            $company = Company::saveSingleton($request->validated());
            $after = $company->only($keys);
            $changes = $audit->changes($before, $after, $keys);

            $audit->record(
                $wasFirstTime ? 'company.created' : 'company.updated',
                $company,
                $wasFirstTime
                    ? ['after' => $changes['after']]
                    : $changes,
            );

            return $company;
        });

        $message = $wasFirstTime
            ? 'تم حفظ ملف الشركة بنجاح.'
            : 'تم تحديث ملف الشركة بنجاح.';

        return redirect()
            ->route('admin.settings.company.edit')
            ->with('status_ar', $message);
    }
}
