<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreCustomerPhotoRequest;
use App\Models\Attachment;
use App\Models\Customer;
use App\Support\Attachments\AttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerPhotoController extends Controller
{
    public function index(Customer $customer): View
    {
        $this->authorize('view', $customer);

        $customer->load(['attachments' => fn ($q) => $q->latest('id')->with('uploader')]);

        return view('customers.photos', [
            'customer' => $customer,
            'canManage' => request()->user()?->can('update', $customer) ?? false,
            'photoLimits' => [
                'extensions' => config('attachments.customer_photo_extensions', []),
                'max_kilobytes' => (int) config('attachments.max_kilobytes', 5120),
            ],
        ]);
    }

    public function store(
        StoreCustomerPhotoRequest $request,
        Customer $customer,
        AttachmentService $attachments,
    ): RedirectResponse {
        try {
            $attachments->store($customer, $request->file('file'), $request->user());
        } catch (RuntimeException $e) {
            return redirect()
                ->route('customers.photos.index', $customer)
                ->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()
            ->route('customers.photos.index', $customer)
            ->with('status_ar', 'تم رفع صورة العميل بنجاح.');
    }

    public function download(
        Request $request,
        Customer $customer,
        Attachment $attachment,
        AttachmentService $attachments,
    ): StreamedResponse {
        $this->authorize('view', $customer);
        $attachments->assertBelongsTo($attachment, $customer);

        return $attachments->download($attachment);
    }

    public function destroy(
        Request $request,
        Customer $customer,
        Attachment $attachment,
        AttachmentService $attachments,
    ): RedirectResponse {
        $this->authorize('update', $customer);
        $attachments->assertBelongsTo($attachment, $customer);
        $attachments->delete($attachment, $request->user());

        return redirect()
            ->route('customers.photos.index', $customer)
            ->with('status_ar', 'تم حذف الصورة.');
    }
}
