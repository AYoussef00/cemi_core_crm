<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateApprovalAuthoritiesRequest;
use App\Models\ApprovalAuthority;
use App\Models\Role;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ApprovalAuthorityController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()?->can('approval_authority.configure'), 403);

        return view('admin.approvals.edit', [
            'authorities' => ApprovalAuthority::query()->with('assignedRole')->orderBy('workflow_key')->get(),
            'roles' => Role::query()->orderBy('name_ar')->get(),
        ]);
    }

    public function update(UpdateApprovalAuthoritiesRequest $request, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $audit): void {
            $before = [];
            $after = [];

            foreach (ApprovalAuthority::query()->orderBy('workflow_key')->get() as $row) {
                $before[$row->workflow_key] = (int) $row->assigned_role_id;
            }

            foreach ($request->input('authorities', []) as $id => $payload) {
                $authority = ApprovalAuthority::query()->find($id);
                if ($authority === null) {
                    continue;
                }

                $authority->update([
                    'assigned_role_id' => (int) $payload['assigned_role_id'],
                ]);
            }

            foreach (ApprovalAuthority::query()->orderBy('workflow_key')->get() as $row) {
                $after[$row->workflow_key] = (int) $row->assigned_role_id;
            }

            $audit->record('approval_authority.updated', null, [
                'before' => $before,
                'after' => $after,
            ]);
        });

        return redirect()
            ->route('admin.approvals.edit')
            ->with('status_ar', 'تم تحديث جهات الاعتماد.');
    }
}
