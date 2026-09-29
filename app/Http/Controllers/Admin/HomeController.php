<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Sends the admin to the first section their role allows. A role may have
     * no dashboard access, so the dashboard cannot be assumed.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        // This redirect is itself a hop with no rendered view, so flashed
        // session data (e.g. the post-login toast) would otherwise expire
        // here before ever reaching the page the user lands on.
        $request->session()->reflash();

        $route = $request->user('admin')->homeRoute();

        return redirect()->route($route ?? 'admin.no-access');
    }
}
