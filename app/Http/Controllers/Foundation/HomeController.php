<?php

namespace App\Http\Controllers\Foundation;

use App\Http\Controllers\Controller;
use App\Support\Dashboard\HomeDashboardBuilder;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    /**
     * Authenticated operational dashboard from real scoped records only.
     * Guests use `/` (login); this route is behind `auth`.
     */
    public function __invoke(HomeDashboardBuilder $dashboard): View
    {
        return view('foundation.dashboard', [
            'dashboard' => $dashboard->for(auth()->user()),
        ]);
    }
}
