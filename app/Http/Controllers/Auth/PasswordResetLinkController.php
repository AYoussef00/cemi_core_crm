<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password', [
            'mailConfigured' => $this->mailLooksConfigured(),
            'mailer' => config('mail.default'),
        ]);
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', __($status))->with('status_ar', 'إذا كان البريد مسجّلًا لدينا، ستصلك رسالة لإعادة تعيين كلمة المرور.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'تعذّر إرسال رابط إعادة التعيين. تحقق من البريد أو تواصل مع مسؤول النظام.']);
    }

    /**
     * Whether outbound mail appears configured beyond a silent local sink.
     */
    private function mailLooksConfigured(): bool
    {
        $mailer = (string) config('mail.default');

        if (in_array($mailer, ['log', 'array'], true)) {
            return false;
        }

        if ($mailer === 'smtp') {
            return filled(config('mail.mailers.smtp.host'))
                && filled(config('mail.from.address'));
        }

        return filled(config('mail.from.address'));
    }
}
