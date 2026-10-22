<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('audit.view'), 403);

        $query = AuditLog::query()
            ->with('actor')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->filled('action')) {
            $query->where('action', $request->string('action')->toString());
        }

        if ($request->filled('actor_user_id')) {
            $query->where('actor_user_id', $request->integer('actor_user_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->string('date_from')->toString());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->string('date_to')->toString());
        }

        $logs = $query->paginate(25)->withQueryString();

        return view('admin.audit.index', [
            'logs' => $logs,
            'actions' => config('audit.actions', []),
            'actors' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'filters' => [
                'action' => $request->string('action')->toString(),
                'actor_user_id' => $request->input('actor_user_id'),
                'date_from' => $request->string('date_from')->toString(),
                'date_to' => $request->string('date_to')->toString(),
            ],
        ]);
    }
}
