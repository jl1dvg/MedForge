<?php

namespace App\Modules\Derivaciones\Http\Controllers;

use App\Modules\Shared\Support\LegacyCurrentUser;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DerivacionesUiController
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        return view('derivaciones.v2-index', [
            'pageTitle' => 'Derivaciones',
            'currentUser' => LegacyCurrentUser::resolve($request),
        ]);
    }
}
