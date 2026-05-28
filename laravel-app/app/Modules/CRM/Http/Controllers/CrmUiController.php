<?php

namespace App\Modules\CRM\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class CrmUiController
{
    public function index(): mixed
    {
        if (!Auth::check()) {
            return redirect('/auth/login');
        }
        return view('crm.panel');
    }
}
