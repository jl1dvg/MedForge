<?php

namespace App\Modules\Derivaciones\Http\Controllers;

use App\Modules\Shared\Support\LegacyCurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DerivacionesUiController
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!Auth::check()) {
            return redirect('/auth/login?auth_required=1');
        }

        return view('derivaciones.v2-index', [
            'pageTitle' => 'Derivaciones',
            'currentUser' => LegacyCurrentUser::resolve($request),
        ]);
    }
}
