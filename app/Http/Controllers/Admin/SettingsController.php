<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Warehouse;
use App\Support\Mail\MailConfigurationInspector;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request, MailConfigurationInspector $mailInspector): View
    {
        abort_unless($request->user()?->can('settings.manage'), 403);

        $company = Company::current();
        $warehouse = Warehouse::current();

        return view('admin.settings.index', [
            'company' => $company,
            'warehouse' => $warehouse,
            'companyConfigured' => $company !== null && $company->isComplete(),
            'warehouseConfigured' => $warehouse !== null && $warehouse->isComplete(),
            'mailStatus' => $mailInspector->status(),
        ]);
    }
}
