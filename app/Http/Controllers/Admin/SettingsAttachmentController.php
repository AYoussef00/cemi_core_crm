<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAttachmentRequest;
use App\Models\Attachment;
use App\Models\Company;
use App\Models\Warehouse;
use App\Support\Attachments\AttachmentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingsAttachmentController extends Controller
{
    public function storeCompany(StoreAttachmentRequest $request, AttachmentService $attachments): RedirectResponse
    {
        return $this->storeFor($request, $attachments, $this->requireCompany());
    }

    public function storeWarehouse(StoreAttachmentRequest $request, AttachmentService $attachments): RedirectResponse
    {
        return $this->storeFor($request, $attachments, $this->requireWarehouse());
    }

    public function downloadCompany(Request $request, Attachment $attachment, AttachmentService $attachments): StreamedResponse
    {
        $this->authorizeSettings($request);
        $attachments->assertBelongsTo($attachment, $this->requireCompany());

        return $attachments->download($attachment);
    }

    public function downloadWarehouse(Request $request, Attachment $attachment, AttachmentService $attachments): StreamedResponse
    {
        $this->authorizeSettings($request);
        $attachments->assertBelongsTo($attachment, $this->requireWarehouse());

        return $attachments->download($attachment);
    }

    public function destroyCompany(Request $request, Attachment $attachment, AttachmentService $attachments): RedirectResponse
    {
        $this->authorizeSettings($request);
        $company = $this->requireCompany();
        $attachments->assertBelongsTo($attachment, $company);
        $attachments->delete($attachment, $request->user());

        return redirect()
            ->route('admin.settings.company.edit')
            ->with('status_ar', 'تم حذف المرفق.');
    }

    public function destroyWarehouse(Request $request, Attachment $attachment, AttachmentService $attachments): RedirectResponse
    {
        $this->authorizeSettings($request);
        $warehouse = $this->requireWarehouse();
        $attachments->assertBelongsTo($attachment, $warehouse);
        $attachments->delete($attachment, $request->user());

        return redirect()
            ->route('admin.settings.warehouse.edit')
            ->with('status_ar', 'تم حذف المرفق.');
    }

    private function storeFor(StoreAttachmentRequest $request, AttachmentService $attachments, Model $attachable): RedirectResponse
    {
        $redirect = $attachable instanceof Company
            ? route('admin.settings.company.edit')
            : route('admin.settings.warehouse.edit');

        try {
            $attachments->store($attachable, $request->file('file'), $request->user());
        } catch (RuntimeException $e) {
            return redirect()
                ->to($redirect)
                ->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()
            ->to($redirect)
            ->with('status_ar', 'تم رفع المرفق بنجاح.');
    }

    private function authorizeSettings(Request $request): void
    {
        abort_unless($request->user()?->can('settings.manage'), 403);
    }

    private function requireCompany(): Company
    {
        $company = Company::current();
        abort_if($company === null, 404);

        return $company;
    }

    private function requireWarehouse(): Warehouse
    {
        $warehouse = Warehouse::current();
        abort_if($warehouse === null, 404);

        return $warehouse;
    }
}
