<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function store(ResetPasswordRequest $request, AuditLogger $audit): RedirectResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request, $audit): void {
                // Password is hashed via the User model's `hashed` cast.
                $user->forceFill([
                    'password' => $request->string('password')->toString(),
                    'remember_token' => Str::random(60),
                ])->save();

                /** @var User $user */
                $audit->record('auth.password_reset', $user, [
                    'password_changed' => true,
                    'email' => $user->email,
                ], $user);

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status_ar', 'تم تحديث كلمة المرور. يمكنك تسجيل الدخول الآن.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'رابط إعادة التعيين غير صالح أو منتهٍ. اطلب رابطًا جديدًا.']);
    }
}
