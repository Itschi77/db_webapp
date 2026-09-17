<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FakturierungController extends Controller
{
    public function index(Request $request)
    {
        $mode = session('frontend_mode', 'classic');

        return view($mode.'.fakturierung.index', [
            'adUsername' => $request->attributes->get('ad_username'),
        ]);
    }
}
