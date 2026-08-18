<?php

namespace App\Http\Controllers;

class PlaceholderController extends Controller
{
    public function show(string $module)
    {
        return view('placeholder', [
            'title' => trans("modules.$module"),
            'module' => $module,
        ]);
    }
}
