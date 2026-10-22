<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('account.profile', [
            'user' => $request->user()->loadMissing('role'),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($request, $user, $audit): void {
            // Password is hashed via the User model's `hashed` cast.
            $user->forceFill([
                'password' => $request->string('password')->toString(),
            ])->save();

            $audit->record('account.password_changed', $user, [
                'password_changed' => true,
            ]);
        });

        return back()->with('status_ar', 'تم تحديث كلمة المرور بنجاح.');
    }
}
