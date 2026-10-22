<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->can($permission)) {
            abort(403, 'ليس لديك صلاحية لتنفيذ هذا الإجراء.');
        }

        return $next($request);
    }
}
