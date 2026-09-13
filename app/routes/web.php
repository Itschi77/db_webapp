<?php

use App\Http\Controllers\KundeController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/', function () {
    $mode = session('frontend_mode', 'classic');

    return view($mode . '.dashboard');
})->name('dashboard');

Route::get('/kunden', [KundeController::class, 'index'])
    ->name('kunden.index');

Route::get('/kunden/{id}/edit', [KundeController::class, 'edit'])
    ->name('kunden.edit');

Route::put('/kunden/{id}', [KundeController::class, 'update'])
    ->name('kunden.update');

Route::get('/kunden/{id}', [KundeController::class, 'show'])
    ->name('kunden.show');

Route::get('/frontend/{mode}', function (Request $request, string $mode) {
    if (!in_array($mode, ['classic', 'modern'], true)) {
        abort(404);
    }

    session(['frontend_mode' => $mode]);

    return redirect()->back();
})->name('frontend.switch');
