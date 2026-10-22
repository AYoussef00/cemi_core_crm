<?php

namespace App\Http\Controllers\Design;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Legacy local preview URL — redirects to the home dashboard design.
 */
class DashboardPreviewController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new NotFoundHttpException;
        }

        return redirect()->route('home', $request->query());
    }
}
