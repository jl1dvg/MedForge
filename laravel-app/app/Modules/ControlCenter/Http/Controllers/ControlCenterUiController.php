<?php

namespace App\Modules\ControlCenter\Http\Controllers;

use Illuminate\Contracts\View\View;

class ControlCenterUiController
{
    public function index(): View
    {
        return view('control-center.index');
    }
}
