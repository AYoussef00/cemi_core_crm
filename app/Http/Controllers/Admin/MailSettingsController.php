<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Mail\MailConfigurationInspector;
use App\Support\Mail\MailTestSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class MailSettingsController extends Controller
{
    public function show(Request $request, MailConfigurationInspector $inspector): View
    {
        abort_unless($request->user()?->can('settings.manage'), 403);

        return view('admin.settings.mail', [
            'status' => $inspector->status(),
            'adminEmailMasked' => $inspector->maskEmail((string) $request->user()->email),
        ]);
    }

    public function sendTest(Request $request, MailTestSender $sender): RedirectResponse
    {
        abort_unless($request->user()?->can('settings.manage'), 403);

        // Recipient is always the authenticated administrator — no client-supplied address.
        try {
            $result = $sender->sendToSelf($request->user());
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.settings.mail.show')
                ->withErrors(['mail_test' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.settings.mail.show')
            ->with('status_ar', $result['message_ar'])
            ->with('mail_test_local_only', $result['local_only']);
    }
}
