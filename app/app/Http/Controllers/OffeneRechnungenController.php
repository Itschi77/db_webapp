<?php

namespace App\Http\Controllers;

use App\Models\Kunde;

class OffeneRechnungenController extends Controller
{
    public function index(int $kunde)
    {
        $kunde = Kunde::findOrFail($kunde);
        $mode = session('frontend_mode', 'classic');

        return view($mode . '.offene-rechnungen.index', compact('kunde'));
    }
}
