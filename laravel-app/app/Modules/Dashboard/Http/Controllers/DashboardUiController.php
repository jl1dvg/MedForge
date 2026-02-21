<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardUiController
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        return view('dashboard.v2', [
            'summaryEndpoint' => '/v2/dashboard/summary',
            'startDate' => trim((string) $request->query('start_date', '')),
            'endDate' => trim((string) $request->query('end_date', '')),
        ]);
    }
}
