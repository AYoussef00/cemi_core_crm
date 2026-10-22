<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Authorization\AdministratorGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('role')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.create', [
            'roles' => Role::query()->orderBy('name_ar')->get(),
        ]);
    }

    public function store(StoreUserRequest $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        DB::transaction(function () use ($data, $audit): void {
            $user = User::query()->create($data);

            $audit->record('user.created', $user, [
                'after' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'is_active' => $user->is_active,
                ],
            ]);
        });

        return redirect()
            ->route('admin.users.index')
            ->with('status_ar', 'تم إنشاء المستخدم بنجاح.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.edit', [
            'user' => $user->load('role'),
            'roles' => Role::query()->orderBy('name_ar')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, AdministratorGuard $guard, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();
        $newRoleId = (int) $data['role_id'];
        $guard->assertMayChangeRole($user, $newRoleId);

        $wantsActive = $request->has('is_active')
            ? $request->boolean('is_active')
            : $user->is_active;

        if (! $wantsActive && $user->is_active) {
            $guard->assertMayDeactivate($user);
        }

        $before = $user->only(['name', 'email', 'role_id', 'is_active']);
        $passwordChanged = ! empty($data['password']);

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role_id' => $newRoleId,
            'is_active' => $wantsActive,
            'deactivated_at' => $wantsActive ? null : ($user->deactivated_at ?? now()),
        ];

        if ($passwordChanged) {
            $payload['password'] = $data['password'];
        }

        DB::transaction(function () use ($user, $payload, $before, $passwordChanged, $audit): void {
            $user->fill($payload)->save();
            $after = $user->fresh()->only(['name', 'email', 'role_id', 'is_active']);
            $changes = $audit->changes($before, $after, ['name', 'email', 'role_id', 'is_active']);

            $properties = $changes;
            if ($passwordChanged) {
                $properties['password_changed'] = true;
            }

            $audit->record('user.updated', $user, $properties);
        });

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('status_ar', 'تم تحديث المستخدم بنجاح.');
    }

    public function deactivate(User $user, AdministratorGuard $guard, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('deactivate', $user);
        $guard->assertMayDeactivate($user);

        DB::transaction(function () use ($user, $audit): void {
            $user->deactivate();
            $audit->record('user.deactivated', $user, [
                'after' => ['is_active' => false],
            ]);
        });

        return back()->with('status_ar', 'تم إيقاف المستخدم.');
    }

    public function activate(User $user, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('activate', $user);

        DB::transaction(function () use ($user, $audit): void {
            $user->activate();
            $audit->record('user.activated', $user, [
                'after' => ['is_active' => true],
            ]);
        });

        return back()->with('status_ar', 'تم إعادة تفعيل المستخدم.');
    }

    public function sendPasswordReset(User $user, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('sendPasswordReset', $user);

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_LINK_SENT) {
            $audit->record('user.password_reset_link_sent', $user, [
                'email' => $user->email,
            ]);

            return back()->with('status_ar', 'تم إرسال رابط إعادة تعيين كلمة المرور إلى بريد المستخدم.');
        }

        return back()->withErrors([
            'password_reset' => 'تعذر إرسال رابط إعادة التعيين. تحقق من إعدادات البريد.',
        ]);
    }
}
